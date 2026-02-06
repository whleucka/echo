<?php

namespace Echo\Framework\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(name: 'migrate:fresh', description: 'Drop all tables and re-run all migrations')]
class MigrateFreshCommand extends Command
{
    use MigrateTrait;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln("<comment>This operation will drop the current database if it exists.</comment>");

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('Are you sure you want to migrate a fresh database? [y/N] ', false);

        if (!$helper->ask($input, $output, $question)) {
            $output->writeln("Operation cancelled.");
            return Command::SUCCESS;
        }

        $dbName = config("db.name");

        $this->dropDatabase();
        $output->writeln("<info>✓ Successfully deleted database $dbName</info>");

        $this->newDatabase();
        $output->writeln("<info>✓ Successfully created new database $dbName</info>");

        $this->initMigrations();

        $migrationFiles = $this->getMigrationFiles(config("paths.migrations"));
        foreach ($migrationFiles as $basename => $filePath) {
            $this->migrationUp($filePath);
        }

        $this->printStatus($output);
        return Command::SUCCESS;
    }
}
