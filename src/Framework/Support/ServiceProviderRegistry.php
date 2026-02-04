<?php

namespace Echo\Framework\Support;

use DI\Container;

/**
 * Service Provider Registry
 *
 * Manages the registration and booting of service providers.
 */
class ServiceProviderRegistry
{
    private Container $container;
    private array $providers = [];
    private array $registered = [];
    private bool $booted = false;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Register a service provider
     */
    public function register(string|ServiceProvider $provider): self
    {
        if (is_string($provider)) {
            $provider = new $provider($this->container);
        }

        $class = get_class($provider);

        // Don't register twice
        if (isset($this->registered[$class])) {
            return $this;
        }

        $this->providers[] = $provider;
        $this->registered[$class] = true;

        // Call register method
        $provider->register();

        // If already booted, boot this provider immediately
        if ($this->booted) {
            $provider->boot();
        }

        return $this;
    }

    /**
     * Register multiple providers at once
     */
    public function registerMany(array $providers): self
    {
        foreach ($providers as $provider) {
            $this->register($provider);
        }

        return $this;
    }

    /**
     * Boot all registered providers
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->providers as $provider) {
            $provider->boot();
        }

        $this->booted = true;
    }

    /**
     * Check if providers have been booted
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Get all registered providers
     */
    public function getProviders(): array
    {
        return $this->providers;
    }
}
