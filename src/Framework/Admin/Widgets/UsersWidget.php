<?php

namespace Echo\Framework\Admin\Widgets;

use App\Services\Admin\DashboardService;
use Echo\Framework\Admin\Widget;

class UsersWidget extends Widget
{
    protected string $id = 'users';
    protected string $title = 'Users';
    protected string $icon = 'people';
    protected string $template = 'admin/widgets/users.html.twig';
    protected int $width = 6;
    protected int $refreshInterval = 120;
    protected int $cacheTtl = 60;
    protected int $priority = 40;

    public function __construct(private DashboardService $dashboardService)
    {
    }

    public function getData(): array
    {
        return $this->dashboardService->getUsersWidgetData();
    }
}
