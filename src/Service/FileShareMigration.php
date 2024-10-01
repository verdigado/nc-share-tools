<?php

namespace App\Service;

use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Style\SymfonyStyle;

class FileShareMigration extends ShareMigration {

    const USER_SHARE_TYPE = 0;

    const CIRCLE_SHARE_TYPE = 7;

    const SHARE_DOWNLOAD_PERMISSION = '[["permissions","download",true]]';

    public function migrate(SymfonyStyle $io): bool {
        try {
            $csv = [$this->getCsvExportHeader()];
            foreach ($io->progressIterate($this->conn->iterateAssociativeIndexed(
                'SELECT * FROM oc_share WHERE SHARE_TYPE = ?',
                [0 => self::CIRCLE_SHARE_TYPE]
            )) as $row) {
                $csv[] = [$row['share_with'], "{$row['uid_owner']}/{$row['uid_initiator']}", $row['file_target']];

                if (!$this->validateCircle($row['share_with'], $io)) {
                    // skip unreal circles
                    $csv[] = $this->formatLeftPadding(['Skipped: not a real circle']);
                    continue;
                }

                try {
                    $circleMembers = $this->resolveCircleMembers($row['share_with']);
                } catch (Exception $e) {
                    $csv[] = $this->formatLeftPadding(['Failed to get circle members: ' . $e->getMessage()]);
                    continue;
                }

                if (empty($circleMembers)) {
                    $csv[] = $this->formatLeftPadding(['No circle members found']);
                }

                foreach ($circleMembers as $circleMember) {
                    if ($row['uid_owner'] === $circleMember) {
                        // don't share with the owner of the file
                        continue;
                    }
                    $row['share_type'] = self::USER_SHARE_TYPE;
                    $row['share_with'] = $circleMember;
                    $row['password'] = null;
                    $row['token'] = null;
                    $row['attributes'] = self::SHARE_DOWNLOAD_PERMISSION;
                    $csv[] = $this->addUserShare('oc_share', $row, $circleMember);
                }
            }

            $this->writeResultToCsv($csv, ExportFilename::FileShare, $io);
            return true;
        } catch (Exception $e) {
            $io->error('Failed to get circle shares: ' . $e->getMessage());
            return false;
        }
    }


    protected function getCsvExportHeader(): array {
        return ['Circle ID', 'UID Owner/Initiator', 'File target', 'Result', 'Circle Member', 'Inserted share ID'];
    }
}
