<?php

namespace Echo\Framework\Routing;

class RouteCache
{
    private string $cachePath;

    public function __construct()
    {
        $this->cachePath = config('paths.root') . 'storage/cache/routes.php';
    }

    /**
     * Check if routes are cached
     */
    public function isCached(): bool
    {
        return file_exists($this->cachePath);
    }

    /**
     * Get cached routes
     */
    public function get(): array
    {
        if (!$this->isCached()) {
            return ['routes' => [], 'patterns' => []];
        }
        $cached = require $this->cachePath;
        // Handle both old format (just routes) and new format (routes + patterns)
        if (isset($cached['routes'])) {
            return $cached;
        }
        // Old format - convert to new format
        return ['routes' => $cached, 'patterns' => []];
    }

    /**
     * Get just the routes (for backward compatibility)
     */
    public function getRoutes(): array
    {
        $data = $this->get();
        return $data['routes'] ?? [];
    }

    /**
     * Get pre-compiled patterns
     */
    public function getPatterns(): array
    {
        $data = $this->get();
        return $data['patterns'] ?? [];
    }

    /**
     * Cache routes array with pre-compiled patterns
     */
    public function cache(array $routes): bool
    {
        $dir = dirname($this->cachePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Pre-compile route patterns for faster matching
        $compiled = $this->compileRoutes($routes);
        $content = '<?php return ' . var_export($compiled, true) . ';';
        return file_put_contents($this->cachePath, $content) !== false;
    }

    /**
     * Compile route patterns for faster regex matching
     * Adds _compiled metadata to routes with parameters
     */
    public function compileRoutes(array $routes): array
    {
        $compiled = [
            'routes' => $routes,
            'patterns' => [],
        ];

        foreach ($routes as $route => $methods) {
            // Only compile routes with parameters
            if (str_contains($route, '{')) {
                $pattern = preg_replace('/\{(\w+)\}/', '([A-Za-z0-9_.-]+)', $route);
                $compiled['patterns'][$route] = "#^$pattern$#";
            }
        }

        return $compiled;
    }


    /**
     * Clear route cache
     */
    public function clear(): bool
    {
        if ($this->isCached()) {
            return unlink($this->cachePath);
        }
        return true;
    }

    /**
     * Get cache file path
     */
    public function getCachePath(): string
    {
        return $this->cachePath;
    }
}
