# Console Commands

`./bin/console` is the CLI entry point (Symfony Console). Run inside Docker:

```bash
docker-compose exec -it php ./bin/console <command>
```

Or use the shortcut:

```bash
./echo <command>
```

## Command Reference

| Group | Command | Description |
|---|---|---|
| `activity:` | `cleanup` | Clean up old activity records |
| | `geocode` | Backfill country codes for existing activity records |
| | `stats` | Show activity statistics |
| `audit:` | `list` | List recent audit entries |
| | `purge` | Purge old audit entries |
| | `stats` | Show audit statistics |
| `cache:` | `clear` | Clear all caches (templates, routes, widgets) |
| `db:` | `backup` | Create a database backup |
| | `cleanup` | Delete old backups, keeping most recent N |
| | `list` | List available database backups |
| | `restore` | Restore database from a backup |
| `file:` | `cleanup` | Clean up orphaned files in uploads directory |
| `geoip:` | `update` | Download/update MaxMind GeoLite2-Country database |
| `key:` | `generate` | Generate a new APP_KEY |
| `mail:` | `purge` | Purge old sent/exhausted email jobs |
| | `queue` | Process pending email jobs |
| | `status` | Show email queue status |
| `make:` | `command` | Create a new console command class |
| | `controller` | Create a new controller class |
| | `event` | Create a new event class |
| | `listener` | Create a new event listener class |
| | `middleware` | Create a new middleware class |
| | `migration` | Create a new migration file |
| | `model` | Create a new model class |
| | `provider` | Create a new service provider class |
| | `service` | Create a new service class |
| | `user` | Create a new user |
| `migrate:` | `down` | Run down method on specific migration files |
| | `fresh` | Drop all tables and re-run all migrations |
| | `rollback` | Rollback the last batch of migrations |
| | `run` | Run all pending migrations |
| | `status` | Show migration status |
| | `up` | Run up method on specific migration files |
| `route:` | `cache` | Cache all application routes |
| | `clear` | Clear the route cache |
| | `list` | List all registered routes |
| `storage:` | `fix` | Fix ownership of storage and cache directories |
| Other | `server` | Start the local development server |
| | `version` | Display the application version |
