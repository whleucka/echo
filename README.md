# Echo PHP Framework

<a href='https://github.com/whleucka/echo/actions/workflows/php.yml'><img src='https://github.com/whleucka/echo/actions/workflows/php.yml/badge.svg' alt='github badge'></a>

A modern PHP 8.4+ MVC framework with attribute-based routing, PHP-DI, and Twig. Runs in Docker (PHP 8.4-FPM, Nginx, MariaDB 11, Redis 7).

> **Work in Progress** — APIs and internals may change. This project will
> eventually serve as a backend to my personal website.

## Quick Start

```bash
git clone https://github.com/whleucka/echo.git && cd echo
cp .env.example .env                              # configure credentials
docker-compose up -d --build                      # php, nginx, db, redis
docker-compose exec -it php composer install
docker-compose exec -it php ./bin/console migrate:run
```

Open `http://localhost`.

### Development vs Production

**Development** (default, `APP_DEBUG=true`):
- OPcache disabled — code changes take effect instantly, no container restarts
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

## Common Commands

```bash
docker-compose exec -it php composer test          # run tests
docker-compose exec -it php composer clear-cache   # clear Twig cache
docker-compose exec -it php ./bin/console migrate:run
docker-compose exec -it db mariadb -u root -p      # database CLI
docker-compose exec -it redis redis-cli            # Redis CLI
```

## Routing

Controllers use PHP 8 attributes. Route types: `Get`, `Post`, `Put`, `Patch`, `Delete`, `Head`.

```php
use Echo\Framework\Http\Controller;
use Echo\Framework\Routing\Route\{Get, Post};
use Echo\Framework\Routing\Group;

#[Group(pathPrefix: "/users", namePrefix: "user", middleware: ["auth"])]
class UserController extends Controller
{
    #[Get("/{id}", "show")]
    public function show(string $id): string
    {
        $user = User::find($id);
        return $this->render('users/show.html.twig', ['user' => $user]);
    }

    #[Post(path: "/", name: "store")]
    public function store(): string
    {
        $data = $this->validate([
            'email' => 'required|email|unique:users',
            'password' => 'required|min_length:8',
        ]);
        $user = User::create($data);
        return redirect('/users/' . $user->id);
    }
}
```

Controllers in `app/Http/Controllers/` are auto-discovered. Middleware is applied via the third attribute argument or the `Group` attribute.

## Database / ORM

```php
use Echo\Framework\Database\Model;

class User extends Model
{
    use Auditable;

    protected string $table = 'users';
}

$user  = User::find(1);
$user  = User::where('email', 'a@b.com')->first();
$user  = User::where('dob', '>=', '1967-01-12')->first();
$users = User::where('active', 1)->orderBy('created_at', 'DESC')->get();
// create a new model
$user  = User::create([
    'email' => 'a@b.com', 
    'first_name' => 'Abby', 
    'last_name' => 'Green'
]);
// update a model
$user->update([
    'first_name' => 'Jane', 
    'last_name' => 'Doe'
]);
// or 
$user->first_name = 'Alice';
$user->save();
// delete a model
$user->delete();

// QueryBuilder for complex queries
$rows = qb()->select('*')->from('users')->where('active', '=', 1)->get();
```

## Helper Functions

All defined in `app/Helpers/Functions.php`:

| Helper | Returns |
|---|---|
| `app()` | Web Application (HttpKernel) |
| `console()` | Console Application (ConsoleKernel) |
| `user()` | Authenticated User or null |
| `container()` | DI container |
| `qb()` | New QueryBuilder |
| `twig()` | Twig Environment |
| `db()` | PDO Connection or null |
| `session()` | Session instance |
| `router()` | Router instance |
| `request()` | Current HTTP Request |
| `env($key, $default)` | Environment variable |
| `uri($name, ...$params)` | Named route URI |
| `dump($val)` | Pretty-print value |
| `dd($val)` | Dump & die |
| `logger()` | Logger instance |
| `profiler()` | Profiler (null if debug off) |
| `redis()` | RedisManager instance |
| `cache()` | Cache (Redis or file fallback) |
| `redirect($url)` | Redirect response (HTMX-aware) |
| `crypto()` | Crypto instance |
| `mailer()` | Mailer instance |
| `config($key)` | Config value (e.g. `config('app.debug')`) |

