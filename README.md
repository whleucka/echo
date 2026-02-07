# Echo PHP Framework

<a href='https://github.com/whleucka/echo/actions/workflows/php.yml'><img src='https://github.com/whleucka/echo/actions/workflows/php.yml/badge.svg' alt='github badge'></a>

Echo is a modern PHP 8.2+ MVC framework built for speed, simplicity, and flexibility. It leverages PHP 8 attributes for routing, PHP-DI for dependency injection, and Twig for templating.

## Development Status

**Work in Progress:** Echo is an actively developed personal PHP framework intended to serve as the backend for my website. APIs and internals may change at any time. Use at your own risk. Contributions and feedback are encouraged.

## Features

- **Attribute-based Routing** - Clean, declarative routing using PHP 8 attributes
- **Dependency Injection** - Powered by PHP-DI for flexible, testable code
- **Twig Templating** - Modern template engine with caching
- **Custom ORM** - Intuitive query builder and model system
- **Middleware Stack** - Comprehensive middleware for auth, CSRF, rate limiting, and more
- **Email System** - SMTP mail with queue, retries, Twig templates, and attachments
- **Redis Integration** - Optional Redis for caching, sessions, and rate limiting
- **Docker-Ready** - Fully containerized development environment
- **Testing** - Built-in PHPUnit integration

## Requirements

- Docker & Docker Compose
- Git

## Quick Start

### 1. Clone the Repository

```bash
git clone https://github.com/whleucka/echo.git
cd echo
```

### 2. Configure Environment

```bash
cp .env.example .env
```

Edit `.env` with your database credentials and other settings.

### 3. Start Docker Environment

```bash
docker-compose up -d
```

This starts four containers:
- `php` - PHP 8.3-FPM with Redis extension
- `nginx` - Nginx web server
- `db` - MariaDB 11 database
- `redis` - Redis 7 Alpine (caching, sessions, rate limiting)

### 4. Install Dependencies

```bash
docker-compose exec -it php composer install
```

### 5. Run Migrations

```bash
docker-compose exec -it php ./bin/console migrate:run
```

### 6. Access the Application

Open your browser to `http://localhost`

## Docker Commands

**IMPORTANT:** All PHP commands must be run inside the Docker container using `docker-compose exec -it php`.

```bash
# Start containers
docker-compose up -d

# Stop containers
docker-compose down

# View logs
docker-compose logs php
docker-compose logs nginx
docker-compose logs db
docker-compose logs redis

# Run Composer
docker-compose exec -it php composer install
docker-compose exec -it php composer update

# Run tests
docker-compose exec -it php ./vendor/phpunit/phpunit/phpunit tests

# Run migrations
docker-compose exec -it php ./bin/console migrate:run

# Clear template cache
docker-compose exec -it php composer clear-cache

# Access database CLI
docker-compose exec -it db mariadb -u root -p

# Access Redis CLI
docker-compose exec -it redis redis-cli

# Interactive shell
docker-compose exec -it php bash
```

## Framework Examples

### Routing with Attributes

Controllers use PHP 8 attributes for clean, declarative routing:

```php
<?php

namespace App\Http\Controllers;

use Echo\Framework\Http\Controller;
use Echo\Framework\Routing\Route\Get;
use Echo\Framework\Routing\Route\Post;
use Echo\Framework\Routing\Group;

#[Group(path_prefix: "/users", name_prefix: "user")]
class UserController extends Controller
{
    #[Get("/{id}", "show", ["auth"])]
    public function show(string $id): string
    {
        $user = User::find($id);
        return $this->render('users/show.html.twig', ['user' => $user]);
    }
    
    #[Post("/", "create", ["auth", "csrf"])]
    public function create(): string
    {
        $data = $this->validate([
            'email' => 'required|email|unique:users',
            'password' => 'required|min_length:8'
        ]);
        
        $user = User::create($data);
        return redirect('/users/' . $user->id);
    }
}
```

Available route attributes: `#[Get]`, `#[Post]`, `#[Put]`, `#[Patch]`, `#[Delete]`

### Database/ORM

Models extend the base `Model` class for intuitive database interactions:

