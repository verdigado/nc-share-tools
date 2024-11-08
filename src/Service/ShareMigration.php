<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class ShareMigration extends CsvExport {

    const REAL_CIRCLE_TYPE = 16;

    const CSV_EXPORT_PADDING = ['', '', ''];

    public function __construct(protected Connection $conn) {
    }

    abstract public function migrate(SymfonyStyle $io): bool;

    abstract protected function getCsvExportHeader(): array;

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

    /**
     * Some circles are not "real" circles. There are app circles, group circles, user circles, etc.
     * So this function makes sure it is a "real" circle, because these are the shares we want to migrate.
     * Reference: https://github.com/nextcloud/circles/blob/388d39bdd61ed125466777eea4f6163768132184/lib/Model/Member.php#L53
     *
     * @param string $circleId
     * @param SymfonyStyle $io
     * @return bool
     */
    protected function validateCircle(string $circleId, SymfonyStyle $io): bool {
        try {
            $result = $this->conn->createQueryBuilder()
                ->select('source')
                ->from('oc_circles_circle')
                ->where('unique_id = ?')
                ->setParameter(0, $circleId)
                ->fetchOne();
        } catch (Exception $e) {
            $io->error("Circle validation failed [{$circleId}]: ".$e->getMessage());
            return false;
        }

        if (!$result || $result !== self::REAL_CIRCLE_TYPE) {
            return false;
        }
        return true;
    }

    protected function addUserShare(string $table, array $columns, string $circleMember): array {
        try {
            $qb = $this->conn->createQueryBuilder()
                ->insert($table);

            foreach ($columns as $column => $value) {
                $qb->setValue($column, ':' . $column);
            }
            $qb->setParameters($columns);
            $result = $qb->executeStatement();

            assert($result == 1);
            $lastShareId = $this->conn->lastInsertId();
            return $this->formatLeftPadding(['Success', $circleMember, $lastShareId]);
        } catch (Exception|\AssertionError $e) {
            return $this->formatLeftPadding(['Failure: ' . $e->getMessage(), $circleMember]);
        }
    }

    protected function formatLeftPadding(array $result): array {
        return array_merge(self::CSV_EXPORT_PADDING, $result);
    }

}