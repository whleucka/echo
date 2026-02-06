<?php

namespace Echo\Framework\Console\Commands;

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
            $total = db()->execute("SELECT COUNT(*) FROM audits")->fetchColumn();
            $output->writeln(sprintf("  Total entries: %s", number_format($total)));

            $byEvent = db()->fetchAll(
                "SELECT event, COUNT(*) as count FROM audits GROUP BY event ORDER BY count DESC"
            );
            $output->writeln("");
            $output->writeln("  By Event Type:");
            foreach ($byEvent as $row) {
                $output->writeln(sprintf("    %-10s %s", ucfirst($row['event']) . ':', number_format($row['count'])));
            }

            $byModel = db()->fetchAll(
                "SELECT auditable_type, COUNT(*) as count FROM audits GROUP BY auditable_type ORDER BY count DESC LIMIT 10"
            );
            $output->writeln("");
            $output->writeln("  By Table (top 10):");
            foreach ($byModel as $row) {
                $output->writeln(sprintf("    %-20s %s", $row['auditable_type'] . ':', number_format($row['count'])));
            }

            $today = db()->execute(
                "SELECT COUNT(*) FROM audits WHERE DATE(created_at) = CURDATE()"
            )->fetchColumn();
            $output->writeln("");
            $output->writeln(sprintf("  Today's entries: %s", number_format($today)));

            $week = db()->execute(
                "SELECT COUNT(*) FROM audits WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
            )->fetchColumn();
            $output->writeln(sprintf("  Last 7 days: %s", number_format($week)));

        } catch (\Exception $e) {
            $output->writeln("<error>Error fetching statistics: " . $e->getMessage() . "</error>");
            return Command::FAILURE;
        }

        $output->writeln(str_repeat('-', 60));
        return Command::SUCCESS;
    }


}
