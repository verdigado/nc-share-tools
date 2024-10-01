<?php

namespace App\Service;

use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Style\SymfonyStyle;

class CalendarShareMigration extends ShareMigration {

    const PRINCIPALURI_CIRCLE_PREFIX = 'principals/circles/';

    const DAV_SHARE_CALENDAR_TYPE = 'calendar';

    const PRINCIPALURI_USER_PREFIX = 'principals/users/';

    public function migrate(SymfonyStyle $io): bool {
        try {
            $csv = [$this->getCsvExportHeader()];
            foreach ($io->progressIterate($this->conn->iterateAssociativeIndexed(
                'SELECT * FROM oc_dav_shares WHERE type = ? AND principaluri LIKE ?',
                [
                    0 => self::DAV_SHARE_CALENDAR_TYPE,
                    1 => self::PRINCIPALURI_CIRCLE_PREFIX.'%',
                ]
            )) as $row) {
                $circleId = $this->parsePrincipaluri($row['principaluri'], self::PRINCIPALURI_CIRCLE_PREFIX);

                if (!$circleId) {
                    $io->error("[{$row['principaluri']}] is not a valid circle principaluri");
                    continue;
                }

                $resourceOwner = $this->getResourceOwner($row['resourceid']);

                $csv[] = [$circleId, $row['resourceid'], $resourceOwner];
                if (!$this->validateCircle($circleId, $io)) {
                    // skip unreal circles
                    $csv[] = $this->formatLeftPadding(['Skipped: not a real circle']);
                    continue;
                }

                try {
                    $circleMembers = $this->resolveCircleMembers($circleId);
                } catch (Exception $e) {
                    $csv[] = $this->formatLeftPadding(['Failed to get circle members: ' . $e->getMessage()]);
                    continue;
                }

                if (empty($circleMembers)) {
                    $csv[] = $this->formatLeftPadding(['No circle members found']);
                    continue;
                }

                foreach ($circleMembers as $circleMember) {
                    if ($circleMember === $resourceOwner) {
                        // don't share with owner of the calendar
                        continue;
                    }

                    $row['principaluri'] = self::PRINCIPALURI_USER_PREFIX . $circleMember;

                    $csv[] = $this->addUserShare('oc_dav_shares', $row, $circleMember);
                }
            }

            $this->writeResultToCsv($csv, ExportFilename::CalendarShare, $io);
            return true;
        } catch (Exception $e) {
            $io->error('Failed to get circle shares: ' . $e->getMessage());
            return false;
        }
    }

    private function parsePrincipaluri(string $principaluri, string $prefix): string|bool {
        if (str_starts_with($principaluri, $prefix)) {
            return substr($principaluri, strlen($prefix));
        }
        return false;
    }

    private function getResourceOwner(int $resourceId): string|false {
        try {
            $result = $this->conn->createQueryBuilder()
                ->select('principaluri')
                ->from('oc_calendars')
                ->where('id = :resourceId')
                ->andWhere('principaluri LIKE :prefix')
                ->setParameters([
                    'resourceId' => $resourceId,
                    'prefix' => self::PRINCIPALURI_USER_PREFIX.'%',
                ])
                ->fetchOne();
            return $result ? $this->parsePrincipaluri($result, self::PRINCIPALURI_USER_PREFIX) : false;
        } catch (Exception $e) {
            return false;
        }
    }

    protected function getCsvExportHeader(): array {
        return ['Circle ID', 'Resource ID', 'Resource Owner', 'Result', 'Circle Member', 'Inserted share ID'];
    }
}