```php
<?php

namespace App\Models;

use Echo\Framework\Database\Model;

class User extends Model
{
    protected string $table = 'users';
    protected array $fillable = ['email', 'first_name', 'surname'];
}

// Find by ID
$user = User::find(1);

// Query builder
$user = User::where('email', 'user@example.com')->first();
$users = User::where('active', 1)->orderBy('created_at', 'DESC')->get();

// Create
$user = User::create([
    'email' => 'user@example.com',
    'first_name' => 'John',
    'surname' => 'Doe'
]);

// Update
$user->update(['first_name' => 'Jane']);

// Delete
$user->delete();
```

### Helper Functions

Global helpers are available throughout your application:

```php
// Get application instance
$app = app();

// Get DI container
$container = container();

// Render a Twig template
return view('users/index.html.twig', ['users' => $users]);

// Redirect
return redirect('/dashboard');

// Access configuration
$debug = config('app.debug');

// Database helper
$users = db()->fetchAll("SELECT * FROM users WHERE active = ?", [1]);

// Cache helper (uses Redis if available, falls back to file)
$users = cache()->remember('active_users', 300, fn() => 
    User::where('active', 1)->get()
);

// Redis helper (direct access)
redis()->connection('default')->set('key', 'value');
```

### Middleware

Middleware is defined in `app/Http/Kernel.php` and can be applied to routes:

```php
#[Get("/admin", "admin.dashboard", ["auth", "csrf"])]
public function dashboard(): string
{
    return $this->render('admin/dashboard.html.twig');
}
```

Available middleware: `auth`, `csrf`, `request_limit`, `whitelist`, `blacklist`, and more.

### Validation

Built-in validation in controllers:

```php
$data = $this->validate([
    'email' => 'required|email|unique:users',
    'password' => 'required|min_length:8',
    'age' => 'required|numeric|min:18',
    'website' => 'url'
]);
```

## Email System

Echo includes a full email system with SMTP sending, a persistent job queue with retries, Twig template support, and audit logging.

### Configuration

Add SMTP credentials to your `.env` file:

```env
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your@email.com
MAIL_PASSWORD=secret
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME=Echo
```

Additional settings in `config/mail.php`: `max_retries`, `retry_delay_minutes`, `batch_size`.

### Sending Email

Use the fluent `Mailable` builder with the `mailer()` helper:

```php
use Echo\Framework\Mail\Mailable;

// Send immediately — plain text
mailer()->send(
    Mailable::create()
        ->to('user@example.com')
        ->subject('Hello')
        ->text('Plain text body')
);

// Send immediately — HTML with Twig template
mailer()->send(
    Mailable::create()
        ->to('user@example.com', 'Will')
        ->subject('Welcome!')
        ->template('emails/welcome.html.twig', ['name' => 'Will'])
);

// Send with attachments
mailer()->send(
    Mailable::create()
        ->to('user@example.com')
        ->subject('Your Report')
        ->html('<h1>Report attached</h1>')
        ->attach('/path/to/report.pdf')
        ->attachData($csvContent, 'data.csv', 'text/csv')
);

// Queue for background delivery
mailer()->queue(
    Mailable::create()
        ->to('user@example.com')
        ->subject('Newsletter')
        ->template('emails/newsletter.html.twig', $data)
);

// Schedule for future delivery
mailer()->send(
    Mailable::create()
        ->to('user@example.com')
        ->subject('Reminder')
        ->text('Your trial expires tomorrow.')
        ->delay('2026-03-01 09:00:00')
);
```

### Email Queue

Queued and scheduled emails are stored in the `email_jobs` table and processed by a background worker. The queue supports automatic retries with configurable delay and max attempts.

```bash
# Process the queue manually
./bin/console mail:queue

# View queue status
./bin/console mail:status

# Purge old sent/exhausted jobs
./bin/console mail:purge --days=30
```

The scheduler (`scheduler.php`) runs the mail worker every minute automatically when cron is configured.

### Email Templates

Email templates live in `templates/emails/` and extend the base layout:

```twig
{% extends "emails/base.html.twig" %}

{% block header %}<h1>Welcome, {{ name }}!</h1>{% endblock %}

{% block body %}
    <p>Thanks for signing up.</p>
{% endblock %}
```

### Logging

All email activity is logged to `storage/logs/mail-YYYY-MM-DD.log` via a dedicated `mail` channel. Sent emails and final failures are also recorded in the audit log.

## Redis Integration

Echo includes optional Redis support for caching, sessions, and rate limiting. All features gracefully fall back to file-based alternatives if Redis is unavailable.

