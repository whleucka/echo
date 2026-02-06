<?php

namespace Echo\Framework\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'audit:list', description: 'List recent audit entries')]
class AuditListCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Number of entries to show', 20)
            ->addOption('event', 'e', InputOption::VALUE_OPTIONAL, 'Filter by event (created, updated, deleted)')
            ->addOption('model', 'm', InputOption::VALUE_OPTIONAL, 'Filter by model name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (int) $input->getOption('limit');
        $event = $input->getOption('event');
        $model = $input->getOption('model');

        $output->writeln("Recent Audit Entries:");
        $output->writeln(str_repeat('-', 100));

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
            $output->writeln("<error>Error fetching audits: " . $e->getMessage() . "</error>");
            return Command::FAILURE;
        }

        if (empty($audits)) {
            $output->writeln("  No audit entries found.");
            return Command::SUCCESS;
        }

        foreach ($audits as $audit) {
            $modelType = $this->getShortModelName($audit['auditable_type']);
            $user = $audit['user_email'] ?? 'System';
            $date = date('Y-m-d H:i:s', strtotime($audit['created_at']));

            $output->writeln(sprintf(
                "  #%-6d %-10s %-20s %-30s %s",
                $audit['id'],
                $this->formatEvent($audit['event']),
                $modelType . ' #' . $audit['auditable_id'],
                $user,
                $date
            ));

            if ($audit['event'] === 'updated' && $audit['old_values'] && $audit['new_values']) {
                $oldValues = json_decode($audit['old_values'], true) ?: [];
                $newValues = json_decode($audit['new_values'], true) ?: [];
                $changedFields = array_keys(array_diff_assoc($newValues, $oldValues));
                if (!empty($changedFields)) {
                    $output->writeln(sprintf("           Changed: %s", implode(', ', $changedFields)));
                }
            }
        }

        $output->writeln(str_repeat('-', 100));
        $output->writeln(sprintf("Showing %d entries", count($audits)));
        return Command::SUCCESS;
    }

    private function formatEvent(string $event): string
    {
        return match ($event) {
            'created' => '[CREATE]',
            'updated' => '[UPDATE]',
            'deleted' => '[DELETE]',
            default => "[$event]"
        };
    }

    private function getShortModelName(string $className): string
    {
        if (str_contains($className, '\\')) {
            return substr(strrchr($className, '\\'), 1);
        }
        return $className;
    }
}
