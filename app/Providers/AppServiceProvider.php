<?php

namespace App\Providers;

use Echo\Framework\Support\ServiceProvider;
use Echo\Framework\View\TwigExtension;

/**
 * Application Service Provider
 *
 * Register and bootstrap core application services.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register application services
     */
    public function register(): void
    {
        // Register application-specific services here
    }

    /**
     * Bootstrap application services
     */
    public function boot(): void
    {
        // Add Twig extensions
        $twig = $this->container->get(\Twig\Environment::class);

        // Add debug extension in debug mode
        if (config('app.debug')) {
            $twig->addExtension(new \Twig\Extension\DebugExtension());
        }

        // Add custom Echo extension
        $twig->addExtension(new TwigExtension());
    }
}
