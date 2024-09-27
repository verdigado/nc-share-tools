<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class ShareMigration {

    const USER_SHARE_TYPE = 0;

    const CIRCLE_SHARE_TYPE = 7;

    const SHARE_DOWNLOAD_PERMISSION = '[["permissions","download",true]]';

    const REAL_CIRCLE_TYPE = 16;

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

    protected function writeResultToCsv(array $data, ExportFilename $filename, SymfonyStyle $io): void {
        $file = fopen($this->getUniqueFilename($filename), 'w');

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

    private function getUniqueFilename(ExportFilename $filename): string {
        $fullPath = dirname(__FILE__, 3)."/{$filename->value}_migration.csv";
        $counter = 1;

        // Check if the file exists, and if so, append a counter
        while (file_exists($fullPath)) {
            $fullPath = dirname(__FILE__, 3)."/{$filename->value}_migration_{$counter}.csv";
            $counter++;
        }

        return $fullPath;
    }

}