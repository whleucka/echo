<?php

namespace Echo\Framework\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'migrate:rollback', description: 'Rollback the last batch of migrations')]
class MigrateRollbackCommand extends Command
{
    use MigrateTrait;

    protected function configure(): void
    {
        $this->addOption('steps', 's', InputOption::VALUE_OPTIONAL, 'Number of batches to rollback', 1);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMigrations();

        $steps = (int) $input->getOption('steps');

        $output->writeln("Rolling back migrations...");

        $lastBatch = $this->getLastBatchNumber();

        if ($lastBatch === 0) {
            $output->writeln("Nothing to rollback.");
            return Command::SUCCESS;
        }

        $rolledBack = 0;
        for ($i = 0; $i < $steps && $lastBatch > 0; $i++) {
            $migrations = $this->getMigrationsFromBatch($lastBatch);

            foreach ($migrations as $migration) {
                $this->migrationDown($migration['filepath']);
                $output->writeln("Rolled back: " . $migration['basename']);
                $rolledBack++;
            }

            $lastBatch--;
        }

        if ($rolledBack === 0) {
            $output->writeln("Nothing to rollback.");
        } else {
            $output->writeln("<info>Rolled back $rolledBack migration(s).</info>");
        }

        $this->printStatus($output);
        return Command::SUCCESS;
    }
}
