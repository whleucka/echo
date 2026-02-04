<?php

namespace App\Providers;

use Echo\Framework\Support\ServiceProvider;

/**
 * Database Service Provider
 *
 * Register and bootstrap database services.
 */
class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * Register database services
     */
    public function register(): void
    {
        // Database bindings are handled in config/container.php
        // This provider can be used for additional database setup
    }

    /**
     * Bootstrap database services
     */
    public function boot(): void
    {
        // Any database initialization can go here
        // For example: setting up event listeners, query logging, etc.
    }
}
