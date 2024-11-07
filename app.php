#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use App\Command\MigrateCommand;
use App\Service\CalendarShareMigration;
use App\Service\DeckShareMigration;
use App\Service\FileShareMigration;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\Console\Application;
use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__.'/.env');

// Define the database connection parameters from env variables
$connectionParams = [
    'dbname'   => $_ENV['DB_NAME'],
    'user'     => $_ENV['DB_USER'],
    'password' => $_ENV['DB_PASSWORD'],
    'host'     => $_ENV['DB_HOST'],
    'driver'   => $_ENV['DB_DRIVER'],
];

$conn = DriverManager::getConnection($connectionParams);

$application = new Application();

// ... register commands
$command = new MigrateCommand(
    new FileShareMigration($conn),
    new CalendarShareMigration($conn),
    new DeckShareMigration($conn)
);
$application->add($command);

$application->run();
