<?php

namespace Echo\Framework\Admin\Widgets;

use Echo\Framework\Admin\Widget;

class StatsWidget extends Widget
{
    protected string $id = 'stats';
    protected string $title = 'Quick Stats';
    protected string $icon = 'bar-chart';
    protected string $template = 'admin/widgets/stats.html.twig';
    protected int $width = 12;
    protected int $refreshInterval = 60;

    public function getData(): array
    {
        $usersCount = db()->execute(
            "SELECT COUNT(*) FROM users"
        )->fetchColumn();

        $activeUsers = db()->execute(
            "SELECT COUNT(DISTINCT user_id) FROM sessions
            WHERE created_at >= NOW() - INTERVAL 30 MINUTE"
        )->fetchColumn();

        $todayRequests = db()->execute(
            "SELECT COUNT(*) FROM sessions WHERE DATE(created_at) = CURDATE()"
        )->fetchColumn();

        $totalRequests = db()->execute(
            "SELECT COUNT(*) FROM sessions"
        )->fetchColumn();

        $modulesCount = db()->execute(
            "SELECT COUNT(*) FROM modules WHERE parent_id IS NOT NULL"
        )->fetchColumn();

        $auditCount = db()->execute(
            "SELECT COUNT(*) FROM audits WHERE DATE(created_at) = CURDATE()"
        )->fetchColumn();

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
