<?php

namespace Echo\Framework\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'make:model', description: 'Create a new model class')]
class MakeModelCommand extends Command
{
    use MakeCommandTrait;

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The model name (e.g., User, BlogPost)');
        $this->addOption('migration', 'm', InputOption::VALUE_NONE, 'Also create a migration for this model');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $withMigration = $input->getOption('migration');

        // Normalize the class name
        $className = $this->toPascalCase($name);

        // Generate table name from class name
        $tableName = $this->toTableName($className);

        $modelsPath = config('paths.root') . 'app/Models';
        $filePath = $modelsPath . '/' . $className . '.php';

        $success = $this->generateFromStub(
            'model',
            $filePath,
            [
                'class' => $className,
                'table' => $tableName,
            ],
            $output
        );

        if ($success) {
            $output->writeln("<info>Created model:</info> {$className}");
            $output->writeln("<comment>Table name:</comment> {$tableName}");

            // Optionally create migration
            if ($withMigration) {
                $migrationPath = config("paths.migrations");
                $timestamp = time();
                $fileName = sprintf("%s_create_%s.php", $timestamp, $tableName);
                $migrationFilePath = sprintf("%s/%s", $migrationPath, $fileName);

                $migrationSuccess = $this->generateFromStub(
                    'migration',
                    $migrationFilePath,
                    ['table' => $tableName],
                    $output
                );

                if ($migrationSuccess) {
                    $output->writeln("<info>Created migration:</info> {$fileName}");
                }
            }

            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }
}
