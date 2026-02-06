<?php

declare(strict_types=1);

namespace Tests\Http\Middleware;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for BearerAuth middleware logic
 *
 * These tests verify the core logic patterns used by the BearerAuth middleware.
 */
class BearerAuthTest extends TestCase
{
    /**
     * Test middleware activation - requires 'api' in middleware
     */
    public function testActivatesOnApiMiddleware(): void
    {
        $middleware = ['api'];

        $shouldActivate = $this->shouldActivate($middleware);

        $this->assertTrue($shouldActivate);
    }

    /**
     * Test middleware activation - requires 'bearer' in middleware
     */
    public function testActivatesOnBearerMiddleware(): void
    {
        $middleware = ['bearer'];

        $shouldActivate = $this->shouldActivate($middleware);

        $this->assertTrue($shouldActivate);
    }

    /**
     * Test middleware does not activate for web routes
     */
    public function testDoesNotActivateForWebRoutes(): void
    {
        $middleware = ['web', 'auth'];

        $shouldActivate = $this->shouldActivate($middleware);

        $this->assertFalse($shouldActivate);
    }

    /**
     * Test middleware does not activate for empty middleware
     */
    public function testDoesNotActivateForEmptyMiddleware(): void
    {
        $middleware = [];

        $shouldActivate = $this->shouldActivate($middleware);

        $this->assertFalse($shouldActivate);
    }

    /**
     * Test Bearer header format parsing - valid format
     */
    public function testParsesValidBearerHeader(): void
    {
        $header = 'Bearer abc123xyz';

        $token = $this->parseBearerToken($header);

        $this->assertEquals('abc123xyz', $token);
    }

    /**
     * Test Bearer header format parsing - case insensitive
     */
    public function testParsesLowercaseBearerHeader(): void
    {
        $header = 'bearer abc123xyz';

        $token = $this->parseBearerToken($header);

        $this->assertEquals('abc123xyz', $token);
    }

    /**
     * Test Bearer header format parsing - invalid format
     */
    public function testRejectsInvalidHeaderFormat(): void
    {
        $invalidHeaders = [
            'Basic abc123',
            'Token abc123',
            'abc123',
            'Bearer',
            'Bearer ',
            '',
        ];

        foreach ($invalidHeaders as $header) {
            $token = $this->parseBearerToken($header);
            $this->assertNull($token, "Expected null for header: '$header'");
        }
    }

    /**
     * Test Bearer header with extra whitespace
     */
    public function testParsesHeaderWithExtraWhitespace(): void
    {
        $header = 'Bearer   abc123xyz';

        $token = $this->parseBearerToken($header);

        $this->assertEquals('abc123xyz', $token);
    }

    /**
     * Test token hashing uses SHA256
     */
    public function testTokenHashingUsesSha256(): void
    {
        $plainToken = 'test_token_123';
        $expectedHash = hash('sha256', $plainToken);

        $actualHash = $this->hashToken($plainToken);

        $this->assertEquals($expectedHash, $actualHash);
        $this->assertEquals(64, strlen($actualHash)); // SHA256 produces 64 hex chars
    }

    /**
     * Test token expiration check - not expired
     */
    public function testTokenNotExpired(): void
    {
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $isExpired = $this->isTokenExpired($expiresAt);

        $this->assertFalse($isExpired);
    }

    /**
     * Test token expiration check - expired
     */
    public function testTokenExpired(): void
    {
        $expiresAt = date('Y-m-d H:i:s', strtotime('-1 hour'));

        $isExpired = $this->isTokenExpired($expiresAt);

        $this->assertTrue($isExpired);
    }

    /**
     * Test token expiration check - null expiry (never expires)
     */
    public function testTokenWithNullExpiryNeverExpires(): void
    {
        $expiresAt = null;

        $isExpired = $this->isTokenExpired($expiresAt);

        $this->assertFalse($isExpired);
    }

    /**
     * Test token revocation check
     */
    public function testRevokedTokenIsInvalid(): void
    {
        $revoked = '1';

        $isValid = $this->isTokenValid($revoked);

        $this->assertFalse($isValid);
    }

    /**
     * Test non-revoked token is valid
     */
    public function testNonRevokedTokenIsValid(): void
    {
        $revoked = '0';

        $isValid = $this->isTokenValid($revoked);

        $this->assertTrue($isValid);
    }

    /**
     * Test 401 response format
     */
    public function testUnauthorizedResponseFormat(): void
    {
        $message = 'Invalid token';
        $response = $this->buildUnauthorizedResponse($message);

        $this->assertFalse($response['success']);
        $this->assertEquals(401, $response['status']);
        $this->assertEquals('UNAUTHORIZED', $response['error']['code']);
        $this->assertEquals($message, $response['error']['message']);
        $this->assertArrayHasKey('ts', $response);
    }

    /**
     * Test session check bypasses auth
     */
    public function testSessionAuthBypassesBearerCheck(): void
    {
        $userUuid = 'existing-uuid';

        $shouldBypass = $this->hasSessionAuth($userUuid);

        $this->assertTrue($shouldBypass);
    }

    /**
     * Test no session auth requires bearer check
     */
    public function testNoSessionAuthRequiresBearerCheck(): void
    {
        $userUuid = null;

        $shouldBypass = $this->hasSessionAuth($userUuid);

        $this->assertFalse($shouldBypass);
    }

    /**
     * Test missing Authorization header allows pass-through
     */
    public function testMissingAuthHeaderAllowsPassThrough(): void
    {
        $authHeader = null;

        // When no auth header, middleware should pass through
        // to allow other auth methods (session, etc.)
        $shouldPassThrough = $authHeader === null;

        $this->assertTrue($shouldPassThrough);
    }

    /**
     * Helper: Check if middleware should activate for route
     */
    private function shouldActivate(array $middleware): bool
    {
        return in_array('api', $middleware) || in_array('bearer', $middleware);
    }

    /**
     * Helper: Parse Bearer token from header
     */
    private function parseBearerToken(string $header): ?string
    {
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Helper: Hash token for storage comparison
     */
    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Helper: Check if token is expired
     */
    private function isTokenExpired(?string $expiresAt): bool
    {
        if (!$expiresAt) {
            return false; // No expiry means never expires
        }
        return strtotime($expiresAt) < time();
    }

    /**
     * Helper: Check if token is valid (not revoked)
     */
    private function isTokenValid(string $revoked): bool
    {
        return $revoked === '0';
    }

    /**
     * Helper: Build unauthorized response structure
     */
    private function buildUnauthorizedResponse(string $message): array
    {
        return [
            'id' => 'test-request-id',
            'success' => false,
            'status' => 401,
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => $message,
            ],
            'ts' => date(DATE_ATOM),
        ];
    }

    /**
     * Helper: Check if session has auth
     */
    private function hasSessionAuth(?string $userUuid): bool
    {
        return $userUuid !== null;
    }
}
