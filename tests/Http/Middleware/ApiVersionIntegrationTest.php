<?php

declare(strict_types=1);

namespace Tests\Http\Middleware;

use Closure;
use Echo\Framework\Http\Middleware\ApiVersion;
use Echo\Framework\Http\Request;
use Echo\Framework\Http\Response;
use Echo\Framework\Http\ResponseInterface;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for ApiVersion middleware
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ApiVersionIntegrationTest extends TestCase
{
    private ApiVersion $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new ApiVersion();
    }

    private function createRequest(array $route = [], array $headers = []): Request
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $route['uri'] ?? '/api/test';

        $request = new Request(headers: $headers);
        $defaultRoute = [
            'middleware' => $route['middleware'] ?? ['api'],
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

    public function testSkipsNonApiRoutes(): void
    {
        $request = $this->createRequest(['middleware' => ['web']]);
        $response = $this->middleware->handle($request, $this->createNextHandler());

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull($request->getAttribute('api_version'));
    }

    public function testDefaultsToV1(): void
    {
        $request = $this->createRequest();
        $this->middleware->handle($request, $this->createNextHandler());

        $this->assertEquals('v1', $request->getAttribute('api_version'));
    }

    public function testParsesVersionFromAcceptHeader(): void
    {
        $request = $this->createRequest([], ['Accept' => 'application/vnd.echo.v1+json']);
        $this->middleware->handle($request, $this->createNextHandler());

        $this->assertEquals('v1', $request->getAttribute('api_version'));
    }

    public function testParsesVersionFromXApiVersionHeader(): void
    {
        $request = $this->createRequest([], ['X-Api-Version' => 'v1']);
        $this->middleware->handle($request, $this->createNextHandler());

        $this->assertEquals('v1', $request->getAttribute('api_version'));
    }

    public function testUnsupportedVersionDefaultsToV1(): void
    {
        $request = $this->createRequest([], ['X-Api-Version' => 'v99']);
        $this->middleware->handle($request, $this->createNextHandler());

        $this->assertEquals('v1', $request->getAttribute('api_version'));
    }

    public function testNextHandlerIsCalled(): void
    {
        $called = false;
        $next = function ($request) use (&$called): ResponseInterface {
            $called = true;
            return new Response('OK', 200);
        };

        $request = $this->createRequest();
        $this->middleware->handle($request, $next);

        $this->assertTrue($called);
    }

    public function testGetSupportedVersions(): void
    {
        $versions = ApiVersion::getSupportedVersions();
        $this->assertIsArray($versions);
        $this->assertContains('v1', $versions);
    }
}
