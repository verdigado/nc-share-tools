<?php

namespace App\Service;

use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Style\SymfonyStyle;

class DeckShareMigration extends ShareMigration {

    const PERMISSION_TYPE_CIRCLE = 7;

    const PERMISSION_TYPE_USER = 0;

    public function migrate(SymfonyStyle $io): bool {
        $csv = [$this->getCsvExportHeader()];
        try {
            foreach ($this->conn->iterateAssociativeIndexed(
                'SELECT * FROM oc_deck_board_acl WHERE type = :type',
                ['type' => self::PERMISSION_TYPE_CIRCLE]
            ) as $row) {
                $boardOwner = $this->getBoardOwner($row['board_id']);

                $csv[] = [$row['participant'], $row['board_id'], $boardOwner];

                if (!$this->validateCircle($row['participant'], $io)) {
                    $csv[] = $this->formatLeftPadding(['Skipped: not a real circle']);
                    continue;
                }

                try {
                    $circleMembers = $this->resolveCircleMembers($row['participant']);
                } catch (Exception $e) {
                    $csv[] = $this->formatLeftPadding(['Failed to get circle members: ' . $e->getMessage()]);
                    continue;
                }

                if (empty($circleMembers)) {
                    $csv[] = $this->formatLeftPadding(['No circle members found']);
                }

                foreach ($circleMembers as $circleMember) {
                    if ($circleMember === $boardOwner) {
                        // don't share with the owner of the board
                        continue;
                    }

                    $row['type'] = self::PERMISSION_TYPE_USER;
                    $row['participant'] = $circleMember;

                    $csv[] = $this->addUserShare('oc_deck_board_acl', $row, $circleMember);
                }
            }

            $this->writeResultToCsv($csv, ExportFilename::DeckShare, $io);
            return true;
        } catch (Exception $e) {
            $io->error('Failed to get circle shares: ' . $e->getMessage());
            return false;
        }
    }

    private function getBoardOwner(int $boardId): string {
        try {
            return $this->conn->createQueryBuilder()
                ->select('owner')
                ->from('oc_deck_boards')
                ->where('id = :boardId')
                ->setParameter('boardId', $boardId)
                ->fetchOne();
        } catch (Exception $e) {
            return false;
        }
    }

    protected function getCsvExportHeader(): array {
        return ['Circle ID', 'Board ID', 'Board Owner', 'Result', 'Circle Member', 'Inserted share ID'];
    }
}
