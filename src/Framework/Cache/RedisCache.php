<?php

namespace Echo\Framework\Cache;

use Echo\Framework\Redis\RedisManager;
use Redis;

/**
 * Redis Cache Implementation
 *
 * High-performance cache using Redis.
 */
class RedisCache implements CacheInterface
{
    private Redis $redis;

    public function __construct(?RedisManager $manager = null)
    {
        $manager = $manager ?? RedisManager::getInstance();
        $this->redis = $manager->connection('cache');
    }

    /**
     * Get a value from cache
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get($key);

        if ($value === false) {
            return $default;
        }

        return $this->unserialize($value);
    }

    /**
     * Store a value in cache
     */
    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        $serialized = $this->serialize($value);

        if ($ttl > 0) {
            return $this->redis->setex($key, $ttl, $serialized);
        }

        return $this->redis->set($key, $serialized);
    }

    /**
     * Check if key exists
     */
    public function has(string $key): bool
    {
        return $this->redis->exists($key) > 0;
    }

    /**
     * Delete a key
     */
    public function delete(string $key): bool
    {
        return $this->redis->del($key) > 0;
    }

    /**
     * Clear all cache
     */
    public function clear(): bool
    {
        return $this->redis->flushDB();
    }

    /**
     * Get multiple values
     */
    public function getMany(array $keys, mixed $default = null): array
    {
        if (empty($keys)) {
            return [];
        }

        $values = $this->redis->mGet($keys);
        $result = [];

        foreach ($keys as $i => $key) {
            $value = $values[$i] ?? false;
            $result[$key] = $value === false ? $default : $this->unserialize($value);
        }

        return $result;
    }

    /**
     * Set multiple values
     */
    public function setMany(array $values, int $ttl = 0): bool
    {
        if (empty($values)) {
            return true;
        }

        $serialized = [];
        foreach ($values as $key => $value) {
            $serialized[$key] = $this->serialize($value);
        }

        if ($ttl > 0) {
            // Use pipeline for atomic multi-set with TTL
            $pipe = $this->redis->multi(Redis::PIPELINE);
            foreach ($serialized as $key => $value) {
                $pipe->setex($key, $ttl, $value);
            }
            $pipe->exec();
            return true;
        }

        return $this->redis->mSet($serialized);
    }

    /**
     * Delete multiple keys
     */
    public function deleteMany(array $keys): bool
    {
        if (empty($keys)) {
            return true;
        }

        return $this->redis->del($keys) > 0;
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
     * Increment a value
     */
    public function increment(string $key, int $value = 1): int
    {
        return $this->redis->incrBy($key, $value);
    }

    /**
     * Decrement a value
     */
    public function decrement(string $key, int $value = 1): int
    {
        return $this->redis->decrBy($key, $value);
    }

    /**
     * Serialize value for storage
     */
    private function serialize(mixed $value): string
    {
        return serialize($value);
    }

    /**
     * Unserialize stored value
     */
    private function unserialize(string $value): mixed
    {
        return unserialize($value);
    }
}
