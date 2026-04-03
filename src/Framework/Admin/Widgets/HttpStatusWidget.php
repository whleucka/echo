<?php

namespace Echo\Framework\Admin\Widgets;

use App\Services\Admin\DashboardService;
use Echo\Framework\Admin\Widget;

class HttpStatusWidget extends Widget
{
    protected string $id = 'http-status';
    protected string $title = 'HTTP Status';
    protected string $icon = 'shield-check';
    protected string $template = 'admin/widgets/http-status.html.twig';
    protected int $width = 6;
    protected int $refreshInterval = 60;
    protected int $cacheTtl = 30;
    protected int $priority = 55;

    public function __construct(private DashboardService $dashboardService)
    {
    }

    public function getData(): array
    {
        return $this->dashboardService->getHttpStatusSummary();
    }
}
