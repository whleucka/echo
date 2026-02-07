<?php

namespace Echo\Framework\Console\Commands;

use Echo\Framework\Database\QueryBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'mail:status', description: 'Show email queue status')]
class MailStatusCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $rows = QueryBuilder::select(['status', 'COUNT(*) as total'])
                ->from('email_jobs')
                ->groupBy(['status'])
                ->execute()
                ->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($rows)) {
                $output->writeln('<info>No email jobs found.</info>');
                return Command::SUCCESS;
            }

            $output->writeln('<info>Email Queue Status:</info>');
            $output->writeln(str_repeat('-', 30));

            $grand = 0;
            foreach ($rows as $row) {
                $output->writeln(sprintf('  %-12s %d', $row['status'], $row['total']));
                $grand += $row['total'];
            }

            $output->writeln(str_repeat('-', 30));
            $output->writeln(sprintf('  %-12s %d', 'total', $grand));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
