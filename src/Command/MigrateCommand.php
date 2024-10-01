<?php

namespace App\Command;

use App\Service\CalendarShareMigration;
use App\Service\DeckShareMigration;
use App\Service\FileShareMigration;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'migrate',
    description: 'Migrate nextcloud circle shares to single user shares',
)]
class MigrateCommand extends Command {
    public function __construct(
        private readonly FileShareMigration $fileShareMigration,
        private readonly CalendarShareMigration $calendarShareMigration,
        private readonly DeckShareMigration $deckShareMigration,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);

        $io->info("Starting file share migration...");
        $fileStatus = $this->fileShareMigration->migrate($io);
        $this->formatPartialResult($io, $fileStatus, 'files/folders');

        $io->info("Starting calendar share migration...");
        $calendarStatus = $this->calendarShareMigration->migrate($io);
        $this->formatPartialResult($io, $calendarStatus, 'calendar');

        $io->info("Starting deck share migration...");
        $deckStatus = $this->deckShareMigration->migrate($io);
        $this->formatPartialResult($io, $deckStatus, 'deck');

        return $fileStatus && $calendarStatus && $deckStatus ? Command::SUCCESS : Command::FAILURE;
    }

    private function formatPartialResult(SymfonyStyle $io, bool $result, string $type): void {
        if ($result) {
            $io->success("Partial migration completed for {$type}.");
        } else {
            $io->error("Partial migration failed for {$type}.");
        }
    }
}
