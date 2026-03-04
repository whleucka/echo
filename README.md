# Echo PHP Framework

<a href='https://github.com/whleucka/echo/actions/workflows/php.yml'><img src='https://github.com/whleucka/echo/actions/workflows/php.yml/badge.svg' alt='github badge'></a>

A modern PHP 8.4+ MVC framework with attribute-based routing, PHP-DI, and Twig. Runs in Docker (PHP 8.4-FPM, Nginx, MariaDB 11, Redis 7).

> **Work in Progress**: APIs and internals may change. This project will
> eventually serve as a backend to my personal website.

## Quick Start

```bash
git clone https://github.com/whleucka/echo.git && cd echo
cp .env.example .env                              # configure credentials
docker-compose up -d --build                      # php, nginx, db, redis
./bin/php composer install                        # enter php container
./echo migrate:fresh                              # migrate database
./echo storage:fix                                # fix permissions on storage and cache directories
```

Open `http://localhost`.

### Development vs Production

**Development** (default, `APP_DEBUG=true`):
- OPcache disabled: code changes take effect instantly, no container restarts
- Xdebug enabled (port 9003, trigger mode with `XDEBUG_SESSION` cookie or query param)
- Verbose errors displayed

**Production** (`docker-compose.prod.yml`):
- OPcache enabled with aggressive caching
- No Xdebug
- Errors logged only

```bash
# Production deployment
docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

## Documentation

| Topic | Description |
|---|---|
| [Routing](docs/routing.md) | Attribute routing, groups, subdomain routing, middleware |
| [Database / ORM](docs/database.md) | Model (CRUD, relationships, aggregates) and QueryBuilder |
| [Admin Modules](docs/admin-modules.md) | ModuleController CRUD: tables, forms, JOINs, filters, actions, permissions |
| [Events](docs/events.md) | Event system: creating events/listeners, dispatching, built-in events |
| [Helpers](docs/helpers.md) | All global helper functions |
| [Email](docs/email.md) | Mailable API, send/queue |
| [Caching](docs/caching.md) | Redis and file-based caching |
| [Console](docs/console.md) | All CLI commands |
| [Testing](docs/testing.md) | PHPUnit setup and usage |

## Docker Commands

```bash
docker-compose exec -it php bash                   # PHP container
docker-compose exec -it db mariadb -u root -p      # database CLI
docker-compose exec -it redis redis-cli            # Redis CLI
```

## Project Structure

```
app/                 # Application code (App\ namespace)
  Http/Controllers/  # Route controllers (auto-discovered)
  Models/            # Active Record models
  Events/            # Application events
  Listeners/         # Event listeners
  Helpers/           # Global helper functions
  Services/          # Business logic services
  Providers/         # Service providers
src/Framework/       # Framework internals (Echo\ namespace)
config/              # PHP config files
templates/           # Twig templates
migrations/          # Database migrations
tests/               # PHPUnit tests
docs/                # Documentation
bin/console          # CLI entry point
public/index.php     # Web entry point
```

## Screenshots

<img width="1436" height="1058" alt="image" src="https://github.com/user-attachments/assets/63b7251a-28b4-4349-ab6b-6fb5944df1bc" />

## License

MIT: see [LICENSE](LICENSE).