### Configuration

Configure Redis in your `.env` file:

```env
# Redis Connection
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_PREFIX=echo:

# Drivers (set to "redis" to enable, "file" for fallback)
SESSION_DRIVER=file
CACHE_DRIVER=file
```

### Caching

Use the `cache()` helper for application caching:

```php
// Store a value (TTL in seconds)
cache()->set('key', 'value', 3600);

// Retrieve a value
$value = cache()->get('key', 'default');

// Remember pattern - get from cache or compute and store
$users = cache()->remember('active_users', 300, fn() => 
    User::where('active', 1)->get()
);

// Delete
cache()->delete('key');

// Check existence
if (cache()->has('key')) { ... }

// Bulk operations
cache()->setMany(['key1' => 'val1', 'key2' => 'val2'], 3600);
$values = cache()->getMany(['key1', 'key2']);
```

### Sessions

Sessions automatically use Redis when `SESSION_DRIVER=redis`. No code changes required - the existing `session()` helper works identically.

### Rate Limiting

Rate limiting automatically uses Redis when available, providing:
- IP-based limiting (not session-based)
- Shared limits across load-balanced servers
- Accurate request counting with atomic operations

### Direct Redis Access

For advanced use cases, access Redis directly:

```php
// Get a connection (connections are lazy-loaded and pooled)
$redis = redis()->connection('default');
$redis = redis()->connection('cache');

// Check availability
if (redis()->isAvailable()) { ... }
```

## Admin Backend

Echo includes a powerful, feature-rich admin panel out of the box. The admin system provides CRUD operations, user management, activity tracking, and system monitoring capabilities.

### Admin Modules

The framework comes with several pre-built admin modules accessible at `/admin`:

#### Dashboard
- **Widgets System** - Extensible widget registry for custom dashboard components
- **Analytics** - Request tracking with charts (today, week, month, YTD)
- **System Health** - Real-time monitoring of database, cache, and system resources
- **Activity Heatmap** - Visual representation of user activity patterns
- **Audit Summary** - Overview of recent database changes

#### User Management (`/admin/users`)
- Full CRUD operations for user accounts
- Role-based permissions (Standard, Admin)
- Password strength validation with regex patterns
- Avatar upload support
- Search and filtering by role
- Prevent users from deleting their own account

#### Activity Tracking (`/admin/activity`)
- Real-time session monitoring
- IP address and URI tracking
- Filter by frontend/backend activity
- User-specific activity views
- Read-only view (no create/edit/delete)

#### Audit Logs (`/admin/audits`)
- Automatic tracking of all database changes (create, update, delete)
- Detailed diff viewer showing before/after values
- Filter by event type, user, date range
- IP address and user agent logging
- Read-only view with detailed change inspection

#### Modules Management (`/admin/modules`)
- Manage sidebar navigation items
- Parent/child hierarchical structure
- Bootstrap Icons integration with autocomplete
- Enable/disable modules dynamically
- Custom ordering with drag-and-drop support
- Real-time sidebar updates via HTMX

#### User Permissions (`/admin/user-permissions`)
- Granular permission control per module per user
- Set create, edit, delete, and export permissions
- Prevent duplicate permission entries
- Admin users bypass permission checks

#### System Health (`/admin/health`)
- Database connectivity checks
- Cache system monitoring
- Disk space and memory usage
- JSON API endpoint for external monitoring tools
- Real-time health status refresh

### Creating Custom Admin Modules

Extend the `ModuleController` base class to quickly create CRUD interfaces. Define your table and form schemas declaratively using the builder methods:

```php
<?php

namespace App\Http\Controllers\Admin;

use Echo\Framework\Admin\Schema\{FormSchemaBuilder, TableSchemaBuilder};
use Echo\Framework\Http\ModuleController;
use Echo\Framework\Routing\Group;

#[Group(path_prefix: "/products", name_prefix: "products")]
class ProductsController extends ModuleController
{
    protected function defineTable(TableSchemaBuilder $builder): void
    {
        $builder->defaultSort('id', 'DESC')
                ->perPage(10);

        $builder->column('id', 'ID')->sortable();
        $builder->column('name', 'Name')->sortable()->searchable();
        $builder->column('price', 'Price')->sortable();
        $builder->column('stock', 'Stock')->sortable();
        $builder->column('created_at', 'Created')->sortable();

        $builder->filter('category', 'category')
                ->label('Category')
                ->optionsFrom("SELECT DISTINCT category as value, category as label FROM products ORDER BY category");
    }

    protected function defineForm(FormSchemaBuilder $builder): void
    {
        $builder->field('name', 'Name')
                ->input()
                ->rules(['required']);

        $builder->field('description', 'Description')
                ->input();

        $builder->field('price', 'Price')
                ->number()
                ->rules(['required', 'numeric']);

        $builder->field('stock', 'Stock')
                ->number()
                ->rules(['required', 'numeric']);
    }

    public function __construct()
    {
        parent::__construct("products"); // table name
    }
}
```

