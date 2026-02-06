<?php

namespace Echo\Framework\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'migrate:status', description: 'Show migration status')]
class MigrateStatusCommand extends Command
{
    use MigrateTrait;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMigrations();
        $this->printStatus($output);
        return Command::SUCCESS;
    }
}
