<?php

namespace Echo\Framework\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'session:cleanup', description: 'Clean up old session records')]
class SessionCleanupCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('days', 'd', InputOption::VALUE_OPTIONAL, 'Delete sessions older than N days', 30);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = (int) $input->getOption('days');

        if ($days < 1) {
            $output->writeln("<error>Days must be at least 1.</error>");
            return Command::FAILURE;
        }

        $output->writeln(sprintf("Cleaning up sessions older than %d days...", $days));

        try {
            $count = db()->execute(
                "SELECT COUNT(*) FROM sessions WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            )->fetchColumn();

            if ($count == 0) {
                $output->writeln("No old sessions to clean up.");
                return Command::SUCCESS;
            }

            $output->writeln(sprintf("Found %s sessions to delete.", number_format($count)));

            db()->execute(
                "DELETE FROM sessions WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            );

            $output->writeln(sprintf("<info>✓ Deleted %s old session records.</info>", number_format($count)));
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln("<error>Error cleaning sessions: " . $e->getMessage() . "</error>");
            return Command::FAILURE;
        }
    }
}
