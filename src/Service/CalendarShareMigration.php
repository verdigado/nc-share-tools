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
            $csv = [['Circle ID', 'Resource ID', 'Resource Owner', 'Result', 'Circle Member', 'Inserted share ID']];
            foreach ($this->conn->iterateAssociativeIndexed(
                'SELECT * FROM oc_dav_shares WHERE type = ? AND principaluri LIKE ?',
                [
                    0 => self::DAV_SHARE_CALENDAR_TYPE,
                    1 => self::PRINCIPALURI_CIRCLE_PREFIX.'%',
                ]
            ) as $row) {
                $circleId = $this->parsePrincipaluri($row['principaluri'], self::PRINCIPALURI_CIRCLE_PREFIX);

                if (!$circleId) {
                    $io->error("[{$row['principaluri']}] is not a valid circle principaluri");
                    continue;
                }

                $resourceOwner = $this->getResourceOwner($row['resourceid']);

                $csv[] = [$circleId, $row['resourceid'], $resourceOwner];
                if (!$this->validateCircle($circleId, $io)) {
                    // skip unreal circles
                    $csv[] = ['', '', '', 'Skipped: not a real circle'];
                }

                try {
                    $circleMembers = $this->resolveCircleMembers($circleId);
                } catch (Exception $e) {
                    $csv[] = ['', '', '', 'Failed to get circle members: ' . $e->getMessage()];
                    continue;
                }

                if (empty($circleMembers)) {
                    $csv[] = ['', '', '', 'No circle members found'];
                }

                foreach ($circleMembers as $circleMember) {
                    if ($circleMember === $resourceOwner) {
                        // don't share with owner of the calendar
                        continue;
                    }

                    $row['principaluri'] = self::PRINCIPALURI_USER_PREFIX . $circleMember;

                    try {
                        $qb = $this->conn->createQueryBuilder()
                            ->insert('oc_dav_shares');
                        foreach ($row as $column => $value) {
                            $qb->setValue($column, ':' . $column);
                        }
                        $qb->setParameters($row);
                        $result = $qb->executeStatement();
                        assert($result == 1);
                        $lastSharedId = $this->conn->lastInsertId();
                        $csv[] = ['', '', '', 'Success', $circleMember, $lastSharedId];
                    } catch (Exception $e) {
                        $csv[] = ['', '', '', 'Failure: ' . $e->getMessage(), $circleMember];
                    }
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
}