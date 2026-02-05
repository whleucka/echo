<?php

namespace Echo\Framework\Console\Commands;

/**
 * Session management commands
 */
class Session extends \ConsoleKit\Command
{
    /**
     * Clean up old session records
     *
     * Usage: ./bin/console session cleanup [--days=30]
     */
    public function executeCleanup(array $args, array $options = []): void
    {
        $days = isset($options['days']) ? (int)$options['days'] : 30;

        if ($days < 1) {
            $this->writeerr("Days must be at least 1." . PHP_EOL);
            return;
        }

        $this->writeln(sprintf("Cleaning up sessions older than %d days...", $days));

        try {
            // Count records to delete
            $count = db()->execute(
                "SELECT COUNT(*) FROM sessions WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            )->fetchColumn();

            if ($count == 0) {
                $this->writeln("No old sessions to clean up.");
                return;
            }

            $this->writeln(sprintf("Found %s sessions to delete.", number_format($count)));

            // Delete old sessions
            db()->execute(
                "DELETE FROM sessions WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            );

            $this->writeln(sprintf("✓ Deleted %s old session records.", number_format($count)));

        } catch (\Exception $e) {
            $this->writeerr("Error cleaning sessions: " . $e->getMessage() . PHP_EOL);
        }
    }

    /**
     * Show session statistics
     *
     * Usage: ./bin/console session stats
     */
    public function executeStats(array $args, array $options = []): void
    {
        $this->writeln("Session Statistics:");
        $this->writeln(str_repeat('-', 60));

        try {
            // Total sessions
            $total = db()->execute("SELECT COUNT(*) FROM sessions")->fetchColumn();
            $this->writeln(sprintf("  Total sessions: %s", number_format($total)));

            // Today's sessions
            $today = db()->execute(
                "SELECT COUNT(*) FROM sessions WHERE DATE(created_at) = CURDATE()"
            )->fetchColumn();
            $this->writeln(sprintf("  Today: %s", number_format($today)));

            // Active users (last 30 min)
            $active = db()->execute(
                "SELECT COUNT(DISTINCT user_id) FROM sessions WHERE created_at >= NOW() - INTERVAL 30 MINUTE AND user_id IS NOT NULL"
            )->fetchColumn();
            $this->writeln(sprintf("  Active users (30 min): %s", number_format($active)));

            // Sessions by age
            $this->writeln("");
            $this->writeln("  By Age:");

            $last7 = db()->execute(
                "SELECT COUNT(*) FROM sessions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
            )->fetchColumn();
            $this->writeln(sprintf("    Last 7 days: %s", number_format($last7)));

            $last30 = db()->execute(
                "SELECT COUNT(*) FROM sessions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
            )->fetchColumn();
            $this->writeln(sprintf("    Last 30 days: %s", number_format($last30)));

            $older = $total - $last30;
            $this->writeln(sprintf("    Older than 30 days: %s", number_format($older)));

            // Table size
            $size = db()->fetch(
                "SELECT ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = 'sessions'"
            );
            if ($size) {
                $this->writeln("");
                $this->writeln(sprintf("  Table size: %s MB", $size['size_mb'] ?? '0'));
            }

        } catch (\Exception $e) {
            $this->writeerr("Error fetching statistics: " . $e->getMessage() . PHP_EOL);
        }

        $this->writeln(str_repeat('-', 60));
    }
}
