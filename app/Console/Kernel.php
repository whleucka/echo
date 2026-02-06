<?php

namespace App\Console;

use Echo\Framework\Console\Kernel as ConsoleKernel;
use Echo\Framework\Console\Commands;

class Kernel extends ConsoleKernel
{
    protected array $commands = [
        // General
        Commands\VersionCommand::class,
        Commands\ServerCommand::class,
        
        // Admin
        Commands\AdminNewCommand::class,
        
        // Storage
        Commands\StorageFixCommand::class,
        
        // Routes
        Commands\RouteCacheCommand::class,
        Commands\RouteClearCommand::class,
        Commands\RouteListCommand::class,
        
        // Migrations
        Commands\MigrateRunCommand::class,
        Commands\MigrateStatusCommand::class,
        Commands\MigrateFreshCommand::class,
        Commands\MigrateRollbackCommand::class,
        Commands\MigrateUpCommand::class,
        Commands\MigrateDownCommand::class,
        Commands\MigrateCreateCommand::class,
        
        // Sessions
        Commands\SessionCleanupCommand::class,
        Commands\SessionStatsCommand::class,
        
        // Audits
        Commands\AuditListCommand::class,
        Commands\AuditStatsCommand::class,
        Commands\AuditPurgeCommand::class,
        
        // Database
        Commands\DbBackupCommand::class,
        Commands\DbRestoreCommand::class,
        Commands\DbListCommand::class,
        Commands\DbCleanupCommand::class,
    ];
}
