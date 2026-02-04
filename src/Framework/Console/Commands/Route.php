<?php

namespace Echo\Framework\Console\Commands;

use Echo\Framework\Routing\Collector;
use Echo\Framework\Routing\RouteCache;

/**
 * Route management commands
 */
class Route extends \ConsoleKit\Command
{
    /**
     * Cache all routes
     */
    public function executeCache(array $args, array $options = []): void
    {
        $this->writeln("Caching routes...");

        // Collect routes from controllers
        $collector = new Collector();
        $controllerPath = config('paths.controllers');

        if (!is_dir($controllerPath)) {
            $this->writeerr("Controllers directory not found: $controllerPath" . PHP_EOL);
            return;
        }

        $files = recursiveFiles($controllerPath);
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $className = $this->getClassNameFromFile($file->getPathname());
                if ($className && class_exists($className)) {
                    try {
                        $collector->register($className);
                    } catch (\Exception $e) {
                        // Skip classes that can't be registered
                    }
                }
            }
        }

        $routes = $collector->getRoutes();
        $cache = new RouteCache();

        if ($cache->cache($routes)) {
            $count = 0;
            foreach ($routes as $methods) {
                $count += count($methods);
            }
            $this->writeln("✓ Routes cached successfully ($count routes)");
            $this->writeln("  Cache file: " . $cache->getCachePath());
        } else {
            $this->writeerr("Failed to cache routes" . PHP_EOL);
        }
    }

    /**
     * Clear route cache
     */
    public function executeClear(array $args, array $options = []): void
    {
        $cache = new RouteCache();

        if (!$cache->isCached()) {
            $this->writeln("Route cache is already empty.");
            return;
        }

        if ($cache->clear()) {
            $this->writeln("✓ Route cache cleared successfully");
        } else {
            $this->writeerr("Failed to clear route cache" . PHP_EOL);
        }
    }

    /**
     * List all registered routes
     */
    public function executeList(array $args, array $options = []): void
    {
        $cache = new RouteCache();

        if ($cache->isCached()) {
            $routes = $cache->get();
            $this->writeln("Routes (from cache):");
        } else {
            // Collect routes fresh
            $collector = new Collector();
            $controllerPath = config('paths.controllers');

            $files = recursiveFiles($controllerPath);
            foreach ($files as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $className = $this->getClassNameFromFile($file->getPathname());
                    if ($className && class_exists($className)) {
                        try {
                            $collector->register($className);
                        } catch (\Exception $e) {
                            // Skip
                        }
                    }
                }
            }
            $routes = $collector->getRoutes();
            $this->writeln("Routes (not cached):");
        }

        if (empty($routes)) {
            $this->writeln("  No routes found.");
            return;
        }

        foreach ($routes as $path => $methods) {
            foreach ($methods as $method => $route) {
                $middleware = !empty($route['middleware']) ? '[' . implode(', ', $route['middleware']) . ']' : '';
                $this->writeln(sprintf(
                    "  %-7s %-40s -> %s::%s %s",
                    strtoupper($method),
                    $path,
                    $this->getShortClassName($route['controller']),
                    $route['method'],
                    $middleware
                ));
            }
        }
    }

    /**
     * Extract class name from PHP file
     */
    private function getClassNameFromFile(string $filepath): ?string
    {
        $contents = file_get_contents($filepath);

        $namespace = null;
        $class = null;

        if (preg_match('/namespace\s+([^;]+);/', $contents, $matches)) {
            $namespace = $matches[1];
        }

        if (preg_match('/class\s+(\w+)/', $contents, $matches)) {
            $class = $matches[1];
        }

        if ($namespace && $class) {
            return $namespace . '\\' . $class;
        }

        return $class;
    }

    /**
     * Get short class name without namespace
     */
    private function getShortClassName(string $className): string
    {
        $parts = explode('\\', $className);
        return end($parts);
    }
}
