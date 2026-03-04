# Testing

Echo uses PHPUnit 12 for testing.

## Running Tests

```bash
# All tests
./bin/phpunit

# Single test file
./bin/phpunit tests/Http/KernelTest.php

# Single test method
./bin/phpunit --filter testMethodName

# Via composer (inside container)
docker-compose exec -it php composer test
```

## Setup

- Bootstrap: `tests/bootstrap.php` (sets `APP_ENV=testing`)
- Base class: `Tests\TestCase`
- Config: `phpunit.xml`

## Test Organization

Tests are organized by domain:

```
tests/
  Admin/         # Admin module tests
  Audit/         # Audit system tests
  Database/      # Model and QueryBuilder tests
  Http/          # Kernel, middleware, request tests
  Routing/       # Router and route cache tests
  Session/       # Session handling tests
  TestCase.php   # Base test class
  bootstrap.php  # Test bootstrap
```

## Writing Tests

```php
namespace Tests\Database;

use Tests\TestCase;

class UserModelTest extends TestCase
{
    public function testCanCreateUser(): void
    {
        $user = User::create([
            'email' => 'test@example.com',
            'first_name' => 'Test',
        ]);

        $this->assertNotNull($user->id);
        $this->assertEquals('test@example.com', $user->email);
    }
}
```
