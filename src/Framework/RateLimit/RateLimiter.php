<?php

namespace Echo\Framework\RateLimit;

/**
 * Rate Limiter Interface
 *
 * Defines the contract for rate limiting implementations.
 */
interface RateLimiter
{
    /**
     * Attempt to perform an action, returns true if allowed
     */
    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool;

    /**
     * Get remaining attempts for a key
     */
    public function remaining(string $key, int $maxAttempts): int;

    /**
     * Get seconds until the rate limit resets
     */
    public function retryAfter(string $key): int;

    /**
     * Clear rate limit for a key
     */
    public function clear(string $key): void;

    /**
     * Get current attempt count
     */
    public function attempts(string $key): int;
}
