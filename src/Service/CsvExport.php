<?php

namespace App\Service;

use Symfony\Component\Console\Style\SymfonyStyle;

class CsvExport {

    public function writeResultToCsv(array $data, ExportFilename $filename, SymfonyStyle $io): void {
        $uniqueFilename = $this->getUniqueFilename($filename);
        $file = fopen($uniqueFilename, 'w');

        if (!$file) {
            $io->warning('Unable to open writable CSV file: ' . $uniqueFilename);
            return;
        }

        foreach ($data as $row) {
            fputcsv($file, $row);
        }

        if (!fclose($file)) {
            $io->warning('Unable to close CSV file: ' . $uniqueFilename);
        }
    }

    public function getUniqueFilename(ExportFilename $filename): string {
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
