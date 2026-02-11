<?php

namespace Echo\Framework\Console\Commands;

use App\Models\Audit;
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

        try {
            // Build query using ORM
            $query = Audit::where('id', '>', '0');

            if ($event) {
                $query = $query->andWhere('event', $event);
            }

            if ($model) {
                $query = $query->andWhere('auditable_type', 'LIKE', "%$model%");
            }

            $result = $query->orderBy('created_at', 'DESC')->get($limit);

            // Normalize to array
            if (is_null($result)) {
                $audits = [];
            } else {
                $audits = is_array($result) ? $result : [$result];
            }
        } catch (\Exception $e) {
            $output->writeln("<error>Error fetching audits: " . $e->getMessage() . "</error>");
            return Command::FAILURE;
        }

        if (empty($audits)) {
            $output->writeln("  No audit entries found.");
            return Command::SUCCESS;
        }

        foreach ($audits as $audit) {
            $user = $audit->user();
            $userEmail = $user ? $user->email : 'System';
            $date = date('Y-m-d H:i:s', strtotime($audit->created_at));

            $output->writeln(sprintf(
                "  #%-6d %-10s %-20s %-30s %s",
                $audit->id,
                $this->formatEvent($audit->event),
                $audit->auditable_type . ' #' . $audit->auditable_id,
                $userEmail,
                $date
            ));

            if ($audit->event === 'updated' && $audit->old_values && $audit->new_values) {
                $oldValues = json_decode($audit->old_values, true) ?: [];
                $newValues = json_decode($audit->new_values, true) ?: [];
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
}