## Admin Modules

Extend `ModuleController` for instant CRUD with HTMX tables, modals, sorting, filtering, pagination, CSV export, and per-user permissions.

```php
#[Group(pathPrefix: "/products", namePrefix: "products")]
class ProductsController extends ModuleController
{
    public function __construct() { parent::__construct("products"); }

    protected function defineTable(TableSchemaBuilder $builder): void
    {
        $builder->defaultSort('id', 'DESC')->perPage(10);
        $builder->column('id', 'ID')->sortable();
        $builder->column('name', 'Name')->sortable()->searchable();
        $builder->column('price', 'Price')->sortable();
    }

    protected function defineForm(FormSchemaBuilder $builder): void
    {
        $builder->field('name', 'Name')->input()->rules(['required']);
        $builder->field('price', 'Price')->number()->rules(['required', 'numeric']);
    }
}
```

Built-in modules: Users, Activity, Audits, Health, Modules, User Permissions.

## Email

```php
use Echo\Framework\Mail\Mailable;

// Send immediately
mailer()->send(
    Mailable::create()
        ->to('user@example.com')
        ->subject('Welcome!')
        ->template('emails/welcome.html.twig', ['name' => 'Will'])
);

// Queue for background delivery
mailer()->queue(
    Mailable::create()
        ->to('user@example.com')
        ->subject('Newsletter')
        ->template('emails/newsletter.html.twig', $data)
);
```

Configure SMTP in `.env` (`MAIL_HOST`, `MAIL_PORT`, etc.). Queue commands: `mail:queue`, `mail:status`, `mail:purge`.

## Redis & Caching

Redis is optional — everything falls back to file-based alternatives.

```php
// Cache (auto-selects Redis or file driver)
cache()->set('key', 'value', 3600);
$val = cache()->get('key', 'default');
$users = cache()->remember('active_users', 300, fn() => User::where('active', 1)->get());

// Direct Redis
redis()->connection('default')->set('key', 'value');
```

Configure in `.env`: `REDIS_HOST`, `CACHE_DRIVER`, `SESSION_DRIVER`.

## Debug Toolbar

When `APP_DEBUG=true`, a toolbar shows request timing, database queries, memory usage, and a visual timeline. Configure thresholds in `config/debug.php`.

## Benchmarking

```bash
./bin/benchmark                          # defaults: localhost, 10s, 100 connections
./bin/benchmark http://localhost 30 200  # custom
```

Endpoints at `/benchmark/*` (plaintext, json, db, queries, template, fullstack, memory). Requires `wrk` or `ab` for accurate results.

## Console Commands

`./bin/console` is the CLI entry point (Symfony Console). Run inside Docker:

```bash
docker-compose exec -it php ./bin/console <command>
```

| Group | Commands |
|---|---|
| `audit:` | `list`, `stats`, `purge` |
| `db:` | `backup`, `restore`, `list`, `cleanup` |
| `mail:` | `queue`, `status`, `purge` |
| `make:` | `command`, `controller`, `middleware`, `migration`, `model`, `provider`, `service`, `user` |
| `migrate:` | `run`, `rollback`, `status`, `fresh`, `up`, `down` |
| `route:` | `list`, `cache`, `clear` |
| `session:` | `stats`, `cleanup` |
| Other | `version`, `key:generate`, `storage:fix`, `cache:clear`, `server` |

## Project Structure

```
app/                 # Application code (App\ namespace)
  Http/Controllers/  # Route controllers (auto-discovered)
  Models/            # Eloquent-style models
  Helpers/           # Global helper functions
  Services/          # Business logic services
  Providers/         # Service providers
src/Framework/       # Framework internals (Echo\ namespace)
config/              # PHP config files
templates/           # Twig templates
migrations/          # Database migrations
tests/               # PHPUnit tests
bin/console          # CLI entry point
public/index.php     # Web entry point
```

## Testing

```bash
docker-compose exec -it php composer test
```

## License

MIT — see [LICENSE](LICENSE).
