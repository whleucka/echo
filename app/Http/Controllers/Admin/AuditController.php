<?php

namespace App\Http\Controllers\Admin;

use App\Models\Audit;
use Echo\Framework\Admin\Schema\TableSchemaBuilder;
use Echo\Framework\Http\ModuleController;
use Echo\Framework\Routing\Group;
use Echo\Framework\Routing\Route\Get;

#[Group(path_prefix: "/audits", name_prefix: "audits")]
class AuditController extends ModuleController
{
    public function __construct()
    {
        $this->has_create = $this->has_edit = $this->has_delete = false;
        parent::__construct('audits');
    }

    protected function defineTable(TableSchemaBuilder $builder): void
    {
        $builder->primaryKey('audits.id')
                ->join('LEFT JOIN users ON users.id = audits.user_id')
                ->dateColumn('audits.created_at')
                ->defaultSort('audits.id', 'DESC');

        $builder->column('id', 'ID', 'audits.id')->sortable();
        $builder->column('user_name', 'User', "COALESCE(CONCAT(users.first_name, ' ', users.surname), 'System')")
                ->searchable();
        $builder->column('auditable_type', 'Type', 'audits.auditable_type')
                ->searchable()
                ->formatUsing(fn($col, $val) => $this->formatType($val));
        $builder->column('auditable_id', 'Record ID', 'audits.auditable_id');
        $builder->column('event', 'Event', 'audits.event')
                ->sortable()
                ->formatUsing(fn($col, $val) => $this->formatEvent($val));
        $builder->column('ip_address', 'IP', 'audits.ip_address')->searchable();
        $builder->column('created_at', 'Created', 'audits.created_at')->sortable();

        $builder->filter('event', 'audits.event')
                ->label('Event')
                ->options([
                    ['value' => 'created', 'label' => 'Created'],
                    ['value' => 'updated', 'label' => 'Updated'],
                    ['value' => 'deleted', 'label' => 'Deleted'],
                ]);

        $builder->filter('user', 'audits.user_id')
                ->label('User')
                ->optionsFrom("SELECT id as value, CONCAT(first_name, ' ', surname) as label FROM users ORDER BY label");

        $builder->filterLink('Created', "audits.event = 'created'");
        $builder->filterLink('Updated', "audits.event = 'updated'");
        $builder->filterLink('Deleted', "audits.event = 'deleted'");
    }

    /**
     * Show diff view for an audit record
     */
    #[Get("/diff/{id}", "diff", ["max_requests" => 0])]
    public function diff(int $id): string
    {
        $audit = Audit::find($id);

        if (!$audit) {
            return $this->render("admin/audit/diff.html.twig", [
                "error" => "Audit record not found",
            ]);
        }

        $user = $audit->user();

        return $this->render("admin/audit/diff.html.twig", [
            "audit" => [
                "id" => $audit->id,
                "event" => $audit->event,
                "auditable_type" => $this->formatType($audit->auditable_type),
                "auditable_id" => $audit->auditable_id,
                "user" => $user ? $user->fullName() : 'System',
                "ip_address" => $audit->ip_address,
                "user_agent" => $audit->user_agent,
                "created_at" => $audit->created_at,
            ],
            "changes" => $audit->getChanges(),
            "old_values" => $audit->getOldValues(),
            "new_values" => $audit->getNewValues(),
        ]);
    }

    /**
     * Format the auditable type (table name)
     */
    private function formatType(?string $type): string
    {
        return $type ?? '';
    }

    /**
     * Format the event with a badge
     */
    private function formatEvent(?string $event): string
    {
        $badgeClass = match ($event) {
            'created' => 'bg-success',
            'updated' => 'bg-warning text-dark',
            'deleted' => 'bg-danger',
            default => 'bg-secondary',
        };

        return sprintf(
            '<span class="badge %s">%s</span>',
            $badgeClass,
            ucfirst($event ?? 'unknown')
        );
    }

    protected function hasShow(int $id): bool
    {
        return true;
    }

    #[Get("/modal/{id}", "show")]
    public function show(int $id): string
    {
        return $this->diff($id);
    }
}
