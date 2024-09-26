<?php

namespace App\Command;

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
    public function __construct(private readonly FileShareMigration $fileShareMigration, ?string $name = null) {
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        return $this->fileShareMigration->migrate($io) ? Command::SUCCESS : Command::FAILURE;
    }
}
