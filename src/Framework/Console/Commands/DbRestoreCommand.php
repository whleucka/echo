<?php

namespace Echo\Framework\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(name: 'db:restore', description: 'Restore database from a backup')]
class DbRestoreCommand extends Command
{
    use DbTrait;

    protected function configure(): void
    {
        $this->addArgument('filename', InputArgument::OPTIONAL, 'Backup file to restore');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filename = $input->getArgument('filename');

        if (empty($filename)) {
            $output->writeln("<error>Usage: ./bin/console db restore <filename></error>");
            $output->writeln("");
            $output->writeln("Available backups:");
            
            $files = $this->getBackupFiles();
            if (empty($files)) {
                $output->writeln("  No backups found in: " . $this->getBackupDir());
            } else {
                foreach ($files as $file) {
                    $name = basename($file);
                    $size = $this->formatSize(filesize($file));
                    $date = date('Y-m-d H:i:s', filemtime($file));
                    $output->writeln(sprintf("  %-45s %10s  %s", $name, $size, $date));
                }
            }
            return Command::FAILURE;
        }

        $filepath = $this->getBackupDir() . '/' . $filename;

        if (!file_exists($filepath)) {
            $filepath = $filename;
            if (!file_exists($filepath)) {
                $output->writeln("<error>✗ Backup file not found: $filename</error>");
                return Command::FAILURE;
            }
        }

        $db = $this->getDbConfig();

        $output->writeln("This will restore database '{$db['name']}' from: " . basename($filepath));
        $output->writeln("<comment>WARNING: This will overwrite all existing data!</comment>");

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('Are you sure you want to continue? [y/N] ', false);

        if (!$helper->ask($input, $output, $question)) {
            $output->writeln("Restore cancelled.");
            return Command::SUCCESS;
        }

        $output->writeln("Restoring database '{$db['name']}'...");

        $useGzip = str_ends_with($filepath, '.gz');

        if ($useGzip) {
            $command = sprintf(
                'gunzip -c %s | mysql -h%s -P%s -u%s -p%s %s 2>&1',
                escapeshellarg($filepath),
                escapeshellarg($db['host']),
                escapeshellarg($db['port']),
                escapeshellarg($db['user']),
                escapeshellarg($db['pass']),
                escapeshellarg($db['name'])
            );
        } else {
            $command = sprintf(
                'mysql -h%s -P%s -u%s -p%s %s < %s 2>&1',
                escapeshellarg($db['host']),
                escapeshellarg($db['port']),
                escapeshellarg($db['user']),
                escapeshellarg($db['pass']),
                escapeshellarg($db['name']),
                escapeshellarg($filepath)
            );
        }

        $cmdOutput = [];
        $returnCode = 0;
        exec($command, $cmdOutput, $returnCode);

        if ($returnCode !== 0) {
            $output->writeln("<error>✗ Restore failed</error>");
            if (!empty($cmdOutput)) {
                $output->writeln("  " . implode("\n  ", $cmdOutput));
            }
            return Command::FAILURE;
        }

        $output->writeln("<info>✓ Database restored successfully from: " . basename($filepath) . "</info>");
        return Command::SUCCESS;
    }
}
