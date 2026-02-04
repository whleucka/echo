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
            return [];
        }
        return require $this->cachePath;
    }

    /**
     * Cache routes array
     */
    public function cache(array $routes): bool
    {
        $dir = dirname($this->cachePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $content = '<?php return ' . var_export($routes, true) . ';';
        return file_put_contents($this->cachePath, $content) !== false;
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
