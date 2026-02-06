<?php

namespace Echo\Framework\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'db:backup', description: 'Create a database backup')]
class DbBackupCommand extends Command
{
    use DbTrait;

    protected function configure(): void
    {
        $this
            ->addArgument('filename', InputArgument::OPTIONAL, 'Backup filename')
            ->addOption('keep', 'k', InputOption::VALUE_OPTIONAL, 'Keep only the last N backups');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->ensureBackupDir();
        $db = $this->getDbConfig();

        $filename = $input->getArgument('filename') ?? date('Y-m-d_His') . '_' . $db['name'];
        if (!str_ends_with($filename, '.sql.gz') && !str_ends_with($filename, '.sql')) {
            $filename .= '.sql.gz';
        }

        $filepath = $this->getBackupDir() . '/' . $filename;
        $useGzip = str_ends_with($filename, '.gz');

        $output->writeln("Creating backup of database '{$db['name']}'...");

        $command = sprintf(
            'mysqldump -h%s -P%s -u%s -p%s %s',
            escapeshellarg($db['host']),
            escapeshellarg($db['port']),
            escapeshellarg($db['user']),
            escapeshellarg($db['pass']),
            escapeshellarg($db['name'])
        );

        if ($useGzip) {
            $command .= ' | gzip';
        }

        $command .= ' > ' . escapeshellarg($filepath) . ' 2>&1';

        $cmdOutput = [];
        $returnCode = 0;
        exec($command, $cmdOutput, $returnCode);

        if ($returnCode !== 0 || !file_exists($filepath) || filesize($filepath) === 0) {
            @unlink($filepath);
            $output->writeln("<error>✗ Backup failed</error>");
            if (!empty($cmdOutput)) {
                $output->writeln("  " . implode("\n  ", $cmdOutput));
            }
            return Command::FAILURE;
        }

        $size = $this->formatSize(filesize($filepath));
        $output->writeln("<info>✓ Backup created: $filename ($size)</info>");

        $keep = $input->getOption('keep');
        if ($keep !== null) {
            $this->cleanupOldBackups((int) $keep, $output);
        }

        return Command::SUCCESS;
    }
}
