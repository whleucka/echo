# DATABASE Documentation

This document provides information on database interactions and management within the Echo PHP framework.

## Database/ORM

Echo utilizes a custom ORM with a query builder. Models should extend `Echo\Framework\Database\Model`.

Examples:

```php
User::find($id);
User::where('email', $email)->first();
User::create(['name' => $name, 'email' => $email]);
```

## Migrations

Database migrations are defined using a Blueprint pattern within the `migrations/` directory.

## Validation (Database-Related)

The framework includes validation built into the Controller base class, which can include database-specific rules.

Example:

```php
$data = $this->validate([
    'email' => 'required|email|unique:users', // 'unique:users' is database-related
    'password' => 'required|min_length:8'
]);
```

Database-related validation rules include: `unique`.