<?php

namespace App\Service;

use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Style\SymfonyStyle;

class FileShareMigration extends ShareMigration {
    const USER_SHARE_TYPE = 0;

    public function migrate(SymfonyStyle $io): bool {
        try {
            $csv = [['Circle ID', 'UID Owner', 'UID Initiator', 'File target', 'Circle Member', 'Result', 'Inserted share ID']];
            foreach ($io->progressIterate($this->conn->iterateAssociativeIndexed(
                'SELECT * FROM oc_share WHERE SHARE_TYPE = 7'
            )) as $data) {
                try {
                    $circleMembers = $this->resolveCircleMembers($data['share_with']);
                } catch (Exception $e) {
                    $csv[] = ['', '', '', '', '', 'Failed to get circle members: ' . $e->getMessage()];
                    continue;
                }
                $csv[] = [$data['share_with'], $data['uid_owner'], $data['uid_initiator'], $data['file_target']];
                if (empty($circleMembers)) {
                    $csv[] = ['', '', '', '', '', 'No circle members found'];
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
                    $data['attributes'] = '[["permissions","download",true]]';
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
                        $csv[] = ['', '', '', '', $circleMember, 'Success', $lastSharedId];
                    } catch (Exception|\AssertionError $e) {
                        $csv[] = ['', '', '', '', $circleMember, 'Failure: ' . $e->getMessage()];
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