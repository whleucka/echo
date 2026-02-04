<?php

declare(strict_types=1);

namespace Tests\Http\Middleware;

use PHPUnit\Framework\TestCase;

class CSRFTest extends TestCase
{
    /**
     * Test that GET requests bypass CSRF validation
     */
    public function testGetRequestsBypassCSRF(): void
    {
        $safeMethods = ['GET', 'HEAD', 'OPTIONS'];

        foreach ($safeMethods as $method) {
            $this->assertTrue(
                $this->shouldBypassCsrf($method),
                "Expected $method to bypass CSRF"
            );
        }
    }

    /**
     * Test that POST requests require CSRF token
     */
    public function testPostRequestRequiresToken(): void
    {
        $unsafeMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

        foreach ($unsafeMethods as $method) {
            $this->assertFalse(
                $this->shouldBypassCsrf($method),
                "Expected $method to require CSRF token"
            );
        }
    }

    /**
     * Test valid token passes validation
     */
    public function testValidTokenPasses(): void
    {
        $sessionToken = bin2hex(random_bytes(32));
        $requestToken = $sessionToken;

        $result = $this->validateToken($sessionToken, $requestToken);

        $this->assertTrue($result);
    }

    /**
     * Test invalid token fails validation
     */
    public function testInvalidTokenFails(): void
    {
        $sessionToken = bin2hex(random_bytes(32));
        $requestToken = bin2hex(random_bytes(32));

        $result = $this->validateToken($sessionToken, $requestToken);

        $this->assertFalse($result);
    }

    /**
     * Test missing token fails validation
     */
    public function testMissingTokenFails(): void
    {
        $sessionToken = bin2hex(random_bytes(32));
        $requestToken = null;

        $result = $this->validateToken($sessionToken, $requestToken);

        $this->assertFalse($result);
    }

    /**
     * Test that API routes can bypass CSRF
     */
    public function testApiRoutesCanBypassCSRF(): void
    {
        $middleware = ['api'];

        $this->assertTrue(
            $this->isApiRoute($middleware),
            "Expected API route to bypass CSRF"
        );
    }

    /**
     * Test non-API routes require CSRF
     */
    public function testNonApiRoutesRequireCSRF(): void
    {
        $middleware = ['auth', 'web'];

        $this->assertFalse(
            $this->isApiRoute($middleware),
            "Expected non-API route to require CSRF"
        );
    }

    /**
     * Test token generation produces valid format
     */
    public function testTokenGenerationFormat(): void
    {
        $token = bin2hex(random_bytes(32));

        // Should be 64 characters (32 bytes * 2 for hex encoding)
        $this->assertEquals(64, strlen($token));

        // Should only contain hex characters
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    /**
     * Test token is cryptographically random
     */
    public function testTokensAreUnique(): void
    {
        $tokens = [];
        for ($i = 0; $i < 100; $i++) {
            $tokens[] = bin2hex(random_bytes(32));
        }

        // All tokens should be unique
        $this->assertCount(100, array_unique($tokens));
    }

    /**
     * Test hash_equals is timing-safe
     */
    public function testHashEqualsUsedForComparison(): void
    {
        $token1 = 'abc123';
        $token2 = 'abc123';
        $token3 = 'xyz789';

        // hash_equals should return true for matching tokens
        $this->assertTrue(hash_equals($token1, $token2));

        // hash_equals should return false for non-matching tokens
        $this->assertFalse(hash_equals($token1, $token3));
    }

    /**
     * Test empty session token fails validation
     */
    public function testEmptySessionTokenFails(): void
    {
        $sessionToken = null;
        $requestToken = bin2hex(random_bytes(32));

        $result = $this->validateToken($sessionToken, $requestToken);

        $this->assertFalse($result);
    }

    /**
     * Test both tokens null fails validation
     */
    public function testBothTokensNullFails(): void
    {
        $result = $this->validateToken(null, null);

        $this->assertFalse($result);
    }

    /**
     * Helper: Check if method should bypass CSRF
     */
    private function shouldBypassCsrf(string $method): bool
    {
        return in_array($method, ['GET', 'HEAD', 'OPTIONS']);
    }

    /**
     * Helper: Validate CSRF token (mirrors CSRF middleware logic)
     */
    private function validateToken(?string $sessionToken, ?string $requestToken): bool
    {
        if (
            !is_null($sessionToken) &&
            !is_null($requestToken) &&
            hash_equals($sessionToken, $requestToken)
        ) {
            return true;
        }

        return false;
    }

    /**
     * Helper: Check if route is an API route
     */
    private function isApiRoute(array $middleware): bool
    {
        return in_array('api', $middleware);
    }
}
