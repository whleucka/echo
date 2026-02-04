# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Echo is a custom PHP 8.2+ MVC framework built for speed and simplicity. It uses PHP 8 attributes for routing, PHP-DI for dependency injection, and Twig for templating.

## Docker Environment

**IMPORTANT: The project runs in Docker containers. NEVER run PHP, composer, or bin/console commands directly on the host. ALL commands must be executed inside the `php` container using `docker-compose exec`.**

### Container Names

- `php` - PHP 8.3-FPM (run ALL PHP commands here: tests, composer, console)
- `nginx` - Nginx web server
- `db` / `mariadb` - MariaDB 11 database

### Running Commands

```bash
# Start Docker environment
docker-compose up -d

# Check running containers
docker ps

# ALWAYS prefix PHP commands with: docker-compose exec -it php
docker-compose exec -it php composer install
docker-compose exec -it php ./vendor/phpunit/phpunit/phpunit tests
docker-compose exec -it php composer clear-cache
docker-compose exec -it php php bin/console migrate
docker-compose exec -it php php bin/console server

# View container logs
docker-compose logs php
docker-compose logs nginx
docker-compose logs db

# Access database CLI
docker-compose exec -it db mariadb -u root -p

# Interactive shell in PHP container
docker-compose exec -it php bash
```

## Architecture

### Directory Structure

- `app/` - Application code (controllers, models, providers)
- `src/` - Framework code (Echo namespace)
- `config/` - Configuration files
- `migrations/` - Database migration files
- `templates/` - Twig templates (cache in `templates/.cache`)
- `public/` - Web root (entry point: `index.php`)
- `bin/console` - CLI entry point
- `tests/` - PHPUnit test suite

### Namespaces

- `App\` → `app/` - Application code
- `Echo\Framework\` → `src/Framework/` - Framework implementation
- `Echo\Interface\` → `src/Interface/` - Contracts/interfaces
- `Tests\` → `tests/` - Test suite

### Routing System

Routes are defined using PHP 8 attributes on controller methods. Controllers in `app/Http/Controllers/` are auto-discovered.

```php
#[Get("/users/{id}", "user.show", ["auth"])]
public function show(string $id): string
```

Available attributes: `#[Get]`, `#[Post]`, `#[Put]`, `#[Patch]`, `#[Delete]`

Route parameters support regex patterns: `{id}` with `[0-9]`, `(blue|red)`, dots allowed.

### Middleware Stack

Defined in `app/Http/Kernel.php`, processed in order:
RequestID → Sessions → Auth → Whitelist → Blacklist → RequestLimit → CSRF

### Database/ORM

Custom ORM with query builder. Models extend `Echo\Framework\Database\Model`.

```php
User::find($id);
User::where('email', $email)->first();
User::create(['name' => $name, 'email' => $email]);
```

Migrations use Blueprint pattern in `migrations/` directory.

### Validation

Built into Controller base class:

```php
$data = $this->validate([
    'email' => 'required|email|unique:users',
    'password' => 'required|min_length:8'
]);
```

Rules: required, unique, string, array, date, numeric, email, integer, float, boolean, url, ip, ipv4, ipv6, mac, domain, uuid, regex, min_length, max_length, match

### Helper Functions

Global helpers defined in `app/Helpers/Functions.php`:
- `app()` - Get Application instance
- `container()` - Get DI container
- `console()` - Get Console kernel
- `view()` - Render Twig template
- `redirect()` - HTTP redirect

## Conventions

- Controllers: `FooController` in `app/Http/Controllers/`
- Models: Singular names, extend `Model`
- Route names: `resource.action` format (e.g., `user.show`)
- Database tables: Plural names with timestamps

## Development Roadmap

See `ROADMAP.md` for the improvement plan divided into 4 phases:

1. **Phase 1: Security Fixes** - Session security, CSRF, IP spoofing, security headers
2. **Phase 2: Critical Bugs** - N+1 queries, config caching, migration transactions
3. **Phase 3: Testing** - Session, Flash, Validation, CSRF, Model CRUD tests
4. **Phase 4: Features** - Schema::alter(), rollback, route caching, relationships, bulk insert

When working on roadmap tasks, update the checkboxes in `ROADMAP.md` to track progress.
