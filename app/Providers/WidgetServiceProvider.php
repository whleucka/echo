<?php

namespace App\Providers;

use Echo\Framework\Admin\WidgetRegistry;
use Echo\Framework\Admin\Widgets\ActivityHeatmapWidget;
use Echo\Framework\Admin\Widgets\AuditSummaryWidget;
use Echo\Framework\Admin\Widgets\DatabaseWidget;
use Echo\Framework\Admin\Widgets\EmailQueueWidget;
use Echo\Framework\Admin\Widgets\RedisWidget;
use Echo\Framework\Admin\Widgets\StatsWidget;
use Echo\Framework\Admin\Widgets\SystemHealthWidget;
use Echo\Framework\Admin\Widgets\UsersWidget;
use Echo\Framework\Support\ServiceProvider;

/**
 * Widget Service Provider
 *
 * Register dashboard widgets with the widget registry.
 */
class WidgetServiceProvider extends ServiceProvider
{
    /**
     * Register widgets
     */
    public function register(): void
    {
        // Register dashboard widgets (ordered by priority in each widget class)
        WidgetRegistry::register('activity-heatmap', ActivityHeatmapWidget::class);
        WidgetRegistry::register('stats', StatsWidget::class);
        WidgetRegistry::register('system-health', SystemHealthWidget::class);
        WidgetRegistry::register('redis', RedisWidget::class);
        WidgetRegistry::register('database', DatabaseWidget::class);
        WidgetRegistry::register('email-queue', EmailQueueWidget::class);
        WidgetRegistry::register('audit-summary', AuditSummaryWidget::class);
        WidgetRegistry::register('users', UsersWidget::class);
    }

    /**
     * Bootstrap widget services
     */
    public function boot(): void
    {
        // Any widget initialization can go here
    }
}
