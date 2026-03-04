# Helper Functions

All helpers are defined in `app/Helpers/Functions.php` and available globally.

| Helper | Returns | Description |
|---|---|---|
| `app()` | `Application` | Web application (HttpKernel) |
| `console()` | `Application` | Console application (ConsoleKernel) |
| `user()` | `?User` | Authenticated user or null |
| `container()` | `Container` | DI container |
| `qb()` | `QueryBuilder` | New QueryBuilder instance |
| `twig()` | `Environment` | Twig template environment |
| `db()` | `?Connection` | PDO database connection |
| `session()` | `Session` | Session instance |
| `router()` | `RouterInterface` | Router instance |
| `request()` | `RequestInterface` | Current HTTP request |
| `env($key, $default)` | `mixed` | Environment variable |
| `uri($name, ...$params)` | `?string` | Named route URI (path only) |
| `url($name, ...$params)` | `?string` | Named route URL (full URL with scheme/host for cross-subdomain) |
| `dump($val)` | `void` | Pretty-print value |
| `dd($val)` | `void` | Dump and die |
| `logger()` | `Logger` | Logger instance |
| `profiler()` | `?Profiler` | Profiler (null if debug off) |
| `redis()` | `RedisManager` | Redis manager instance |
| `cache()` | `CacheInterface` | Cache (Redis or file fallback) |
| `redirect($url, $code)` | `RedirectResponse` | Redirect response (HTMX-aware) |
| `crypto()` | `Crypto` | Crypto instance |
| `event($event)` | `EventInterface` | Dispatch an event |
| `mailer()` | `Mailer` | Mailer instance |
| `config($key)` | `mixed` | Config value (e.g. `config('app.debug')`) |
| `format_bytes($bytes, $precision)` | `string` | Human-readable file size (e.g. `1.5 MB`) |
