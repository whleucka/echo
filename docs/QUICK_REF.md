# QUICK REFERENCE

This file provides a quick reference for common tasks and important information within the Echo PHP framework.

## Project Overview

Echo is a custom PHP 8.2+ MVC framework built for speed and simplicity. It uses PHP 8 attributes for routing, PHP-DI for dependency injection, and Twig for templating.

## Essential Docker Commands

**IMPORTANT: The project runs in Docker containers. NEVER run PHP, composer, or bin/console commands directly on the host. ALL commands must be executed inside the `php` container using `docker-compose exec`.**

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
```

## Helper Functions

Global helpers defined in `app/Helpers/Functions.php`:
- `app()` - Get Application instance
- `container()` - Get DI container
- `console()` - Get Console kernel
- `view()` - Render Twig template
- `redirect()` - HTTP redirect
- `config()` - Get configuration value
- `session()` - Get session instance
- `cache()` - Get cache instance (Redis or file)
- `redis()` - Get Redis manager
- `db()` - Get database connection
- `user()` - Get current authenticated user
- `profiler()` - Get debug profiler (if debug mode)

## Conventions

- Controllers: `FooController` in `app/Http/Controllers/`
- Models: Singular names, extend `Model`
- Route names: `resource.action` format (e.g., `user.show`)
- Database tables: Plural names with timestamps