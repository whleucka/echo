# API Documentation

This document details the Application Programming Interface (API) for the Echo PHP framework.

## Routing System

Routes are defined using PHP 8 attributes on controller methods. Controllers in `app/Http/Controllers/` are auto-discovered.

```php
#[Get("/users/{id}", "user.show", ["auth"])]
public function show(string $id): string
```

Available attributes: `#[Get]`, `#[Post]`, `#[Put]`, `#[Patch]`, `#[Delete]`

Route parameters support regex patterns: `{id}` with `[0-9]`, `(blue|red)`, dots allowed.

## Middleware Stack

Defined in `app/Http/Kernel.php`, processed in order:
RequestID → Sessions → Auth → Whitelist → Blacklist → RequestLimit → CSRF