<?php

namespace Echo\Framework\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(name: 'audit:purge', description: 'Purge old audit entries')]
class AuditPurgeCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('days', 'd', InputOption::VALUE_OPTIONAL, 'Delete entries older than N days', 90);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = (int) $input->getOption('days');

        if ($days < 7) {
            $output->writeln("<error>Days must be at least 7 to prevent accidental data loss.</error>");
            return Command::FAILURE;
        }

        $output->writeln(sprintf("Purging audit entries older than %d days...", $days));

        try {
            $count = db()->execute(
                "SELECT COUNT(*) FROM audits WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            )->fetchColumn();

            if ($count == 0) {
                $output->writeln("No entries to purge.");
                return Command::SUCCESS;
            }

            $output->writeln(sprintf("Found %s entries to delete.", number_format($count)));

            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('This action cannot be undone. Continue? [y/N] ', false);

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln("Operation cancelled.");
                return Command::SUCCESS;
            }

            db()->execute(
                "DELETE FROM audits WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            );

            $output->writeln(sprintf("<info>✓ Purged %s audit entries.</info>", number_format($count)));
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln("<error>Error purging audits: " . $e->getMessage() . "</error>");
            return Command::FAILURE;
        }
    }
}
