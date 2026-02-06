<?php

namespace Echo\Framework\RateLimit;

use Echo\Framework\Session\Session;

/**
 * Session Rate Limiter
 *
 * Fallback rate limiter using PHP sessions.
 * Note: This is less accurate than Redis and doesn't work across servers.
 */
class SessionRateLimiter implements RateLimiter
{
    private Session $session;

    public function __construct()
    {
        $this->session = Session::getInstance();
    }

    /**
     * Attempt to perform an action
     */
    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $data = $this->session->get($key);

        // Initialize if not exists or expired
        if (!$data || (time() - $data['timestamp']) > $decaySeconds) {
            $data = [
                'count' => 0,
                'timestamp' => time(),
            ];
        }

        $data['count']++;
        $this->session->set($key, $data);

        return $data['count'] <= $maxAttempts;
    }

    /**
     * Get remaining attempts
     */
    public function remaining(string $key, int $maxAttempts): int
    {
        $data = $this->session->get($key);
        if (!$data) {
            return $maxAttempts;
        }
        return max(0, $maxAttempts - $data['count']);
    }

    /**
     * Get seconds until reset
     */
    public function retryAfter(string $key): int
    {
        $data = $this->session->get($key);
        if (!$data) {
            return 0;
        }
        // We don't track decay_seconds in session, estimate 60 seconds
        $elapsed = time() - $data['timestamp'];
        return max(0, 60 - $elapsed);
    }

    /**
     * Clear rate limit
     */
    public function clear(string $key): void
    {
        $this->session->delete($key);
    }

    /**
     * Get current attempts
     */
    public function attempts(string $key): int
    {
        $data = $this->session->get($key);
        return $data['count'] ?? 0;
    }
}
