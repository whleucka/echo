<?php

namespace App;

use Echo\Framework\Http\Request;
use Echo\Framework\Support\ServiceProviderRegistry;
use Echo\Interface\Console\Kernel as ConsoleKernel;
use Echo\Interface\Application as EchoApplication;
use Echo\Interface\Http\Kernel as HttpKernel;
use Dotenv;

class Application implements EchoApplication
{
    private ServiceProviderRegistry $providers;

    public function __construct(private ConsoleKernel|HttpKernel $kernel)
    {
        $dotenv = Dotenv\Dotenv::createImmutable(config("paths.root"));
        $dotenv->safeLoad();

        // Register and boot service providers
        $this->bootProviders();
    }

    public function run(): void
    {
        // Run the application (web or cli)
        if ($this->kernel instanceof HttpKernel) {
            // Handle a web request
            $request = container()->get(Request::class);
            $this->kernel->handle($request);
        } elseif ($this->kernel instanceof ConsoleKernel) {
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
