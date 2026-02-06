<?php

namespace Echo\Framework\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'db:cleanup', description: 'Delete old backups, keeping only the most recent N')]
class DbCleanupCommand extends Command
{
    use DbTrait;

    protected function configure(): void
    {
        $this->addArgument('keep', InputArgument::REQUIRED, 'Number of backups to keep');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $keep = (int) $input->getArgument('keep');

        if ($keep < 1) {
            $output->writeln("<error>✗ Keep value must be at least 1</error>");
            return Command::FAILURE;
        }

        $this->cleanupOldBackups($keep, $output);
        return Command::SUCCESS;
    }
}
