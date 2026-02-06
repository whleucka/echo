<?php

namespace Echo\Framework\Cache;

/**
 * Cache Interface
 *
 * Defines the contract for cache implementations.
 */
interface CacheInterface
{
    /**
     * Get a value from cache
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Store a value in cache
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds (0 = forever)
     */
    public function set(string $key, mixed $value, int $ttl = 0): bool;

    /**
     * Check if key exists in cache
     */
    public function has(string $key): bool;

    /**
     * Delete a key from cache
     */
    public function delete(string $key): bool;

    /**
     * Clear all cache entries
     */
    public function clear(): bool;

    /**
     * Get multiple values at once
     *
     * @param array $keys Array of cache keys
     * @param mixed $default Default value for missing keys
     * @return array Associative array of key => value
     */
    public function getMany(array $keys, mixed $default = null): array;

    /**
     * Store multiple values at once
     *
     * @param array $values Associative array of key => value
     * @param int $ttl Time to live in seconds
     */
    public function setMany(array $values, int $ttl = 0): bool;

    /**
     * Delete multiple keys at once
     */
    public function deleteMany(array $keys): bool;

    /**
     * Get or set a cache value
     *
     * If the key exists, return the cached value.
     * If not, call the callback, cache the result, and return it.
     */
    public function remember(string $key, int $ttl, callable $callback): mixed;
}
