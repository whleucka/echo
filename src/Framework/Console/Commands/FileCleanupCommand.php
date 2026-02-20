<?php

namespace Echo\Framework\Console\Commands;

use App\Models\FileInfo;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'file:cleanup', description: 'Clean up orphaned files in the uploads directory')]
class FileCleanupCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'List orphaned files without deleting them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = $input->getOption('dry-run');
        $uploadDir = config("paths.uploads");

        if (!is_dir($uploadDir)) {
            $output->writeln("<error>Uploads directory does not exist: {$uploadDir}</error>");
            return Command::FAILURE;
        }

        $output->writeln($dryRun ? "Scanning for orphaned files (dry run)..." : "Scanning for orphaned files...");

        try {
            // Get all stored_name values from file_info
            $records = FileInfo::where('id', '>', '0')->get();
            $knownFiles = [];
            foreach ($records as $record) {
                $knownFiles[$record->stored_name] = true;
            }

            // Scan uploads directory for files
            $orphanedFiles = [];
            $totalSize = 0;

            $files = scandir($uploadDir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $filePath = $uploadDir . '/' . $file;
                if (!is_file($filePath)) {
                    continue;
                }

                if (!isset($knownFiles[$file])) {
                    $orphanedFiles[] = $file;
                    $totalSize += filesize($filePath);
                }
            }

            if (empty($orphanedFiles)) {
                $output->writeln("<info>No orphaned files found.</info>");
                return Command::SUCCESS;
            }

            $output->writeln(sprintf(
                "Found %d orphaned file(s) totaling %s",
                count($orphanedFiles),
                format_bytes($totalSize)
            ));

            if ($dryRun) {
                foreach ($orphanedFiles as $file) {
                    $output->writeln("  - {$file}");
                }
                $output->writeln("<comment>Run without --dry-run to delete these files.</comment>");
                return Command::SUCCESS;
            }

            // Delete orphaned files
            $deleted = 0;
            foreach ($orphanedFiles as $file) {
                $filePath = $uploadDir . '/' . $file;
                if (unlink($filePath)) {
                    $deleted++;
                    $output->writeln("  Deleted: {$file}");
                } else {
                    $output->writeln("<error>  Failed to delete: {$file}</error>");
                }
            }

            $output->writeln(sprintf(
                "<info>Deleted %d orphaned file(s), freed %s</info>",
                $deleted,
                format_bytes($totalSize)
            ));

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln("<error>Error cleaning orphaned files: " . $e->getMessage() . "</error>");
            return Command::FAILURE;
        }
    }
}
