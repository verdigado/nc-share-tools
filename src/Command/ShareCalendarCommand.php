<?php

namespace App\Command;

use App\Service\CalendarShareMigration;
use App\Service\CsvExport;
use App\Service\ExportFilename;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'share:calendar',
    description: 'Create new calendar share',
)]
class ShareCalendarCommand extends Command {

    public const ACCESS_READ_WRITE = 2;

    public const SYSTEM_CALENDAR = 'principals/system/system';

    public function __construct(
        private readonly Connection $connection,
        private readonly CsvExport $csvExport,
        ?string                     $name = null
    ) {
        parent::__construct($name);
    }


    protected function configure(): void {
        $this->addArgument('userId', InputArgument::REQUIRED, 'User ID to share all calendars');
        $this->addOption('skipUserCheck', 's', InputOption::VALUE_NONE, 'Skip user check');
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);

        try {
            $this->connection->beginTransaction();
            $userId = $input->getArgument('userId');

            if (!$input->getOption('skipUserCheck')) {
                $result = $this->connection->executeQuery(
                    'SELECT * FROM oc_ldap_user_mapping where owncloud_name = :userId',
                    ['userId' => $userId]
                );

                if (!$result->fetchOne()) {
                    $io->error('User does not exist');
                    return Command::INVALID;
                }
            }

            $filename = $this->csvExport->getUniqueFilename(ExportFilename::CreateShareCalendar);

            $file = fopen($filename, 'w');

            if (!$file) {
                $io->error('Unable to open writable CSV file: ' . $filename);
                return Command::FAILURE;
            }

            fputcsv($file, [$userId, '']);

            foreach($this->connection->iterateAssociativeIndexed('SELECT * from oc_calendars') as $id => $data) {
                if (str_ends_with($data['principaluri'], "/$userId") || $data['principaluri'] === self::SYSTEM_CALENDAR) {
                    continue;
                }
                $this->connection->insert('oc_dav_shares', [
                    'principaluri' => "principals/users/$userId",
                    'type' => CalendarShareMigration::DAV_SHARE_CALENDAR_TYPE,
                    'access' => self::ACCESS_READ_WRITE,
                    'resourceid' => $id,
                    'publicuri' => null,
                ]);
                fputcsv($file, ['', $id]);
            }

            if (!fclose($file)) {
                $io->error('Unable to close CSV file: ' . $filename);
                return Command::FAILURE;
            }

            $this->connection->commit();

        } catch (Throwable $exception) {
            $this->connection->rollBack();
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }

        $io->success('All calendars shared');
        return Command::SUCCESS;
    }


}
