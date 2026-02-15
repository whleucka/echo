<?php

namespace Echo\Framework\Admin\Widgets;

use App\Services\Admin\DashboardService;
use Echo\Framework\Admin\Widget;

class StatsWidget extends Widget
{
    protected string $id = 'stats';
    protected string $title = 'Quick Stats';
    protected string $icon = 'bar-chart';
    protected string $template = 'admin/widgets/stats.html.twig';
    protected int $width = 12;
    protected int $refreshInterval = 60;
    protected int $priority = 10;

    public function __construct(private DashboardService $dashboardService)
    {
    }

    public function getData(): array
    {
        $usersCount = $this->dashboardService->getUsersCount();

        $activeUsers = $this->dashboardService->getActiveUsersCount();

        $todayRequests = $this->dashboardService->getTodayRequests();

        $totalRequests = $this->dashboardService->getTotalRequests();

        $modulesCount = $this->dashboardService->getModulesCount();

        $auditData = $this->dashboardService->getAuditSummary();
        $auditCount = $auditData['today'];

        return [
            'stats' => [
                [
                    'label' => 'Total Users',
                    'value' => (int)$usersCount,
                    'icon' => 'people',
                    'color' => 'primary',
                ],
                [
                    'label' => 'Active Now',
                    'value' => (int)$activeUsers,
                    'icon' => 'person-check',
                    'color' => 'success',
                ],
                [
                    'label' => "Today's Requests",
                    'value' => (int)$todayRequests,
                    'icon' => 'lightning',
                    'color' => 'warning',
                ],
                [
                    'label' => 'Total Requests',
                    'value' => (int)$totalRequests,
                    'icon' => 'graph-up',
                    'color' => 'info',
                ],
                [
                    'label' => 'Modules',
                    'value' => (int)$modulesCount,
                    'icon' => 'puzzle',
                    'color' => 'secondary',
                ],
                [
                    'label' => "Today's Changes",
                    'value' => (int)$auditCount,
                    'icon' => 'journal-text',
                    'color' => 'danger',
                ],
            ],
        ];
    }
}
