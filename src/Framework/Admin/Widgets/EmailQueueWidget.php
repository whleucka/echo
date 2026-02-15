<?php

namespace Echo\Framework\Admin\Widgets;

use App\Services\Admin\DashboardService;
use Echo\Framework\Admin\Widget;

class EmailQueueWidget extends Widget
{
    protected string $id = 'email-queue';
    protected string $title = 'Email Queue';
    protected string $icon = 'envelope';
    protected string $template = 'admin/widgets/email-queue.html.twig';
    protected int $width = 6;
    protected int $refreshInterval = 60;
    protected int $cacheTtl = 30;
    protected int $priority = 60;

    public function __construct(private DashboardService $dashboardService)
    {
    }

    public function getData(): array
    {
        return $this->dashboardService->getEmailQueueData();
    }
}
