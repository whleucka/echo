<?php

namespace Echo\Framework\Cache;

/**
 * File Cache Implementation
 *
 * Simple file-based cache for environments without Redis.
 */
class FileCache implements CacheInterface
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = rtrim($path ?? config('paths.cache'), '/') . '/';

        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    /**
     * Get a value from cache
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return $default;
        }

        $data = $this->readFile($file);

        if ($data === null) {
            return $default;
        }

        // Check expiration
        if ($data['expires'] > 0 && $data['expires'] < time()) {
            $this->delete($key);
            return $default;
        }

        return $data['value'];
    }

    /**
     * Store a value in cache
     */
    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        $file = $this->getFilePath($key);
        $dir = dirname($file);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $data = [
            'value' => $value,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
            'created' => time(),
        ];

        return file_put_contents($file, serialize($data), LOCK_EX) !== false;
    }

    /**
     * Check if key exists
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Delete a key
     */
    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);

        if (file_exists($file)) {
            return unlink($file);
        }

        return true;
    }

    /**
     * Clear all cache
     */
    public function clear(): bool
    {
        $files = glob($this->path . '*.cache');

        if ($files === false) {
            return false;
        }

        foreach ($files as $file) {
            unlink($file);
        }

        return true;
    }

    /**
     * Get multiple values
     */
    public function getMany(array $keys, mixed $default = null): array
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    /**
     * Set multiple values
     */
    public function setMany(array $values, int $ttl = 0): bool
    {
        $success = true;

        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Delete multiple keys
     */
    public function deleteMany(array $keys): bool
    {
        $success = true;

        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Get or set cache value
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Get file path for cache key
     */
    private function getFilePath(string $key): string
    {
        $hash = md5($key);
        return $this->path . $hash . '.cache';
    }

    /**
     * Read and unserialize file
     */
    private function readFile(string $file): ?array
    {
        $content = @file_get_contents($file);

        if ($content === false) {
            return null;
        }

        $data = @unserialize($content);

        if ($data === false) {
            return null;
        }

        return $data;
    }

    /**
     * Clean expired cache files (call periodically)
     */
    public function gc(): int
    {
        $files = glob($this->path . '*.cache');
        $cleaned = 0;

        if ($files === false) {
            return 0;
        }

        foreach ($files as $file) {
            $data = $this->readFile($file);

            if ($data !== null && $data['expires'] > 0 && $data['expires'] < time()) {
                unlink($file);
                $cleaned++;
            }
        }

        return $cleaned;
    }
}
