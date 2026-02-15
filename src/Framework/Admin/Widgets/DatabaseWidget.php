<?php

namespace Echo\Framework\Admin\Widgets;

use App\Services\Admin\DashboardService;
use Echo\Framework\Admin\Widget;

class DatabaseWidget extends Widget
{
    protected string $id = 'database';
    protected string $title = 'Database Stats';
    protected string $icon = 'database';
    protected string $template = 'admin/widgets/database.html.twig';
    protected int $width = 6;
    protected int $refreshInterval = 120;
    protected int $cacheTtl = 60;
    protected int $priority = 80;

    public function __construct(private DashboardService $dashboardService)
    {
    }

    public function getData(): array
    {
        return $this->dashboardService->getDatabaseStats();
    }
}
