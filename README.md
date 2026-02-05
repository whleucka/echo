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

## Project Structure

```
echo/
├── app/                    # Application code
│   ├── Http/
│   │   ├── Controllers/    # Controllers (auto-discovered)
│   │   └── Kernel.php      # Middleware configuration
│   ├── Models/             # Database models
│   ├── Providers/          # Service providers
│   └── Helpers/            # Helper functions
├── src/                    # Framework code (Echo namespace)
│   ├── Framework/          # Core framework classes
│   └── Interface/          # Contracts/interfaces
├── config/                 # Configuration files
├── migrations/             # Database migrations
├── templates/              # Twig templates
├── public/                 # Web root
│   └── index.php           # Application entry point
├── bin/
│   └── console             # CLI entry point
└── tests/                  # PHPUnit tests
```

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
