<?php

use App\Application;
use App\Models\User;
use App\Http\Kernel as HttpKernel;
use App\Console\Kernel as ConsoleKernel;
use Echo\Framework\Container\Container;
use Echo\Framework\Database\Connection;
use Echo\Framework\Database\QueryBuilder;
use Echo\Framework\Http\Request;
use Echo\Framework\Routing\Router;
use Echo\Framework\Routing\RouterInterface;
use Echo\Framework\Session\Session;
use Echo\Framework\Http\RequestInterface;

function recursiveFiles(string $directory)
{

    return new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
}


/**
 * Web application
 */
function app(): Application
{
    $kernel = new HttpKernel();
    return new Application($kernel);
}

/**
 * Console application
 */
function console(): Application
{
    $kernel = new ConsoleKernel();
    return new Application($kernel);
}

function user(): ?User
{
    $uuid = session()->get("user_uuid");
    return $uuid
        ? User::where("uuid", $uuid)->get()
        : null;
}

/**
 * Get application container
 */
function container()
{
    return Container::getInstance();
}

function qb()
{
    return new QueryBuilder;
}

function twig()
{
    $twig = container()->get(\Twig\Environment::class);
    return $twig;
}

/**
 * Get PDO DB connection (resolved from container)
 */
function db(): ?Connection
{
    $root_dir = config("paths.root");
    $exists = file_exists($root_dir . '.env');

    if ($exists) {
        return container()->get(Connection::class);
    }

    return null;
}

/**
 * Get app session
 */
function session()
{
    return Session::getInstance();
}

/**
 * Get web router
 */
function router(): RouterInterface
{
    return container()->get(Router::class);
}

/**
 * Get http request
 */
function request(): RequestInterface
{
    return container()->get(Request::class);
}

/**
 * Get env value
 */
function env(string $name, mixed $default = null)
{
    static $loaded = false;

    if (!$loaded) {
        $root = __DIR__ . "/../../";
        if (file_exists($root . '.env')) {
            $dotenv = Dotenv\Dotenv::createImmutable($root);
            $dotenv->safeLoad();
        }
        $loaded = true;
    }

    return $_ENV[$name] ?? $_SERVER[$name] ?? $default;
}

/**
 * Get route uri
 */
function uri(string $name, ...$params): ?string
{
    return router()->searchUri($name, ...$params);
}


/**
 * Dump
 */
function dump(mixed $payload): void
{
    printf("<pre>%s</pre>", print_r($payload, true));
}

/**
 * Dump & die
 */
function dd(mixed $payload): void
{
    dump($payload);
    die;
}

/**
 * Get logger instance
 */
function logger(): \Echo\Framework\Logging\Logger
{
    return \Echo\Framework\Logging\Logger::getInstance();
}

/**
 * Get profiler instance (null if debug mode is off)
 */
function profiler(): ?\Echo\Framework\Debug\Profiler
{
    if (!config('app.debug')) {
        return null;
    }
    return \Echo\Framework\Debug\Profiler::getInstance();
}

/**
 * Get Redis manager instance
 */
function redis(): \Echo\Framework\Redis\RedisManager
{
    return \Echo\Framework\Redis\RedisManager::getInstance();
}

/**
 * Get cache instance
 */
function cache(): \Echo\Framework\Cache\CacheInterface
{
    static $cache = null;

    if ($cache === null) {
        $driver = config('cache.driver') ?? 'file';

        if ($driver === 'redis') {
            try {
                if (redis()->isAvailable()) {
                    $cache = new \Echo\Framework\Cache\RedisCache();
                } else {
                    $cache = new \Echo\Framework\Cache\FileCache();
                }
            } catch (\Throwable) {
                $cache = new \Echo\Framework\Cache\FileCache();
            }
        } else {
            $cache = new \Echo\Framework\Cache\FileCache();
        }
    }

    return $cache;
}

/**
 * Create a redirect response (handles HTMX automatically)
 */
function redirect(string $url, int $code = 302): \Echo\Framework\Http\RedirectResponse
{
    return new \Echo\Framework\Http\RedirectResponse($url, $code);
}

/**
 * Get crypto instance
 */
function crypto(): \Echo\Framework\Crypto\Crypto
{
    static $instance = null;
    if ($instance === null) {
        $instance = new \Echo\Framework\Crypto\Crypto();
    }
    return $instance;
}

/**
 * Get mailer instance
 */
function mailer(): \Echo\Framework\Mail\Mailer
{
    return container()->get(\Echo\Framework\Mail\Mailer::class);
}

/**
 * Get application config
 */
function config(string $name): mixed
{
    static $files = [];
    static $resolved = [];

    // Return cached resolved value if exists
    if (array_key_exists($name, $resolved)) {
        return $resolved[$name];
    }

    $name_split = explode(".", $name);
    $file = strtolower($name_split[0]);

    // Load and cache config file
    if (!isset($files[$file])) {
        $config_target = __DIR__ . "/../../config/" . $file . ".php";
        $files[$file] = is_file($config_target) ? require $config_target : [];
    }

    // Return full config if no nested key
    if (count($name_split) === 1) {
        $resolved[$name] = $files[$file];
        return $resolved[$name];
    }

    // Traverse nested keys
    $value = $files[$file];
    for ($i = 1; $i < count($name_split); $i++) {
        $key = $name_split[$i];
        if (!is_array($value) || !array_key_exists($key, $value)) {
            $resolved[$name] = null;
            return null;
        }
        $value = $value[$key];
    }

    // Handle string booleans from env
    if ($value === "true") $value = true;
    if ($value === "false") $value = false;

    // Cache the resolved value
    $resolved[$name] = $value;
    return $value;
}
