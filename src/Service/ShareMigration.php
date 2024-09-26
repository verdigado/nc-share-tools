<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class ShareMigration {
    public function __construct(protected Connection $conn) {
    }

    abstract public function migrate(SymfonyStyle $io): bool;

    /**
     * @throws Exception
     */
    protected function resolveCircleMembers(string $circleId): array {
        $result = $this->conn->createQueryBuilder()
            ->select('c.id, c.user_id')
            ->from('oc_circles_member', 'c')
            ->where('c.circle_id = ?')
            // check for active membership and ignore pending invitations
            ->andWhere('c.status = \'Member\'')
            ->setParameter(0, $circleId)
            ->fetchAllKeyValue();

        return array_unique($result);
    }

    protected function writeResultToCsv(array $data, SymfonyStyle $io): void {
        $file = fopen(dirname(__FILE__, 3).'/file_share_migration.csv', 'w');

        if (!$file) {
            $io->warning('Unable to open file share migration CSV file');
            return;
        }

        foreach ($data as $row) {
            fputcsv($file, $row);
        }

        if (!fclose($file)) {
            $io->warning('Unable to close file share migration CSV file');
        }
    }

}