The `ModuleController` automatically provides:
- Paginated listing with sorting
- Search functionality
- Create/Edit/Show forms with validation
- Delete operations
- Export to CSV
- Filter dropdowns, filter links, and date range filters
- Modal-based editing via HTMX
- Permission checks per module and per user

### Admin Features

- **Auto-discovery**: Admin controllers are automatically registered
- **HTMX Integration**: Fast, seamless interactions without page reloads
- **Responsive Design**: Bootstrap 5-based UI works on all devices
- **Customizable Tables**: Format columns, add custom actions, override behavior
- **Validation**: Built-in validation with custom rules
- **Security**: CSRF protection, authentication, and authorization
- **Audit Trail**: Automatic tracking of all data changes
- **Export**: Built-in CSV export for all modules

## Project Structure

```
echo/
├── app/                    # Application code
│   ├── Http/
│   │   ├── Controllers/    # Controllers (auto-discovered)
│   │   │   └── Admin/      # Admin panel controllers
│   │   └── Kernel.php      # Middleware configuration
│   ├── Models/             # Database models
│   ├── Providers/          # Service providers
│   ├── Services/
│   │   └── Admin/          # Admin service layer
│   └── Helpers/            # Helper functions
├── src/                    # Framework code (Echo namespace)
│   ├── Framework/          # Core framework classes
│   │   ├── Admin/          # Admin system components
│   │   │   └── Widgets/    # Dashboard widgets
│   │   └── Mail/           # Email system (Mailer, Mailable, EmailQueue)
│   └── Interface/          # Contracts/interfaces
├── config/                 # Configuration files
├── migrations/             # Database migrations
├── templates/              # Twig templates
│   └── admin/              # Admin panel templates
├── public/                 # Web root
│   └── index.php           # Application entry point
├── jobs/                   # Scheduled job scripts
├── bin/
│   ├── console             # CLI entry point
│   └── release             # Version tagging script
└── tests/                  # PHPUnit tests
```

## Debug Profiler & Toolbar

Echo includes a powerful built-in profiler and debug toolbar for development. When `APP_DEBUG=true`, the toolbar appears at the bottom of every page providing real-time performance insights.

### Features

- **Request Tracking** - Monitor all HTTP requests including HTMX calls
- **Query Profiler** - Track every database query with timing and parameters
- **Memory Usage** - View current and peak memory consumption
- **Timeline Visualization** - Visual representation of request lifecycle
- **Slow Query Detection** - Automatically flag queries exceeding threshold
- **Backtrace Support** - See exactly where each query originates
- **Request History** - Track up to 50 recent requests in a single session
- **HTMX Integration** - Seamlessly profiles HTMX requests without page reloads

### Toolbar Panels

#### Request History
- View all requests made during the session (page loads + HTMX calls)
- Click any request to inspect its details
- Initial page load is marked with a special indicator
- Clear history with one click

#### Request Details
- URL and HTTP method
- Total execution time
- Memory usage (current and peak)
- Request timestamp

#### Database Queries
- Complete list of all executed queries
- SQL statement with bound parameters
- Query execution time
- Slow query highlighting (configurable threshold)
- Backtrace showing where the query was called
- Query count and total time

#### Timeline
- Visual timeline bar showing request lifecycle
- Breakdown of time spent in different sections (controller, database, etc.)
- Section timing with call counts

### Configuration

Configure the profiler in `config/debug.php`:

```php
return [
    // Slow query threshold in milliseconds
    'slow_query_threshold' => env('DEBUG_SLOW_QUERY_MS', 100),
    
    // Enable/disable debug toolbar
    'toolbar_enabled' => env('DEBUG_TOOLBAR', true),
    
    // Toolbar position (bottom or top)
    'toolbar_position' => 'bottom',
];
```

