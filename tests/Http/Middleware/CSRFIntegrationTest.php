<?php

declare(strict_types=1);

namespace Tests\Http\Middleware;

use Closure;
use Echo\Framework\Http\Middleware\CSRF;
use Echo\Framework\Http\Request;
use Echo\Framework\Http\Response;
use Echo\Framework\Session\Session;
use Echo\Framework\Http\ResponseInterface;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for CSRF middleware
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CSRFIntegrationTest extends TestCase
{
    private CSRF $csrf;
    private Session $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->csrf = new CSRF();
        $this->session = Session::getInstance();
    }

    protected function tearDown(): void
    {
        $this->session->destroy();
        parent::tearDown();
    }

    private function createRequest(string $method, array $post = [], array $route = []): Request
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_POST = $post;

        $request = new Request(post: $post);

        // Set default route with middleware
        $defaultRoute = [
            'middleware' => $route['middleware'] ?? ['web'],
            'controller' => 'TestController',
            'method' => 'index',
        ];
        $request->setAttribute('route', array_merge($defaultRoute, $route));

        return $request;
    }

    private function createNextHandler(): Closure
    {
        return function ($request): ResponseInterface {
            return new Response('OK', 200);
        };
    }

    public function testGetRequestsBypassCSRF(): void
    {
        $request = $this->createRequest('GET');
        $response = $this->csrf->handle($request, $this->createNextHandler());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testHeadRequestsBypassCSRF(): void
    {
        $request = $this->createRequest('HEAD');
        $response = $this->csrf->handle($request, $this->createNextHandler());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testOptionsRequestsBypassCSRF(): void
    {
        $request = $this->createRequest('OPTIONS');
        $response = $this->csrf->handle($request, $this->createNextHandler());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testPostRequestWithoutTokenFails(): void
    {
        $request = $this->createRequest('POST');
        $response = $this->csrf->handle($request, $this->createNextHandler());

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testPostRequestWithValidTokenPasses(): void
    {
        // First, make a GET request to set up the token
        $getRequest = $this->createRequest('GET');
        $this->csrf->handle($getRequest, $this->createNextHandler());

        // Get the token from session
        $token = $this->session->get('csrf_token');
        $this->assertNotNull($token);

        // Now make a POST request with the token
        $postRequest = $this->createRequest('POST', ['csrf_token' => $token]);
        $response = $this->csrf->handle($postRequest, $this->createNextHandler());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testPostRequestWithInvalidTokenFails(): void
    {
        // Set up a token first
        $getRequest = $this->createRequest('GET');
        $this->csrf->handle($getRequest, $this->createNextHandler());

        // Make POST with wrong token
        $postRequest = $this->createRequest('POST', ['csrf_token' => 'invalid-token']);
        $response = $this->csrf->handle($postRequest, $this->createNextHandler());

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testPutRequestRequiresToken(): void
    {
        $request = $this->createRequest('PUT');
        $response = $this->csrf->handle($request, $this->createNextHandler());

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testPatchRequestRequiresToken(): void
    {
        $request = $this->createRequest('PATCH');
        $response = $this->csrf->handle($request, $this->createNextHandler());

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testDeleteRequestRequiresToken(): void
    {
        $request = $this->createRequest('DELETE');
        $response = $this->csrf->handle($request, $this->createNextHandler());

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testApiRoutesCanBypassCSRF(): void
    {
        $request = $this->createRequest('POST', [], ['middleware' => ['api']]);
        $response = $this->csrf->handle($request, $this->createNextHandler());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testTokenIsGeneratedOnFirstRequest(): void
    {
        $this->assertNull($this->session->get('csrf_token'));

        $request = $this->createRequest('GET');
        $this->csrf->handle($request, $this->createNextHandler());

        $token = $this->session->get('csrf_token');
        $this->assertNotNull($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes hex encoded
    }

    public function testTokenTimestampIsSet(): void
    {
        $request = $this->createRequest('GET');
        $this->csrf->handle($request, $this->createNextHandler());

        $timestamp = $this->session->get('csrf_token_ts');
        $this->assertNotNull($timestamp);
        $this->assertIsInt($timestamp);
        $this->assertGreaterThan(time() - 10, $timestamp);
    }

    public function testTokenIsReusedWithinExpiry(): void
    {
        // First request generates token
        $request1 = $this->createRequest('GET');
        $this->csrf->handle($request1, $this->createNextHandler());
        $token1 = $this->session->get('csrf_token');

        // Second request should reuse same token
        $request2 = $this->createRequest('GET');
        $this->csrf->handle($request2, $this->createNextHandler());
        $token2 = $this->session->get('csrf_token');

        $this->assertEquals($token1, $token2);
    }

    public function testTokenFormatIsValid(): void
    {
        $request = $this->createRequest('GET');
        $this->csrf->handle($request, $this->createNextHandler());

        $token = $this->session->get('csrf_token');

        // Should be 64 hex characters (32 bytes)
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }
}
