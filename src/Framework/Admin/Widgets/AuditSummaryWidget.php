<?php

namespace Echo\Framework\Admin\Widgets;

use App\Services\Admin\DashboardService;
use Echo\Framework\Admin\Widget;

class AuditSummaryWidget extends Widget
{
    protected string $id = 'audit-summary';
    protected string $title = 'Audit Activity (7 days)';
    protected string $icon = 'journal-text';
    protected string $template = 'admin/widgets/audit-summary.html.twig';
    protected int $width = 6;
    protected int $refreshInterval = 60;
    protected int $cacheTtl = 30;
    protected int $priority = 90;

    public function __construct(private DashboardService $dashboardService)
    {
    }

    public function getData(): array
    {
        return $this->dashboardService->getAuditSummary();
    }
}
