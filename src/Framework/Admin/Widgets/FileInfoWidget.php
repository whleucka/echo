<?php

namespace Echo\Framework\Admin\Widgets;

use App\Services\Admin\DashboardService;
use Echo\Framework\Admin\Widget;

class FileInfoWidget extends Widget
{
    protected string $id = 'file-info';
    protected string $title = 'File Uploads (Today)';
    protected string $icon = 'file-earmark-arrow-up';
    protected string $template = 'admin/widgets/file-info.html.twig';
    protected int $width = 6;
    protected int $refreshInterval = 60;
    protected int $cacheTtl = 30;
    protected int $priority = 101; // Right after Activity Heatmap (100)

    public function __construct(private DashboardService $dashboardService)
    {
    }

    public function getData(): array
    {
        return $this->dashboardService->getFileInfoStats();
    }
}
