<?php

namespace Echo\Framework\Console\Commands;

use Echo\Framework\Database\QueryBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'mail:purge', description: 'Purge old sent/exhausted email jobs')]
class MailPurgeCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('days', 'd', InputOption::VALUE_OPTIONAL, 'Delete jobs older than N days', 30);
        $this->addOption('status', 's', InputOption::VALUE_OPTIONAL, 'Only purge this status (sent, exhausted)', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = (int) $input->getOption('days');
        $status = $input->getOption('status');

        try {
            $where = ['created_at < DATE_SUB(NOW(), INTERVAL ? DAY)'];
            $params = [$days];

            if ($status) {
                $where[] = 'status = ?';
                $params[] = $status;
            } else {
                $where[] = "status IN ('sent', 'exhausted')";
            }

            $stmt = QueryBuilder::delete()
                ->from('email_jobs')
                ->where($where, ...$params)
                ->execute();

            $count = $stmt->rowCount();
            $output->writeln(sprintf('<info>Purged %d email job(s) older than %d days.</info>', $count, $days));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
