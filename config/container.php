<?php

use Echo\Framework\Database\Connection;
use Echo\Framework\Database\Drivers\{ MariaDB, MySQL };
use Echo\Framework\Http\Request;
use Echo\Framework\Routing\Collector;
use Echo\Framework\Routing\Router;
use Echo\Framework\Routing\RouteCache;

// Interface imports
use Echo\Framework\Database\ConnectionInterface;
use Echo\Framework\Database\DriverInterface;
use Echo\Framework\Http\RequestInterface;
use Echo\Framework\Routing\RouterInterface;

/**
 * Helpers
 */
function getClasses(string $directory): array
{
    // Get existing classes before loading new ones
    $before = get_declared_classes();

    // Recursively find all PHP files
    $files = recursiveFiles($directory);
    foreach ($files as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            require_once $file->getPathname();
        }
    }

    // Get all declared classes after loading
    $after = get_declared_classes();

    // Return only the new classes
    return array_diff($after, $before);
}

/**
 * Get the configured database driver class
 */
function getDriverClass(): string
{
    return match(config('db.driver')) {
        'mysql' => MySQL::class,
        'mariadb' => MariaDB::class,
        default => MariaDB::class,
    };
}

return [
    // ===================
    // Interface Bindings
    // ===================
    RequestInterface::class => DI\get(Request::class),
    RouterInterface::class => DI\get(Router::class),
    DriverInterface::class => DI\get(getDriverClass()),
    ConnectionInterface::class => DI\get(Connection::class),

    // ===================
    // Concrete Bindings
    // ===================
    Request::class => DI\create()->constructor($_GET, $_POST, $_REQUEST, $_FILES, $_COOKIE, function_exists("getallheaders") ? getallheaders() : []),
    Collector::class => function() {
        $cache = new RouteCache();

        // Use cached routes if available
        if ($cache->isCached()) {
            $collector = new Collector();
            // Load routes from cache into collector
            $cachedRoutes = $cache->getRoutes();
            $reflection = new \ReflectionClass($collector);
            $prop = $reflection->getProperty('routes');
            $prop->setAccessible(true);
            $prop->setValue($collector, $cachedRoutes);
            return $collector;
        }

        // Get web controllers and register routes
        $controller_path = config("paths.controllers");
        $controllers = getClasses($controller_path);

        $collector = new Collector();
        foreach ($controllers as $controller) {
            $collector->register($controller);
        }
        return $collector;
    },
    Router::class => function($container) {
        $collector = $container->get(Collector::class);
        $router = new Router($collector);

        // Set pre-compiled patterns if available
        $cache = new RouteCache();
        if ($cache->isCached()) {
            $router->setCompiledPatterns($cache->getPatterns());
        }

        return $router;
    },
    MySQL::class => DI\create()->constructor(
        name: config("db.name"),
        username: config("db.username"),
        password: config("db.password"),
        host: config("db.host"),
        port: (int) config("db.port"),
        charset: config("db.charset"),
        options: config("db.options"),
    ),
    MariaDB::class => DI\create()->constructor(
        name: config("db.name"),
        username: config("db.username"),
        password: config("db.password"),
        host: config("db.host"),
        port: (int) config("db.port"),
        charset: config("db.charset"),
        options: config("db.options"),
    ),
    \Twig\Loader\FilesystemLoader::class => DI\create()->constructor(config("paths.templates")),
    \Twig\Environment::class => DI\create()->constructor(DI\Get(\Twig\Loader\FilesystemLoader::class), [
        "cache" => config("paths.template_cache"),
        "auto_reload" => config("app.debug"),
        "debug" => config("app.debug"),
    ]),

    // ===================
    // Database Connection (Singleton)
    // ===================
    Connection::class => function () {
        $driverClass = getDriverClass();
        $driver = new $driverClass(
            name: config("db.name"),
            username: config("db.username"),
            password: config("db.password"),
            host: config("db.host"),
            port: (int) config("db.port"),
            charset: config("db.charset"),
            options: config("db.options"),
        );
        return Connection::getInstance($driver);
    },
];
