<?php

namespace Echo\Framework\Console\Commands;

use App\Models\Session;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'session:stats', description: 'Show session statistics')]
class SessionStatsCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln("Session Statistics:");
        $output->writeln(str_repeat('-', 60));

        try {
            $total = Session::countAll();
            $output->writeln(sprintf("  Total sessions: %s", number_format($total)));

            $today = Session::where('id', '>', '0')
                ->whereRaw("DATE(created_at) = CURDATE()")
                ->count();
            $output->writeln(sprintf("  Today: %s", number_format($today)));

            $active = Session::where('id', '>', '0')
                ->whereRaw("created_at >= NOW() - INTERVAL 30 MINUTE")
                ->whereNotNull('user_id')
                ->count('DISTINCT user_id');
            $output->writeln(sprintf("  Active users (30 min): %s", number_format($active)));

            $output->writeln("");
            $output->writeln("  By Age:");

            $last7 = Session::where('id', '>', '0')
                ->whereRaw("created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")
                ->count();
            $output->writeln(sprintf("    Last 7 days: %s", number_format($last7)));

            $last30 = Session::where('id', '>', '0')
                ->whereRaw("created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")
                ->count();
            $output->writeln(sprintf("    Last 30 days: %s", number_format($last30)));

            $older = $total - $last30;
            $output->writeln(sprintf("    Older than 30 days: %s", number_format($older)));

            // Table size query stays raw (information_schema query)
            $size = db()->fetch(
                "SELECT ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = 'sessions'"
            );
            if ($size) {
                $output->writeln("");
                $output->writeln(sprintf("  Table size: %s MB", $size['size_mb'] ?? '0'));
            }

        } catch (\Exception $e) {
            $output->writeln("<error>Error fetching statistics: " . $e->getMessage() . "</error>");
            return Command::FAILURE;
        }

        $output->writeln(str_repeat('-', 60));
        return Command::SUCCESS;
    }
}
