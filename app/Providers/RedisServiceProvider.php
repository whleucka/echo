<?php

namespace App\Providers;

use Echo\Framework\Redis\RedisManager;
use Echo\Framework\Support\ServiceProvider;

/**
 * Redis Service Provider
 *
 * Registers Redis manager and related services.
 */
class RedisServiceProvider extends ServiceProvider
{
    /**
     * Register Redis services
     */
    public function register(): void
    {
        // Register Redis Manager as singleton
        $this->container->set(RedisManager::class, function () {
            return RedisManager::getInstance();
        });
    }

    /**
     * Bootstrap Redis services
     */
    public function boot(): void
    {
        // Nothing to boot
    }
}
