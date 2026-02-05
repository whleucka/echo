<?php

namespace Echo\Framework\Console\Commands;

/**
 * Audit management commands
 */
class Audit extends \ConsoleKit\Command
{
    /**
     * List recent audit entries
     *
     * Usage: ./bin/console audit list [--limit=20] [--event=created|updated|deleted] [--model=User]
     */
    public function executeList(array $args, array $options = []): void
    {
        $limit = isset($options['limit']) ? (int)$options['limit'] : 20;
        $event = $options['event'] ?? null;
        $model = $options['model'] ?? null;

        $this->writeln("Recent Audit Entries:");
        $this->writeln(str_repeat('-', 100));

        $query = "SELECT a.*, u.email as user_email
                  FROM audits a
                  LEFT JOIN users u ON a.user_id = u.id
                  WHERE 1=1";
        $params = [];

        if ($event) {
            $query .= " AND a.event = ?";
            $params[] = $event;
        }

        if ($model) {
            $query .= " AND a.auditable_type LIKE ?";
            $params[] = "%$model%";
        }

        $query .= " ORDER BY a.created_at DESC LIMIT ?";
        $params[] = $limit;

        try {
            $audits = db()->fetchAll($query, $params);
        } catch (\Exception $e) {
            $this->writeerr("Error fetching audits: " . $e->getMessage() . PHP_EOL);
            return;
        }

        if (empty($audits)) {
            $this->writeln("  No audit entries found.");
            return;
        }

        foreach ($audits as $audit) {
            $modelType = $this->getShortModelName($audit['auditable_type']);
            $user = $audit['user_email'] ?? 'System';
            $date = date('Y-m-d H:i:s', strtotime($audit['created_at']));

            $this->writeln(sprintf(
                "  #%-6d %-10s %-20s %-8s %-30s %s",
                $audit['id'],
                $this->formatEvent($audit['event']),
                $modelType . ' #' . $audit['auditable_id'],
                '',
                $user,
                $date
            ));

            // Show changes summary for updates
            if ($audit['event'] === 'updated' && $audit['old_values'] && $audit['new_values']) {
                $oldValues = json_decode($audit['old_values'], true) ?: [];
                $newValues = json_decode($audit['new_values'], true) ?: [];
                $changedFields = array_keys(array_diff_assoc($newValues, $oldValues));
                if (!empty($changedFields)) {
                    $this->writeln(sprintf("           Changed: %s", implode(', ', $changedFields)));
                }
            }
        }

        $this->writeln(str_repeat('-', 100));
        $this->writeln(sprintf("Showing %d of total audit entries", count($audits)));
        $this->writeln("");
        $this->writeln("Options:");
        $this->writeln("  --limit=N        Number of entries to show (default: 20)");
        $this->writeln("  --event=EVENT    Filter by event (created, updated, deleted)");
        $this->writeln("  --model=MODEL    Filter by model name");
    }

    /**
     * Show audit statistics
     *
     * Usage: ./bin/console audit stats
     */
    public function executeStats(array $args, array $options = []): void
    {
        $this->writeln("Audit Statistics:");
        $this->writeln(str_repeat('-', 60));

        try {
            // Total counts
            $total = db()->execute("SELECT COUNT(*) FROM audits")->fetchColumn();
            $this->writeln(sprintf("  Total entries: %s", number_format($total)));

            // By event type
            $byEvent = db()->fetchAll(
                "SELECT event, COUNT(*) as count FROM audits GROUP BY event ORDER BY count DESC"
            );
            $this->writeln("");
            $this->writeln("  By Event Type:");
            foreach ($byEvent as $row) {
                $this->writeln(sprintf("    %-10s %s", ucfirst($row['event']) . ':', number_format($row['count'])));
            }

            // By model type
            $byModel = db()->fetchAll(
                "SELECT auditable_type, COUNT(*) as count FROM audits GROUP BY auditable_type ORDER BY count DESC LIMIT 10"
            );
            $this->writeln("");
            $this->writeln("  By Model (top 10):");
            foreach ($byModel as $row) {
                $modelName = $this->getShortModelName($row['auditable_type']);
                $this->writeln(sprintf("    %-20s %s", $modelName . ':', number_format($row['count'])));
            }

            // Today's activity
            $today = db()->execute(
                "SELECT COUNT(*) FROM audits WHERE DATE(created_at) = CURDATE()"
            )->fetchColumn();
            $this->writeln("");
            $this->writeln(sprintf("  Today's entries: %s", number_format($today)));

            // This week
            $week = db()->execute(
                "SELECT COUNT(*) FROM audits WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
            )->fetchColumn();
            $this->writeln(sprintf("  Last 7 days: %s", number_format($week)));

        } catch (\Exception $e) {
            $this->writeerr("Error fetching statistics: " . $e->getMessage() . PHP_EOL);
        }

        $this->writeln(str_repeat('-', 60));
    }

    /**
     * Purge old audit entries
     *
     * Usage: ./bin/console audit purge [--days=90]
     */
    public function executePurge(array $args, array $options = []): void
    {
        $days = isset($options['days']) ? (int)$options['days'] : 90;

        if ($days < 7) {
            $this->writeerr("Days must be at least 7 to prevent accidental data loss." . PHP_EOL);
            return;
        }

        $this->writeln(sprintf("Purging audit entries older than %d days...", $days));

        try {
            $count = db()->execute(
                "SELECT COUNT(*) FROM audits WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            )->fetchColumn();

            if ($count == 0) {
                $this->writeln("No entries to purge.");
                return;
            }

            $this->writeln(sprintf("Found %s entries to delete.", number_format($count)));
            $this->writeln("This action cannot be undone. Proceeding...");

            db()->execute(
                "DELETE FROM audits WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            );

            $this->writeln(sprintf("✓ Purged %s audit entries.", number_format($count)));

        } catch (\Exception $e) {
            $this->writeerr("Error purging audits: " . $e->getMessage() . PHP_EOL);
        }
    }

    /**
     * Format event name for display
     */
    private function formatEvent(string $event): string
    {
        return match ($event) {
            'created' => '[CREATE]',
            'updated' => '[UPDATE]',
            'deleted' => '[DELETE]',
            default => "[$event]"
        };
    }

    /**
     * Get short model name from full class path
     */
    private function getShortModelName(string $className): string
    {
        if (str_contains($className, '\\')) {
            return substr(strrchr($className, '\\'), 1);
        }
        return $className;
    }
}
