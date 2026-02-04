<?php

namespace Echo\Framework\Support;

use DI\Container;

/**
 * Base Service Provider
 *
 * Service providers are the central place to configure and bootstrap
 * application services. They provide a clean way to organize service
 * registration and initialization.
 */
abstract class ServiceProvider
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Register services with the container
     *
     * This method is called during the registration phase.
     * Use this to bind services, interfaces, and singletons.
     */
    abstract public function register(): void;

    /**
     * Bootstrap any application services
     *
     * This method is called after all providers have been registered.
     * Use this for any initialization that depends on other services.
     */
    public function boot(): void
    {
        // Override in child classes if needed
    }

    /**
     * Get the container instance
     */
    protected function container(): Container
    {
        return $this->container;
    }
}
