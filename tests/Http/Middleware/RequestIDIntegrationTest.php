<?php

declare(strict_types=1);

namespace Tests\Http\Middleware;

use Closure;
use Echo\Framework\Http\Middleware\RequestID;
use Echo\Framework\Http\Request;
use Echo\Framework\Http\Response;
use Echo\Framework\Http\ResponseInterface;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for RequestID middleware
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class RequestIDIntegrationTest extends TestCase
{
    private RequestID $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new RequestID();
    }

    private function createRequest(): Request
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $request = new Request();
        $request->setAttribute('route', [
            'middleware' => ['web'],
            'controller' => 'TestController',
            'method' => 'index',
        ]);
        return $request;
    }

    private function createNextHandler(): Closure
    {
        return function ($request): ResponseInterface {
            return new Response('OK', 200);
        };
    }

    public function testSetsRequestIdAttribute(): void
    {
        $request = $this->createRequest();
        $this->assertNull($request->getAttribute('request_id'));

        $this->middleware->handle($request, $this->createNextHandler());

        $this->assertNotNull($request->getAttribute('request_id'));
    }

    public function testRequestIdIsString(): void
    {
        $request = $this->createRequest();
        $this->middleware->handle($request, $this->createNextHandler());

        $this->assertIsString($request->getAttribute('request_id'));
    }

    public function testRequestIdIs32CharHex(): void
    {
        $request = $this->createRequest();
        $this->middleware->handle($request, $this->createNextHandler());

        $id = $request->getAttribute('request_id');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $id);
    }

    public function testEachRequestGetsUniqueId(): void
    {
        $request1 = $this->createRequest();
        $this->middleware->handle($request1, $this->createNextHandler());

        $request2 = $this->createRequest();
        $this->middleware->handle($request2, $this->createNextHandler());

        $this->assertNotEquals(
            $request1->getAttribute('request_id'),
            $request2->getAttribute('request_id')
        );
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

    public function testResponsePassesThroughUnmodified(): void
    {
        $request = $this->createRequest();
        $response = $this->middleware->handle($request, $this->createNextHandler());

        $this->assertEquals(200, $response->getStatusCode());
    }
}
