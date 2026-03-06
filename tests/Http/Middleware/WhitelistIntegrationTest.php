<?php

declare(strict_types=1);

namespace Tests\Http\Middleware;

use Closure;
use Echo\Framework\Http\Middleware\Whitelist;
use Echo\Framework\Http\Request;
use Echo\Framework\Http\Response;
use Echo\Framework\Http\ResponseInterface;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for Whitelist middleware
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class WhitelistIntegrationTest extends TestCase
{
    private Whitelist $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new Whitelist();
    }

    private function createRequest(string $ip = '127.0.0.1'): Request
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = $ip;
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

    public function testPassesThroughWhenWhitelistEmpty(): void
    {
        // Default config has empty whitelist
        $request = $this->createRequest('192.168.1.100');
        $response = $this->middleware->handle($request, $this->createNextHandler());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testNextHandlerCalledWhenWhitelistEmpty(): void
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
