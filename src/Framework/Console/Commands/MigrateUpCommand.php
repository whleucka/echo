<?php

namespace Echo\Framework\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'migrate:up', description: 'Run up method on specific migration files')]
class MigrateUpCommand extends Command
{
    use MigrateTrait;

    protected function configure(): void
    {
        $this->addArgument('files', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'Migration file names');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMigrations();

        $files = $input->getArgument('files');
        $migrationFiles = $this->getMigrationFiles(config("paths.migrations"));

        foreach ($files as $basename) {
            if (array_key_exists($basename, $migrationFiles)) {
                $this->migrationUp($migrationFiles[$basename]);
                $output->writeln("<info>Migrated: $basename</info>");
            } else {
                $output->writeln("<error>Migration file doesn't exist: $basename</error>");
                return Command::FAILURE;
            }
        }

        $this->printStatus($output);
        return Command::SUCCESS;
    }
}
