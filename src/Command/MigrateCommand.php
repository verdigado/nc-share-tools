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
        $fileStatus = $this->fileShareMigration->migrate($io);
        $calendarStatus = $this->calendarShareMigration->migrate($io);
        $deckStatus = $this->deckShareMigration->migrate($io);
        return $fileStatus && $calendarStatus && $deckStatus ? Command::SUCCESS : Command::FAILURE;
    }
}
