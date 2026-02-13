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
        
        // Security
        Commands\KeyGenerateCommand::class,
        
        // Storage
        Commands\StorageFixCommand::class,
        
        // Cache
        Commands\CacheClearCommand::class,

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

        // Generators
        Commands\MakeUserCommand::class,
        Commands\MakeMigrationCommand::class,
        Commands\MakeControllerCommand::class,
        Commands\MakeModelCommand::class,
        Commands\MakeServiceCommand::class,
        Commands\MakeMiddlewareCommand::class,
        Commands\MakeProviderCommand::class,
        Commands\MakeCommandCommand::class,
        
        // Activity
        Commands\ActivityCleanupCommand::class,
        Commands\ActivityStatsCommand::class,
        
        // Audits
        Commands\AuditListCommand::class,
        Commands\AuditStatsCommand::class,
        Commands\AuditPurgeCommand::class,
        
        // Database
        Commands\DbBackupCommand::class,
        Commands\DbRestoreCommand::class,
        Commands\DbListCommand::class,
        Commands\DbCleanupCommand::class,

        // Mail
        Commands\MailQueueCommand::class,
        Commands\MailStatusCommand::class,
        Commands\MailPurgeCommand::class,
    ];
}
