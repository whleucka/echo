# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Echo is a custom PHP 8.2+ MVC framework using attribute-based routing, PHP-DI for dependency injection, and Twig for templating. It runs in Docker (PHP 8.3-FPM, Nginx, MariaDB 11, Redis 7).

## Commands

All PHP commands run inside the Docker container. Bin helpers wrap `docker-compose exec`:

```bash
# Tests
./bin/phpunit                              # run all tests
./bin/phpunit tests/Http/KernelTest.php    # run a single test file
./bin/phpunit --filter testMethodName      # run a single test method
composer test                              # alternative (inside container)

# Development
docker-compose up -d                       # start containers
./bin/php composer install                 # install dependencies
./bin/console migrate:run                  # run database migrations
composer clear-cache                       # clear Twig template cache
```

## Architecture

### Namespaces & Directories

- `Echo\` → `src/Framework/` — framework internals (routing, ORM, middleware, admin base)
- `App\` → `app/` — application code (controllers, models, services, providers)
- `Tests\` → `tests/` — PHPUnit tests

### Request Lifecycle

`public/index.php` → `app()` → `Application` boots providers → `HttpKernel` runs middleware stack → `Router` dispatches to controller → response

### Routing

Routes are declared via PHP 8 attributes on controller methods:

```php
use Echo\Framework\Routing\Route\{Get, Post, Put, Delete};
use Echo\Framework\Routing\Group;

#[Group(path_prefix: "/admin", middleware: ["auth"])]
class MyController extends Controller
{
    #[Get("/items", "items.index")]
    public function index() { ... }

    #[Post("/items", "items.store")]
    public function store() { ... }
}
```

Route attributes: `Get`, `Post`, `Put`, `Patch`, `Delete`, `Head`. Controllers in `app/Http/Controllers/` are auto-discovered. Routes are cached via `RouteCache`.

### Admin Module System

Admin CRUD modules extend `ModuleController` (at `src/Framework/Http/ModuleController.php`), which provides full CRUD with HTMX-driven tables, modals, sorting, filtering, pagination, and CSV export.

Each module defines its schema declaratively via two builder methods:

```php
#[Group(path_prefix: "/users", name_prefix: "users")]
class UsersController extends ModuleController
{
    protected function defineTable(TableSchemaBuilder $builder): void { ... }
    protected function defineForm(FormSchemaBuilder $builder): void { ... }

    public function __construct()
    {
        parent::__construct("users");  // table name
    }
}
```

Schema builders (`TableSchemaBuilder`, `FormSchemaBuilder`) produce immutable value objects (`TableSchema`, `FormSchema`). Key components live in `src/Framework/Admin/Schema/`.

Admin modules: `app/Http/Controllers/Admin/` — Users, Audit, Activity, Health, Modules, UserPermissions.

### Database

- **Model** (`src/Framework/Database/Model.php`): Active Record with `find()`, `where()`, `get()`, `first()`, `create()`, `update()`, `delete()`
- **QueryBuilder** (`src/Framework/Database/QueryBuilder.php`): Fluent SQL builder for raw queries with parameter binding
- **Helpers**: `qb()` returns new QueryBuilder, `db()` returns PDO Connection
- Admin table queries use QueryBuilder directly (supports JOINs, expressions, aliases); the ORM is for simpler model operations

### Middleware

Defined in `app/Http/Kernel.php` as `$middleware_layers`. Applied per-route via the `middleware` parameter on route attributes/groups (e.g., `middleware: ["auth"]`).

### Helper Functions

Global helpers in `app/Helpers/Functions.php`: `app()`, `console()`, `user()`, `container()`, `qb()`, `twig()`, `db()`, `session()`, `router()`, `request()`, `env()`, `uri()`, `dump()`, `dd()`, `logger()`, `profiler()`, `redis()`, `cache()`, `redirect()`, `crypto()`, `mailer()`, `config()`.

### Configuration

All config lives in `config/` as PHP files returning arrays. Accessed via `config("file.key")` (e.g., `config("db.host")`). Environment variables loaded from `.env` via Dotenv.

### Templates

Twig templates in `templates/`. Cache in `templates/.cache/`. Auto-reload when `APP_DEBUG=true`.

## Testing

PHPUnit 12, bootstrap in `tests/bootstrap.php` (sets `APP_ENV=testing`). Base class: `Tests\TestCase`. Tests organized by domain: `Database/`, `Http/`, `Session/`, `Routing/`, `Audit/`, `Admin/`.

## Documentation Accuracy

When modifying code that affects any of the following, **always** check and update `README.md` and this file (`CLAUDE.md`) to keep them in sync:

- Helper functions (`app/Helpers/Functions.php`) — update the helper list in both files
- Route attributes or routing behavior (`src/Framework/Routing/`)
- Admin module system or `ModuleController`
- Database Model or QueryBuilder APIs
- Middleware names or stack
- Configuration keys or file structure
- Docker setup, container names, or CLI commands
- Test structure or PHPUnit configuration

**Rules:**
1. After any code change, verify affected README/CLAUDE.md sections still match the source code.
2. Never add functions, classes, or features to documentation that don't exist in the codebase.
3. Never remove documentation for things that still exist.
4. Keep README concise — short descriptions, useful examples, no bloat. Detailed internals belong in CLAUDE.md.
5. When adding a new helper function, add it to both the CLAUDE.md helper list and the README helper table.
