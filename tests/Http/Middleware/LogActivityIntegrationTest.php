<?php

declare(strict_types=1);

namespace Tests\Http\Middleware;

use Closure;
use Echo\Framework\Http\Middleware\LogActivity;
use Echo\Framework\Http\Request;
use Echo\Framework\Http\Response;
use Echo\Framework\Http\ResponseInterface;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for LogActivity middleware
 *
 * Activity logging has moved to the ActivityListener on the ResponseSending
 * event. This middleware is now a passthrough — these tests verify it still
 * passes the request through the pipeline without interference.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class LogActivityIntegrationTest extends TestCase
{
    private LogActivity $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new LogActivity();
    }

    private function createRequest(array $route = []): Request
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $request = new Request();
        $defaultRoute = [
            'middleware' => $route['middleware'] ?? ['web'],
            'controller' => 'TestController',
            'method' => 'index',
        ];
        $request->setAttribute('route', array_merge($defaultRoute, $route));
        return $request;
    }

    private function createNextHandler(int $statusCode = 200): Closure
    {
        return function ($request) use ($statusCode): ResponseInterface {
            return new Response('OK', $statusCode);
        };
    }

    public function testNextHandlerIsCalledForWebRoutes(): void
    {
        $called = false;
        $next = function ($request) use (&$called): ResponseInterface {
            $called = true;
            return new Response('OK', 200);
        };

        $request = $this->createRequest(['middleware' => ['web']]);
        $this->middleware->handle($request, $next);

        $this->assertTrue($called);
    }

    public function testPassesThroughBenchmarkRoutes(): void
    {
        $called = false;
        $next = function ($request) use (&$called): ResponseInterface {
            $called = true;
            return new Response('OK', 200);
        };

        $request = $this->createRequest(['middleware' => ['benchmark']]);
        $response = $this->middleware->handle($request, $next);

        $this->assertTrue($called);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testPassesThroughDebugRoutes(): void
    {
        $called = false;
        $next = function ($request) use (&$called): ResponseInterface {
            $called = true;
            return new Response('OK', 200);
        };

        $request = $this->createRequest(['middleware' => ['debug']]);
        $response = $this->middleware->handle($request, $next);

        $this->assertTrue($called);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testResponsePassesThrough(): void
    {
        $request = $this->createRequest();
        $response = $this->middleware->handle($request, $this->createNextHandler());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testPreservesResponseStatusCode(): void
    {
        $request = $this->createRequest();
        $response = $this->middleware->handle($request, $this->createNextHandler(404));

        $this->assertEquals(404, $response->getStatusCode());
    }
}
