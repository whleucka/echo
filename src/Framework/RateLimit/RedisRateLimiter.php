<?php

namespace Echo\Framework\RateLimit;

use Echo\Framework\Redis\RedisManager;
use Redis;

/**
 * Redis Rate Limiter
 *
 * Implements rate limiting using Redis with atomic operations.
 * This provides accurate rate limiting across multiple servers.
 */
class RedisRateLimiter implements RateLimiter
{
    private Redis $redis;

    public function __construct(?RedisManager $manager = null)
    {
        $manager = $manager ?? RedisManager::getInstance();
        $this->redis = $manager->connection('rate_limit');
    }

    /**
     * Attempt to perform an action
     */
    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $current = $this->redis->incr($key);

        // Set expiry on first attempt
        if ($current === 1) {
            $this->redis->expire($key, $decaySeconds);
        }

        return $current <= $maxAttempts;
    }

    /**
     * Get remaining attempts
     */
    public function remaining(string $key, int $maxAttempts): int
    {
        $current = (int) $this->redis->get($key);
        return max(0, $maxAttempts - $current);
    }

    /**
     * Get seconds until reset
     */
    public function retryAfter(string $key): int
    {
        $ttl = $this->redis->ttl($key);
        return $ttl > 0 ? $ttl : 0;
    }

    /**
     * Clear rate limit
     */
    public function clear(string $key): void
    {
        $this->redis->del($key);
    }

    /**
     * Get current attempts
     */
    public function attempts(string $key): int
    {
        return (int) $this->redis->get($key);
    }
}
