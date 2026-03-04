# Redis & Caching

Redis is optional — everything falls back to file-based alternatives when unavailable.

## Cache API

The `cache()` helper auto-selects the Redis or file driver based on `CACHE_DRIVER` in `.env`:

```php
// Basic get/set
cache()->set('key', 'value', 3600);         // TTL in seconds
$val = cache()->get('key', 'default');       // with default fallback

// Remember pattern — fetch from cache or compute and store
$users = cache()->remember('active_users', 300, fn() =>
    User::where('active', 1)->get()
);
```

## Direct Redis

```php
redis()->connection('default')->set('key', 'value');
redis()->connection('default')->get('key');
```

## Configuration

Set in `.env`:

```
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=
CACHE_DRIVER=redis       # or 'file'
SESSION_DRIVER=redis      # or 'file'
```

## Cache Drivers

| Driver | Class | Storage |
|---|---|---|
| `redis` | `RedisCache` | Redis server |
| `file` | `FileCache` | Local filesystem |

The framework automatically falls back to `FileCache` if Redis is unavailable, even when `CACHE_DRIVER=redis`.

## Clearing Cache

```bash
./bin/console cache:clear    # clear all application caches (templates, routes, widgets)
```
