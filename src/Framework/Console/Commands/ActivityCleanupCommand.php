<?php

namespace Echo\Framework\Console\Commands;

use App\Models\Activity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'activity:cleanup', description: 'Clean up old activity records')]
class ActivityCleanupCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('days', 'd', InputOption::VALUE_OPTIONAL, 'Delete activity older than N days', 30);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = (int) $input->getOption('days');

        if ($days < 1) {
            $output->writeln("<error>Days must be at least 1.</error>");
            return Command::FAILURE;
        }

        $output->writeln(sprintf("Cleaning up activity older than %d days...", $days));

        try {
            $count = Activity::where('id', '>', '0')
                ->whereRaw("created_at < DATE_SUB(NOW(), INTERVAL ? DAY)", [$days])
                ->count();

            if ($count == 0) {
                $output->writeln("No old activity to clean up.");
                return Command::SUCCESS;
            }

            $output->writeln(sprintf("Found %s activity records to delete.", number_format($count)));

            // Bulk delete still uses raw SQL for efficiency
            db()->execute(
                "DELETE FROM activity WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            );

            $output->writeln(sprintf("<info>✓ Deleted %s old activity records.</info>", number_format($count)));
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln("<error>Error cleaning activity: " . $e->getMessage() . "</error>");
            return Command::FAILURE;
        }
    }
}
