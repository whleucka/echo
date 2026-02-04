<?php

namespace App\Providers;

use Echo\Framework\Admin\WidgetRegistry;
use Echo\Framework\Admin\Widgets\StatsWidget;
use Echo\Framework\Admin\Widgets\ActivityFeedWidget;
use Echo\Framework\Admin\Widgets\SystemHealthWidget;
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
        // Register default widgets
        WidgetRegistry::register('stats', StatsWidget::class);
        WidgetRegistry::register('activity-feed', ActivityFeedWidget::class);
        WidgetRegistry::register('system-health', SystemHealthWidget::class);
    }

    /**
     * Bootstrap widget services
     */
    public function boot(): void
    {
        // Any widget initialization can go here
    }
}