### Using the Profiler in Code

Access the profiler programmatically:

```php
// Get profiler instance
$profiler = profiler();

// Start timing a section
$profiler->startSection('expensive-operation');

// ... your code ...

// End timing
$profiler->endSection('expensive-operation');

// Get query profiler
$queryCount = $profiler->queries()->getQueryCount();
$slowQueries = $profiler->queries()->getSlowQueries(100); // queries > 100ms

// Get summary data
$summary = $profiler->getSummary();
```

### Data Storage

Profile data is stored server-side in `storage/profiler/` to avoid HTTP header size limits. Each request gets a unique ID, and the toolbar fetches detailed data on demand via AJAX.

### HTMX Request Tracking

The toolbar automatically tracks HTMX requests via response headers:
- `X-Echo-Debug-Id` - Unique profile ID
- `X-Echo-Debug-Time` - Request execution time
- `X-Echo-Debug-Memory` - Memory usage
- `X-Echo-Debug-Queries` - Query count

## Benchmarking

Echo includes a built-in benchmark suite for measuring framework performance. The benchmarks follow TechEmpower Framework Benchmark conventions.

### Running Benchmarks

```bash
# Basic usage (requires wrk, ab, or falls back to curl)
./bin/benchmark

# With custom parameters
./bin/benchmark [base_url] [duration] [connections]

# Example: Test against localhost for 30 seconds with 200 connections
./bin/benchmark http://localhost 30 200
```

**Note:** For accurate results, install `wrk` (recommended) or Apache Bench (`ab`). The script will fall back to `curl` if neither is available, but results will be less accurate.

### Benchmark Endpoints

The following endpoints are available at `/benchmark/*` when `APP_DEBUG=true`:

| Endpoint | Description |
|----------|-------------|
| `/benchmark/plaintext` | Raw framework overhead - returns "Hello, World!" as plain text |
| `/benchmark/json` | JSON serialization - returns a simple JSON object |
| `/benchmark/db` | Single database query - fetches one random row |
| `/benchmark/queries/{count}` | Multiple queries - fetches N rows (1-500) |
| `/benchmark/template` | Template rendering - renders a Twig template |
| `/benchmark/fullstack` | Full stack test - database query + template rendering |
| `/benchmark/memory` | Memory usage - reports current and peak memory consumption |

### Sample Output

```
╔═══════════════════════════════════════════════════════════════╗
║           Echo Framework Performance Benchmark                ║
╚═══════════════════════════════════════════════════════════════╝

Configuration:
  Base URL:    http://localhost
  Tool:        wrk
  Duration:    10s
  Connections: 100

═══════════════════════════════════════════════════════════════
                     Running Benchmarks
═══════════════════════════════════════════════════════════════

Running: Plaintext (raw overhead)
  Requests/sec: 15234.56
  Latency (avg): 6.52ms
  Latency (max): 45.23ms

Running: JSON Serialization
  Requests/sec: 14521.33
  Latency (avg): 6.89ms
  ...
```

### Comparing Frameworks

To compare Echo with other frameworks:

1. Run the same benchmark tests against Laravel, Slim, Symfony, etc.
2. Use identical hardware and database configuration
3. Run multiple iterations and average the results
4. Consider submitting results to [TechEmpower Benchmarks](https://www.techempower.com/benchmarks/)

## Testing

Run the test suite:

```bash
docker-compose exec -it php ./vendor/phpunit/phpunit/phpunit tests
```

Or use the composer script:

```bash
docker-compose exec -it php composer test
```

## Documentation

For detailed documentation, see the `docs/` directory:

- [Documentation Index](docs/INDEX.md) - Central navigation hub
- [Quick Reference](docs/QUICK_REF.md) - Common tasks and conventions
- [API Documentation](docs/API.md) - Routing and middleware
- [Database Documentation](docs/DATABASE.md) - ORM, migrations, validation
- [Testing](docs/TESTING.md) - Testing guidelines
- [Deployment](docs/DEPLOYMENT.md) - Docker environment and deployment
- [Troubleshooting](docs/troubleshooting/) - Common issues and solutions

## License

Echo is licensed under the MIT License. See [LICENSE](LICENSE) for more details.
