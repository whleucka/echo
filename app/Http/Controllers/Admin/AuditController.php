<?php

namespace App\Http\Controllers\Admin;

use App\Models\Audit;
use Echo\Framework\Http\AdminController;
use Echo\Framework\Routing\Group;
use Echo\Framework\Routing\Route\Get;

#[Group(path_prefix: "/audits", name_prefix: "audits")]
class AuditController extends AdminController
{
    public function __construct()
    {
        $this->has_create = false;
        $this->has_edit = false;
        $this->has_delete = false;

        $this->table_pk = "audits.id";

        $this->table_columns = [
            "ID" => "audits.id",
            "User" => "COALESCE(CONCAT(users.first_name, ' ', users.surname), 'System') as user_name",
            "Type" => "audits.auditable_type",
            "Record ID" => "audits.auditable_id",
            "Event" => "audits.event",
            "IP" => "audits.ip_address",
            "Created" => "audits.created_at",
        ];

        $this->table_joins = [
            "LEFT JOIN users ON users.id = audits.user_id"
        ];

        $this->table_format = [
            "auditable_type" => fn($col, $val) => $this->formatType($val),
            "event" => fn($col, $val) => $this->formatEvent($val),
        ];

        $this->filter_dropdowns = [
            "audits.event" => [
                ["value" => "created", "label" => "Created"],
                ["value" => "updated", "label" => "Updated"],
                ["value" => "deleted", "label" => "Deleted"],
            ],
            "users.id" => "SELECT id as value, CONCAT(first_name, ' ', surname) as label FROM users ORDER BY label",
        ];

        $this->filter_links = [
            "Created" => "audits.event = 'created'",
            "Updated" => "audits.event = 'updated'",
            "Deleted" => "audits.event = 'deleted'",
        ];

        $this->search_columns = [
            "Type",
            "IP",
            "User",
        ];

        $this->filter_date_column = "audits.created_at";

        parent::__construct("audits");
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
     * Format the auditable type to show only class name
     */
    private function formatType(?string $type): string
    {
        if (!$type) {
            return '';
        }
        if (str_contains($type, '\\')) {
            return substr(strrchr($type, '\\'), 1);
        }
        return $type;
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

    /**
     * Override table rendering to add diff link
     */
    protected function tableOverride(array $row): array
    {
        return $row;
    }

    /**
     * Override hasShow to enable viewing audit details
     */
    protected function hasShow(int $id): bool
    {
        return true;
    }

    /**
     * Override show to use diff view
     */
    #[Get("/modal/{id}", "show")]
    public function show(int $id): string
    {
        return $this->diff($id);
    }
}
