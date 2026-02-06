<?php

namespace App\Console;

use Echo\Framework\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    // see: https://github.com/maximebf/ConsoleKit
    protected array $commands = [
        'version' => \Echo\Framework\Console\Commands\Version::class,
        'migrate' => \Echo\Framework\Console\Commands\Migrate::class,
        'server' => \Echo\Framework\Console\Commands\Server::class,
        'admin' => \Echo\Framework\Console\Commands\Admin::class,
        'route' => \Echo\Framework\Console\Commands\Route::class,
        'audit' => \Echo\Framework\Console\Commands\Audit::class,
        'session' => \Echo\Framework\Console\Commands\Session::class,
        'storage' => \Echo\Framework\Console\Commands\Storage::class,
    ];
}
