<?php

namespace App\Providers;

use Echo\Framework\Admin\WidgetRegistry;
use Echo\Framework\Admin\Widgets\StatsWidget;
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
    }

    /**
     * Bootstrap widget services
     */
    public function boot(): void
    {
        // Any widget initialization can go here
    }
}
