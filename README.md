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

## Common Docker Commands

```bash
docker-compose exec -it php bash                   # PHP container
docker-compose exec -it db mariadb -u root -p      # database CLI
docker-compose exec -it redis redis-cli            # Redis CLI
```

## Routing

Controllers use PHP 8 attributes. Route types: `Get`, `Post`, `Put`, `Patch`, `Delete`, `Head`. Controllers in `app/Http/Controllers/` are auto-discovered.

```php
#[Group(pathPrefix: "/users", namePrefix: "user", middleware: ["auth"])]
class UserController extends Controller
{
    #[Get("/{id}", "show")]
    public function show(string $id): string { /* ... */ }

    #[Post(path: "/", name: "store")]
    public function store(): string { /* ... */ }
}
```

Supports subdomain routing and route caching. See [full routing docs](docs/routing.md).

## Database / ORM

Active Record ORM with fluent query building, relationships, and model lifecycle events.

```php
$user  = User::find(1);
$users = User::where('active', 1)->orderBy('name')->get();
$user  = User::create(['email' => 'a@b.com', 'name' => 'Alice']);
$user->update(['name' => 'Jane']);
$user->delete();
```

Includes `whereNull`, `whereBetween`, `whereRaw`, eager loading (`with`/`load`), aggregates (`count`, `max`), and `QueryBuilder` for complex JOINs. See [full database docs](docs/database.md).

## Events

PSR-14 inspired event system with model lifecycle, HTTP, and auth events.

```php
// Dispatch
event(new OrderPlaced($order->id, $order->email, $order->total));

// Register in EventServiceProvider
protected array $listen = [
    OrderPlaced::class => [SendConfirmation::class],
];
```

Built-in events for model CRUD, HTTP requests, and authentication. See [full events docs](docs/events.md).

## Admin Modules

Extend `ModuleController` for instant CRUD with HTMX tables, modals, sorting, filtering, pagination, CSV export, and per-user permissions.

```php
#[Group(pathPrefix: "/products", namePrefix: "products")]
class ProductsController extends ModuleController
{
    protected string $tableName = "products";

    protected function defineTable(TableSchemaBuilder $builder): void
    {
        $builder->defaultSort('id', 'DESC')->perPage(10);
        $builder->column('id', 'ID');
        $builder->column('name', 'Name')->searchable();
        $builder->column('price', 'Price');
    }

    protected function defineForm(FormSchemaBuilder $builder): void
    {
        $builder->field('name', 'Name')->input()->rules(['required']);
        $builder->field('price', 'Price')->number()->rules(['required', 'numeric']);
    }
}
```

Supports JOINs, expressions, filter links, custom formatters, image/file uploads, pivot tables, and hooks. See [full admin modules docs](docs/admin-modules.md).

## Email

```php
mailer()->send(
    Mailable::create()
        ->to('user@example.com')
        ->subject('Welcome!')
        ->template('emails/welcome.html.twig', ['name' => 'Will'])
);
```

Supports queueing for background delivery. See [full email docs](docs/email.md).

## Redis & Caching

Redis is optional — falls back to file-based alternatives.

```php
cache()->set('key', 'value', 3600);
$val = cache()->get('key', 'default');
$users = cache()->remember('active_users', 300, fn() => User::where('active', 1)->get());
```

See [full caching docs](docs/caching.md).

## Debug Toolbar

When `APP_DEBUG=true`, a toolbar shows request timing, database queries, memory usage, and a visual timeline. Configure thresholds in `config/debug.php`.

## Benchmarking

```bash
./bin/benchmark                          # defaults: localhost, 10s, 100 connections
./bin/benchmark http://localhost 30 200  # custom
```

Endpoints at `/benchmark/*` (plaintext, json, db, queries, template, fullstack, memory). Requires `wrk` or `ab` for accurate results.

## Console Commands

`./bin/console` is the CLI entry point. See [full command reference](docs/console.md).

```bash
./echo migrate:run              # run migrations
./echo make:controller          # scaffold a controller
./echo make:event               # scaffold an event
./echo make:listener            # scaffold a listener
./echo route:list               # list all routes
./echo cache:clear              # clear all caches
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

## Testing

```bash
./bin/phpunit                              # all tests
./bin/phpunit tests/Http/KernelTest.php    # single file
./bin/phpunit --filter testMethodName      # single method
```

See [full testing docs](docs/testing.md).

## License

MIT: see [LICENSE](LICENSE).
