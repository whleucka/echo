<?php

namespace App\Http\Controllers\Admin;

use Echo\Framework\Admin\Schema\{FormSchemaBuilder, ModalSize, TableSchemaBuilder};
use Echo\Framework\Http\ModuleController;
use Echo\Framework\Routing\Group;

#[Group(pathPrefix: "/email-queue", namePrefix: "email-queue")]
class EmailQueueController extends ModuleController
{
    protected string $tableName = "email_jobs";

    protected function defineTable(TableSchemaBuilder $builder): void
    {
        $builder->defaultSort('id', 'DESC');
        $builder->dateColumn('created_at');

        $builder->column('id', 'ID');
        $builder->column('to_address', 'To')
            ->searchable();
        $builder->column('subject', 'Subject')
            ->searchable();
        $builder->column('status', 'Status')
            ->formatUsing(fn($col, $val) => $this->formatStatus($val));
        $builder->column('attempts', 'Attempts', "CONCAT(email_jobs.attempts, '/', email_jobs.max_attempts)");
        $builder->column('sent_at', 'Sent At');
        $builder->column('created_at', 'Created');

        $builder->filterLink('Pending', "status = 'pending'");
        $builder->filterLink('Sent', "status = 'sent'");
        $builder->filterLink('Failed', "status IN ('failed', 'exhausted')");

        $builder->rowAction('show');
        $builder->rowAction('delete');

        $builder->toolbarAction('export');

        $builder->bulkAction('delete', 'Delete');
    }

    protected function defineForm(FormSchemaBuilder $builder): void
    {
        $builder->modalSize(ModalSize::Large);

        $builder->field('to_address', 'To')
            ->input()
            ->readonly();

        $builder->field('subject', 'Subject')
            ->input()
            ->readonly();

        $builder->field('status', 'Status', "status")
            ->renderUsing(fn($col, $val) => $this->formatStatus($val));

        $builder->field('attempts', 'Attempts')
            ->input()
            ->readonly();

        $builder->field('max_attempts', 'Max Attempts')
            ->input()
            ->readonly();

        $builder->field('error_message', 'Error')
            ->textarea()
            ->readonly();

        $builder->field('scheduled_at', 'Scheduled At')
            ->input()
            ->readonly();

        $builder->field('last_attempt_at', 'Last Attempt')
            ->input()
            ->readonly();

        $builder->field('sent_at', 'Sent At')
            ->input()
            ->readonly();

        $builder->field('created_at', 'Created At')
            ->input()
            ->readonly();

        $builder->field('updated_at', 'Updated At')
            ->input()
            ->readonly();
    }

    private function formatStatus(?string $status): string
    {
        $badgeClass = match ($status) {
            'sent' => 'bg-success',
            'pending' => 'bg-secondary',
            'processing' => 'bg-info text-dark',
            'failed' => 'bg-warning text-dark',
            'exhausted' => 'bg-danger',
            default => 'bg-secondary',
        };

        return sprintf(
            '<span class="badge %s">%s</span>',
            $badgeClass,
            ucfirst($status ?? 'unknown')
        );
    }
}
