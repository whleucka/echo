<?php

namespace Echo\Framework\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'migrate:run', description: 'Run all pending migrations')]
class MigrateRunCommand extends Command
{
    use MigrateTrait;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMigrations();

        $output->writeln("Running migrations...");

        $batch = $this->getNextBatchNumber();
        $ran = false;

        $migrationFiles = $this->getMigrationFiles(config("paths.migrations"));
        foreach ($migrationFiles as $basename => $filePath) {
            $hash = md5($filePath);
            $migration = $this->migrationHashExists($hash);
            if (!$migration) {
                if ($this->migrationUp($filePath, $batch)) {
                    $ran = true;
                }
            }
        }

        if (!$ran) {
            $output->writeln("Nothing to migrate.");
        }

        $this->printStatus($output);
        return Command::SUCCESS;
    }
}
