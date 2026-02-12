<?php

declare(strict_types=1);

namespace Tests\Http\Middleware;

use Closure;
use Echo\Framework\Http\Middleware\BearerAuth;
use Echo\Framework\Http\Request;
use Echo\Framework\Http\Response;
use Echo\Framework\Session\Session;
use Echo\Framework\Http\ResponseInterface;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for BearerAuth middleware
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class BearerAuthIntegrationTest extends TestCase
{
    private BearerAuth $bearerAuth;
    private Session $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bearerAuth = new BearerAuth();
        $this->session = Session::getInstance();

        // Clear any existing auth state
        unset($_SERVER['HTTP_AUTHORIZATION']);
        unset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }

    protected function tearDown(): void
    {
        $this->session->destroy();
        unset($_SERVER['HTTP_AUTHORIZATION']);
        unset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        parent::tearDown();
    }

    private function createRequest(array $route = []): Request
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $request = new Request();

        // Set default route with middleware
        $defaultRoute = [
            'middleware' => $route['middleware'] ?? ['api'],
            'controller' => 'TestController',
            'method' => 'index',
        ];
        $request->setAttribute('route', array_merge($defaultRoute, $route));
        $request->setAttribute('request_id', 'test-request-id');

        return $request;
    }

    private function createNextHandler(): Closure
    {
        return function ($request): ResponseInterface {
            return new Response('OK', 200);
        };
    }

    /**
     * Test passes through for non-API routes
     */
    public function testPassesThroughForNonApiRoutes(): void
    {
        $request = $this->createRequest(['middleware' => ['web']]);
        $response = $this->bearerAuth->handle($request, $this->createNextHandler());

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test passes through when session auth exists
     */
    public function testPassesThroughWithSessionAuth(): void
    {
        $this->session->set('user_uuid', 'existing-uuid');

        $request = $this->createRequest(['middleware' => ['api']]);
        $response = $this->bearerAuth->handle($request, $this->createNextHandler());

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test passes through when no Authorization header
     */
    public function testPassesThroughWithNoAuthHeader(): void
    {
        $request = $this->createRequest(['middleware' => ['api']]);
        $response = $this->bearerAuth->handle($request, $this->createNextHandler());

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test returns 401 for invalid header format
     */
    public function testReturns401ForInvalidHeaderFormat(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic abc123';

        $request = $this->createRequest(['middleware' => ['api']]);
        $response = $this->bearerAuth->handle($request, $this->createNextHandler());

        $this->assertEquals(401, $response->getStatusCode());
    }

    /**
     * Test returns 401 for invalid token (requires DB)
     * When DB is available, validates that invalid tokens return 401
     */
    public function testReturns401ForInvalidToken(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer invalid_token_12345';

        $request = $this->createRequest(['middleware' => ['api']]);

        try {
            $response = $this->bearerAuth->handle($request, $this->createNextHandler());
            // Will return 401 because token doesn't exist in DB
            $this->assertEquals(401, $response->getStatusCode());
        } catch (\Error $e) {
            // DB not available in CI - skip
            $this->markTestSkipped('Database not available in test environment');
        }
    }

    /**
     * Test reads from HTTP_AUTHORIZATION server variable
     */
    public function testReadsFromHttpAuthorizationHeader(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer test_token';

        $request = $this->createRequest(['middleware' => ['api']]);

        try {
            $response = $this->bearerAuth->handle($request, $this->createNextHandler());
            // Token won't be valid, but proves header was read
            $this->assertEquals(401, $response->getStatusCode());
        } catch (\Error $e) {
            // DB not available in CI - skip
            $this->markTestSkipped('Database not available in test environment');
        }
    }

    /**
     * Test reads from REDIRECT_HTTP_AUTHORIZATION server variable
     */
    public function testReadsFromRedirectHttpAuthorizationHeader(): void
    {
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer test_token';

        $request = $this->createRequest(['middleware' => ['api']]);

        try {
            $response = $this->bearerAuth->handle($request, $this->createNextHandler());
            // Token won't be valid, but proves header was read
            $this->assertEquals(401, $response->getStatusCode());
        } catch (\Error $e) {
            // DB not available in CI - skip
            $this->markTestSkipped('Database not available in test environment');
        }
    }

    /**
     * Test activates for 'bearer' middleware
     */
    public function testActivatesForBearerMiddleware(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer test_token';

        $request = $this->createRequest(['middleware' => ['bearer']]);

        try {
            $response = $this->bearerAuth->handle($request, $this->createNextHandler());
            // Token won't be valid, but proves middleware activated
            $this->assertEquals(401, $response->getStatusCode());
        } catch (\Error $e) {
            // DB not available in CI - skip
            $this->markTestSkipped('Database not available in test environment');
        }
    }

    /**
     * Test empty Authorization header passes through
     * Empty string doesn't match Bearer regex, so passes to next handler
     */
    public function testEmptyAuthHeaderPassesThrough(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = '';

        $request = $this->createRequest(['middleware' => ['api']]);
        $response = $this->bearerAuth->handle($request, $this->createNextHandler());

        // Empty header doesn't match Bearer regex, passes through
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test Bearer-only header (no token) returns 401
     */
    public function testBearerOnlyHeaderReturns401(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer';

        $request = $this->createRequest(['middleware' => ['api']]);
        $response = $this->bearerAuth->handle($request, $this->createNextHandler());

        $this->assertEquals(401, $response->getStatusCode());
    }

    /**
     * Test next handler is called when auth not required
     */
    public function testNextHandlerCalledWhenAuthNotRequired(): void
    {
        $called = false;
        $nextHandler = function ($request) use (&$called): ResponseInterface {
            $called = true;
            return new Response('OK', 200);
        };

        $request = $this->createRequest(['middleware' => ['web']]);
        $this->bearerAuth->handle($request, $nextHandler);

        $this->assertTrue($called);
    }

    /**
     * Test next handler not called on auth failure
     */
    public function testNextHandlerNotCalledOnAuthFailure(): void
    {
        $called = false;
        $nextHandler = function ($request) use (&$called): ResponseInterface {
            $called = true;
            return new Response('OK', 200);
        };

        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic invalid';

        $request = $this->createRequest(['middleware' => ['api']]);
        $this->bearerAuth->handle($request, $nextHandler);

        $this->assertFalse($called);
    }
}
