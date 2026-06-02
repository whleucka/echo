<?php

namespace App;

use Echo\Framework\ApplicationInterface;
use Echo\Framework\Console\KernelInterface as ConsoleKernelInterface;
use Echo\Framework\Http\KernelInterface as HttpKernelInterface;
use Echo\Framework\Http\Request;
use Echo\Framework\Support\ServiceProviderRegistry;
use Dotenv;

class Application implements ApplicationInterface
{
    private ServiceProviderRegistry $providers;

    public function __construct(private ConsoleKernelInterface|HttpKernelInterface $kernel)
    {
        $dotenv = Dotenv\Dotenv::createImmutable(config("paths.root"));
        $dotenv->safeLoad();

        // Run the whole app in the configured timezone so PHP date handling
        // agrees with the database (the db container runs in the same TZ).
        date_default_timezone_set(config("app.timezone") ?? "UTC");

        // Register and boot service providers
        $this->bootProviders();
    }

    public function run(): void
    {
        // Run the application (web or cli)
        if ($this->kernel instanceof HttpKernelInterface) {
            // Handle a web request — kernel returns a response, we send it here
            $request = container()->get(Request::class);
            $this->kernel->handle($request)->send();
            exit;
        } elseif ($this->kernel instanceof ConsoleKernelInterface) {
            // Run a command in cli mode
            $this->kernel->handle();
        }
    }

    /**
     * Register and boot all configured service providers
     */
    private function bootProviders(): void
    {
        $this->providers = new ServiceProviderRegistry(container());

        // Load providers from config
        $providers = config('providers') ?? [];
        $this->providers->registerMany($providers);

        // Boot all providers
        $this->providers->boot();
    }

    /**
     * Get the service provider registry
     */
    public function getProviders(): ServiceProviderRegistry
    {
        return $this->providers;
    }
}
