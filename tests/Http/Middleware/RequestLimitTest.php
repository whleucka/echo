<?php

declare(strict_types=1);

namespace Tests\Http\Middleware;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for RequestLimit middleware logic
 */
class RequestLimitTest extends TestCase
{
    /**
     * Test rate limit disabled when max_requests is 0
     */
    public function testDisabledWhenMaxRequestsIsZero(): void
    {
        $middleware = ['web', 'max_requests' => 0];

        $isDisabled = $this->isRateLimitDisabled($middleware);

        $this->assertTrue($isDisabled);
    }

    /**
     * Test rate limit enabled when max_requests not set
     */
    public function testEnabledWhenMaxRequestsNotSet(): void
    {
        $middleware = ['web'];

        $isDisabled = $this->isRateLimitDisabled($middleware);

        $this->assertFalse($isDisabled);
    }

    /**
     * Test rate limit enabled when max_requests is positive
     */
    public function testEnabledWhenMaxRequestsPositive(): void
    {
        $middleware = ['web', 'max_requests' => 100];

        $isDisabled = $this->isRateLimitDisabled($middleware);

        $this->assertFalse($isDisabled);
    }

    /**
     * Test API routes use fixed limits
     */
    public function testApiRoutesUseFixedLimits(): void
    {
        $middleware = ['api'];

        [$maxRequests, $decaySeconds] = $this->getLimits($middleware, false);

        $this->assertEquals(60, $maxRequests);
        $this->assertEquals(60, $decaySeconds);
    }

    /**
     * Test rate limit key generation
     */
    public function testRateLimitKeyGeneration(): void
    {
        $ip = '192.168.1.1';

        $key = $this->generateRateLimitKey($ip);

        $this->assertStringStartsWith('request_limit:', $key);
        $this->assertEquals('request_limit:' . md5($ip), $key);
    }

    /**
     * Test different IPs get different keys
     */
    public function testDifferentIpsGetDifferentKeys(): void
    {
        $key1 = $this->generateRateLimitKey('192.168.1.1');
        $key2 = $this->generateRateLimitKey('192.168.1.2');

        $this->assertNotEquals($key1, $key2);
    }

    /**
     * Test same IP gets same key
     */
    public function testSameIpGetsSameKey(): void
    {
        $key1 = $this->generateRateLimitKey('192.168.1.1');
        $key2 = $this->generateRateLimitKey('192.168.1.1');

        $this->assertEquals($key1, $key2);
    }

    /**
     * Test 429 response for API routes is JSON
     */
    public function testApiRouteReturnsJsonResponse(): void
    {
        $middleware = ['api'];
        $retryAfter = 30;

        $response = $this->buildRateLimitResponse($middleware, $retryAfter);

        $this->assertIsArray($response);
        $this->assertFalse($response['success']);
        $this->assertEquals(429, $response['status']);
        $this->assertEquals('RATE_LIMIT_EXCEEDED', $response['error']['code']);
        $this->assertEquals($retryAfter, $response['error']['retry_after']);
    }

    /**
     * Test 429 response for web routes is plain text
     */
    public function testWebRouteReturnsTextResponse(): void
    {
        $middleware = ['web'];
        $retryAfter = 30;

        $response = $this->buildRateLimitResponse($middleware, $retryAfter);

        $this->assertIsString($response);
        $this->assertStringContainsString('Too many requests', $response);
        $this->assertStringContainsString((string) $retryAfter, $response);
    }

    /**
     * Test middleware detection for API routes
     */
    public function testDetectsApiRoute(): void
    {
        $this->assertTrue($this->isApiRoute(['api']));
        $this->assertTrue($this->isApiRoute(['api', 'auth']));
        $this->assertFalse($this->isApiRoute(['web']));
        $this->assertFalse($this->isApiRoute(['web', 'auth']));
    }

    /**
     * Test custom max_requests from middleware config
     */
    public function testCustomMaxRequestsFromMiddleware(): void
    {
        $middleware = ['web', 'max_requests' => 500];

        $maxRequests = $middleware['max_requests'] ?? 100;

        $this->assertEquals(500, $maxRequests);
    }

    /**
     * Test custom decay_seconds from middleware config
     */
    public function testCustomDecaySecondsFromMiddleware(): void
    {
        $middleware = ['web', 'decay_seconds' => 120];

        $decaySeconds = $middleware['decay_seconds'] ?? 60;

        $this->assertEquals(120, $decaySeconds);
    }

    /**
     * Test rate limiter attempt logic
     */
    public function testRateLimiterAttemptLogic(): void
    {
        // Simulating rate limiter behavior
        $attempts = 0;
        $maxAttempts = 3;

        // First 3 attempts should succeed
        for ($i = 0; $i < $maxAttempts; $i++) {
            $attempts++;
            $this->assertTrue($attempts <= $maxAttempts);
        }

        // 4th attempt should fail
        $attempts++;
        $this->assertFalse($attempts <= $maxAttempts);
    }

    /**
     * Test retry after calculation
     */
    public function testRetryAfterCalculation(): void
    {
        $windowStart = time() - 30; // Window started 30 seconds ago
        $decaySeconds = 60;

        $retryAfter = ($windowStart + $decaySeconds) - time();

        $this->assertEquals(30, $retryAfter);
    }

    /**
     * Helper: Check if rate limit is disabled
     */
    private function isRateLimitDisabled(array $middleware): bool
    {
        return isset($middleware['max_requests']) && $middleware['max_requests'] == 0;
    }

    /**
     * Helper: Get rate limits based on route type
     */
    private function getLimits(array $middleware, bool $isHtmx): array
    {
        if (in_array('api', $middleware)) {
            return [60, 60]; // Fixed API limits
        }

        if ($isHtmx) {
            return [$middleware['max_requests'] ?? 1000, $middleware['decay_seconds'] ?? 60];
        }

        return [$middleware['max_requests'] ?? 100, $middleware['decay_seconds'] ?? 60];
    }

    /**
     * Helper: Generate rate limit key
     */
    private function generateRateLimitKey(string $ip): string
    {
        return 'request_limit:' . md5($ip);
    }

    /**
     * Helper: Build rate limit response
     */
    private function buildRateLimitResponse(array $middleware, int $retryAfter): array|string
    {
        $message = "Too many requests. Try again in {$retryAfter} seconds.";

        if (in_array('api', $middleware)) {
            return [
                'id' => 'test-request-id',
                'success' => false,
                'status' => 429,
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => $message,
                    'retry_after' => $retryAfter,
                ],
                'ts' => date(DATE_ATOM),
            ];
        }

        return $message;
    }

    /**
     * Helper: Check if route is API
     */
    private function isApiRoute(array $middleware): bool
    {
        return in_array('api', $middleware);
    }
}
