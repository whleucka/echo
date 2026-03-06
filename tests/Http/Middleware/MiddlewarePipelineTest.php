<?php

declare(strict_types=1);

namespace Tests\Http\Middleware;

use Closure;
use Echo\Framework\Http\Middleware;
use Echo\Framework\Http\MiddlewareInterface;
use Echo\Framework\Http\Request;
use Echo\Framework\Http\Response;
use Echo\Framework\Http\RequestInterface;
use Echo\Framework\Http\ResponseInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Middleware pipeline class
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class MiddlewarePipelineTest extends TestCase
{
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

    private function createCoreHandler(): Closure
    {
        return function ($request): ResponseInterface {
            return new Response('core', 200);
        };
    }

    public function testEmptyPipelineCallsCore(): void
    {
        $middleware = new Middleware();
        $request = $this->createRequest();

        $response = $middleware->handle($request, $this->createCoreHandler());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testSingleLayerExecutes(): void
    {
        $layer = new class implements MiddlewareInterface {
            public function handle(RequestInterface $request, Closure $next): ResponseInterface
            {
                $request->setAttribute('test_layer', true);
                return $next($request);
            }
        };

        $middleware = new Middleware();
        $request = $this->createRequest();

        $middleware->layer([$layer])->handle($request, $this->createCoreHandler());

        $this->assertTrue($request->getAttribute('test_layer'));
    }

    public function testLayersExecuteInOrder(): void
    {
        $layer1 = new class implements MiddlewareInterface {
            public function handle(RequestInterface $request, Closure $next): ResponseInterface
            {
                $request->setAttribute('order_1', true);
                return $next($request);
            }
        };

        $layer2 = new class implements MiddlewareInterface {
            public function handle(RequestInterface $request, Closure $next): ResponseInterface
            {
                // layer2 should see layer1's attribute already set
                $request->setAttribute('order_2_saw_1', $request->getAttribute('order_1'));
                return $next($request);
            }
        };

        $middleware = new Middleware();
        $request = $this->createRequest();

        $middleware->layer([$layer1, $layer2])->handle($request, $this->createCoreHandler());

        $this->assertTrue($request->getAttribute('order_1'));
        $this->assertTrue($request->getAttribute('order_2_saw_1'));
    }

    public function testLayerCanShortCircuit(): void
    {
        $coreCalled = false;

        $blocker = new class implements MiddlewareInterface {
            public function handle(RequestInterface $request, Closure $next): ResponseInterface
            {
                return new Response('blocked', 403);
            }
        };

        $core = function ($request) use (&$coreCalled): ResponseInterface {
            $coreCalled = true;
            return new Response('core', 200);
        };

        $middleware = new Middleware();
        $request = $this->createRequest();

        $response = $middleware->layer([$blocker])->handle($request, $core);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertFalse($coreCalled);
    }

    public function testLayerAcceptsMiddlewareInstance(): void
    {
        $layer = new class implements MiddlewareInterface {
            public function handle(RequestInterface $request, Closure $next): ResponseInterface
            {
                return $next($request);
            }
        };

        $middleware = new Middleware();
        $pipeline = $middleware->layer($layer);

        $this->assertInstanceOf(Middleware::class, $pipeline);
    }

    public function testLayerAcceptsMiddlewarePipeline(): void
    {
        $inner = new Middleware();
        $outer = new Middleware();

        $pipeline = $outer->layer($inner);

        $this->assertInstanceOf(Middleware::class, $pipeline);
    }

    public function testToArrayReturnsLayers(): void
    {
        $middleware = new Middleware();
        $this->assertEquals([], $middleware->toArray());
    }

    public function testInvalidLayerThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('is not compatible middleware');

        $middleware = new Middleware();
        $middleware->layer(new \stdClass());
    }

    public function testCreateLayerRejectsNonMiddlewareInterface(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Middleware layer must implement MiddlewareInterface');

        $notMiddleware = new class {};

        $middleware = new Middleware();
        $request = $this->createRequest();

        $middleware->layer([$notMiddleware])->handle($request, $this->createCoreHandler());
    }

    public function testCreateLayerRejectsUnresolvableString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Middleware layer must implement MiddlewareInterface');

        $middleware = new Middleware();
        $request = $this->createRequest();

        $middleware->layer(['NonExistentMiddlewareClass'])->handle($request, $this->createCoreHandler());
    }
}
