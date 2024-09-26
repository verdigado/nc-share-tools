<?php

namespace App\Service;

use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Style\SymfonyStyle;

class FileShareMigration extends ShareMigration {
    public function migrate(SymfonyStyle $io): bool {
        try {
            $csv = [['Circle ID', 'UID Owner', 'UID Initiator', 'File target', 'Result', 'Circle Member', 'Inserted share ID']];
            foreach ($io->progressIterate($this->conn->iterateAssociativeIndexed(
                'SELECT * FROM oc_share WHERE SHARE_TYPE = ?',
                [0 => self::CIRCLE_SHARE_TYPE]
            )) as $data) {
                $csv[] = [$data['share_with'], $data['uid_owner'], $data['uid_initiator'], $data['file_target']];

                if (!$this->validateCircle($data['share_with'], $io)) {
                    // skip unreal circles
                    $csv[] = ['', '', '', '', 'Skipped: not a real circle'];
                    continue;
                }

                try {
                    $circleMembers = $this->resolveCircleMembers($data['share_with']);
                } catch (Exception $e) {
                    $csv[] = ['', '', '', '', 'Failed to get circle members: ' . $e->getMessage()];
                    continue;
                }

                if (empty($circleMembers)) {
                    $csv[] = ['', '', '', '', 'No circle members found'];
                }

                foreach ($circleMembers as $circleMember) {
                    if ($data['uid_owner'] === $circleMember) {
                        // don't share with the owner of the file
                        continue;
                    }
                    $data['share_type'] = self::USER_SHARE_TYPE;
                    $data['share_with'] = $circleMember;
                    $data['password'] = null;
                    $data['token'] = null;
                    $data['attributes'] = self::SHARE_DOWNLOAD_PERMISSION;
                    try {
                        $qb = $this->conn->createQueryBuilder()
                            ->insert('oc_share');
                        foreach ($data as $column => $value) {
                            $qb->setValue($column, ':' . $column);
                        }
                        $qb->setParameters($data);
                        $result = $qb->executeStatement();
                        assert($result == 1);
                        $lastSharedId = $this->conn->lastInsertId();
                        $csv[] = ['', '', '', '', 'Success', $circleMember, $lastSharedId];
                    } catch (Exception|\AssertionError $e) {
                        $csv[] = ['', '', '', '', 'Failure: ' . $e->getMessage(), $circleMember];
                    }
                }
            }

            $this->writeResultToCsv($csv, $io);
            return true;
        } catch (Exception $e) {
            $io->error('Failed to get circle shares: ' . $e->getMessage());
            return false;
        }
    }
}