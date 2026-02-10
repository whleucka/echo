<?php

namespace Echo\Framework\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'make:migration', description: 'Create a new migration file')]
class MakeMigrationCommand extends Command
{
    use MakeCommandTrait;

    protected function configure(): void
    {
        $this->addArgument('table', InputArgument::REQUIRED, 'Table name for the migration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $table = $input->getArgument('table');
        $migrationPath = config("paths.migrations");
        $timestamp = time();
        $fileName = sprintf("%s_create_%s.php", $timestamp, $table);
        $filePath = sprintf("%s/%s", $migrationPath, $fileName);

        $success = $this->generateFromStub(
            'migration',
            $filePath,
            ['table' => $table],
            $output
        );

        if ($success) {
            $output->writeln("<info>Created migration:</info> {$fileName}");
            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }
}
