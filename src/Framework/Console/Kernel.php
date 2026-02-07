<?php

namespace Echo\Framework\Console;

use Echo\Interface\Console\Kernel as ConsoleKernel;
use Symfony\Component\Console\Application;

class Kernel implements ConsoleKernel
{
    protected array $commands = [];
    protected Application $app;

    public function __construct()
    {
        $this->app = new Application(
            config('app.name') ?? 'Echo',
            config('framework.version') ?? 'v0.0.1'
        );
    }

    public function handle(): void
    {
        error_reporting(E_ERROR | E_WARNING | E_PARSE);
        
        // Register all commands
        foreach ($this->commands as $command) {
            $this->app->addCommand(new $command());
        }

        $this->app->run();
    }
}
