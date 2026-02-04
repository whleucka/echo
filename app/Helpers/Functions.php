<?php

use App\Application;
use App\Models\User;
use App\Http\Kernel as HttpKernel;
use App\Console\Kernel as ConsoleKernel;
use Echo\Framework\Container\Container;
use Echo\Framework\Database\Connection;
use Echo\Framework\Database\Drivers\MariaDB;
use Echo\Framework\Database\Drivers\MySQL;
use Echo\Framework\Database\QueryBuilder;
use Echo\Framework\Http\Request;
use Echo\Framework\Routing\Router;
use Echo\Framework\Session\Session;
use Echo\Interface\Http\Request as HttpRequest;
use Echo\Interface\Routing\Router as RoutingRouter;

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

function user()
{
    $uuid = session()->get("user_uuid");
    return $uuid 
        ? User::where("uuid", $uuid)->get()
        : false;
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
 * Get PDO DB
 */
function db()
{
    $root_dir = config("paths.root");
    $driver = config("db.driver");
    $driver_class = match($driver) {
        'mysql' => MySQL::class,
        'mariadb' => MariaDB::class,
    };
    $exists = file_exists($root_dir . '.env');
    if ($exists) {
        $db_driver = container()->get($driver_class);
        return Connection::getInstance($db_driver);
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
function router(): RoutingRouter
{
    return container()->get(Router::class);
}

/**
 * Get http request
 */
function request(): HttpRequest
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
 * Get application config
 */
function config(string $name): mixed
{
    static $cache = [];

    $name_split = explode(".", $name);
    $file = strtolower($name_split[0]);

    // Load and cache config file
    if (!isset($cache[$file])) {
        $config_target = __DIR__ . "/../../config/" . $file . ".php";
        $cache[$file] = is_file($config_target) ? require $config_target : [];
    }

    // Return full config if no nested key
    if (count($name_split) === 1) {
        return $cache[$file];
    }

    // Traverse nested keys
    $value = $cache[$file];
    for ($i = 1; $i < count($name_split); $i++) {
        $key = $name_split[$i];
        if (!is_array($value) || !array_key_exists($key, $value)) {
            return null;
        }
        $value = $value[$key];
    }

    // Handle string booleans from env
    if ($value === "true") return true;
    if ($value === "false") return false;

    return $value;
}
