<?php

namespace Echo\Framework\Admin\Widgets;

use App\Services\Admin\DashboardService;
use Echo\Framework\Admin\Widget;

class ActivityHeatmapWidget extends Widget
{
    protected string $id = 'activity-heatmap';
    protected string $title = 'Activity Heatmap (7 days)';
    protected string $icon = 'grid-3x3';
    protected string $template = 'admin/widgets/activity-heatmap.html.twig';
    protected int $width = 6;
    protected int $refreshInterval = 300; // 5 minutes
    protected int $cacheTtl = 60;
    protected int $priority = 100;

    public function __construct(private DashboardService $dashboardService)
    {
    }

    public function getData(): array
    {
        return $this->dashboardService->getUserActivityHeatmap();
    }
}
