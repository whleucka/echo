# Echo PHP Framework

<a href='https://github.com/whleucka/echo/actions/workflows/php.yml'><img src='https://github.com/whleucka/echo/actions/workflows/php.yml/badge.svg' alt='github badge'></a>

Echo is a modern PHP 8.2+ MVC framework built for speed, simplicity, and flexibility. It leverages PHP 8 attributes for routing, PHP-DI for dependency injection, and Twig for templating.

## Features

- **Attribute-based Routing** - Clean, declarative routing using PHP 8 attributes
- **Dependency Injection** - Powered by PHP-DI for flexible, testable code
- **Twig Templating** - Modern template engine with caching
- **Custom ORM** - Intuitive query builder and model system
- **Middleware Stack** - Comprehensive middleware for auth, CSRF, rate limiting, and more
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

### 2. Start Docker Environment

```bash
docker-compose up -d
```

This starts three containers:
- `php` - PHP 8.3-FPM
- `nginx` - Nginx web server
- `db` - MariaDB 11 database

### 3. Install Dependencies

```bash
docker-compose exec -it php composer install
```

### 4. Run Migrations

```bash
docker-compose exec -it php php bin/console migrate
```

### 5. Access the Application

Open your browser to `http://localhost:8080`

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

# Run Composer
docker-compose exec -it php composer install
docker-compose exec -it php composer update

# Run tests
docker-compose exec -it php ./vendor/phpunit/phpunit/phpunit tests

# Run migrations
docker-compose exec -it php php bin/console migrate

# Clear template cache
docker-compose exec -it php composer clear-cache

# Access database CLI
docker-compose exec -it db mariadb -u root -p

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

Extend the `AdminController` base class to quickly create CRUD interfaces:

```php
<?php

namespace App\Http\Controllers\Admin;

use Echo\Framework\Http\AdminController;
use Echo\Framework\Routing\Group;

#[Group(path_prefix: "/products", name_prefix: "products")]
class ProductsController extends AdminController
{
    public function __construct()
    {
        // Define table columns for listing
        $this->table_columns = [
            "ID" => "id",
            "Name" => "name",
            "Price" => "price",
            "Stock" => "stock",
            "Created" => "created_at",
        ];

        // Define searchable columns
        $this->search_columns = ["Name"];

        // Define form fields
        $this->form_columns = [
            "Name" => "name",
            "Description" => "description",
            "Price" => "price",
            "Stock" => "stock",
        ];

        // Define form controls
        $this->form_controls = [
            "name" => "input",
            "description" => "textarea",
            "price" => "number",
            "stock" => "number",
        ];

        // Define validation rules
        $this->validation_rules = [
            "name" => ["required"],
            "price" => ["required", "numeric"],
            "stock" => ["required", "numeric"],
        ];

        parent::__construct("products"); // table name
    }
}
```

The `AdminController` automatically provides:
- Paginated listing with sorting
- Search functionality
- Create/Edit forms
- Delete operations
- Export to CSV
- Filter dropdowns and quick links
- Modal-based editing via HTMX
- Automatic validation

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
│   │   └── Admin/          # Admin system components
│   │       └── Widgets/    # Dashboard widgets
│   └── Interface/          # Contracts/interfaces
├── config/                 # Configuration files
├── migrations/             # Database migrations
├── templates/              # Twig templates
│   └── admin/              # Admin panel templates
├── public/                 # Web root
│   └── index.php           # Application entry point
├── bin/
│   └── console             # CLI entry point
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

## Development Status

**WIP:** This project is a personal endeavor that will eventually serve as the backend for my website. Contributions and feedback are welcome!

## License

Echo is licensed under the MIT License. See [LICENSE](LICENSE) for more details.
