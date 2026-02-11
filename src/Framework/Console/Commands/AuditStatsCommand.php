<?php

namespace Echo\Framework\Console\Commands;

use App\Models\Audit;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'audit:stats', description: 'Show audit statistics')]
class AuditStatsCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln("Audit Statistics:");
        $output->writeln(str_repeat('-', 60));

        try {
            $total = Audit::countAll();
            $output->writeln(sprintf("  Total entries: %s", number_format($total)));

            // GROUP BY using ORM
            $byEvent = Audit::where('id', '>', '0')
                ->select(['event', 'COUNT(*) as count'])
                ->groupBy('event')
                ->orderBy('count', 'DESC')
                ->getRaw();
            $output->writeln("");
            $output->writeln("  By Event Type:");
            foreach ($byEvent as $row) {
                $output->writeln(sprintf("    %-10s %s", ucfirst($row['event']) . ':', number_format($row['count'])));
            }

            $byModel = Audit::where('id', '>', '0')
                ->select(['auditable_type', 'COUNT(*) as count'])
                ->groupBy('auditable_type')
                ->orderBy('count', 'DESC')
                ->getRaw(10);
            $output->writeln("");
            $output->writeln("  By Table (top 10):");
            foreach ($byModel as $row) {
                $output->writeln(sprintf("    %-20s %s", $row['auditable_type'] . ':', number_format($row['count'])));
            }

            $today = Audit::where('id', '>', '0')
                ->whereRaw("DATE(created_at) = CURDATE()")
                ->count();
            $output->writeln("");
            $output->writeln(sprintf("  Today's entries: %s", number_format($today)));

            $week = Audit::where('id', '>', '0')
                ->whereRaw("created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")
                ->count();
            $output->writeln(sprintf("  Last 7 days: %s", number_format($week)));

        } catch (\Exception $e) {
            $output->writeln("<error>Error fetching statistics: " . $e->getMessage() . "</error>");
            return Command::FAILURE;
        }

        $output->writeln(str_repeat('-', 60));
        return Command::SUCCESS;
    }
}
