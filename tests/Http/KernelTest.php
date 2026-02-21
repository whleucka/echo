<?php

declare(strict_types=1);

namespace Tests\Http;

use Echo\Framework\Http\ErrorRendererInterface;
use Echo\Framework\Http\Kernel;
use Echo\Framework\Http\RequestInterface;
use Echo\Framework\Http\ResponseInterface;
use Echo\Framework\Routing\RouterInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the HTTP Kernel.
 *
 * The Kernel is tested in isolation: router and error renderer are mocked,
 * so no application bootstrap is required and no process termination occurs.
 */
class KernelTest extends TestCase
{
    /** @var RequestInterface&Stub */
    private RequestInterface $request;

    protected function setUp(): void
    {
        // The request is only ever stubbed — we never assert call counts on it.
        $this->request = $this->createStub(RequestInterface::class);
        $this->request->method('getUri')->willReturn('/any');
        $this->request->method('getMethod')->willReturn('GET');
        $this->request->method('getHost')->willReturn('localhost');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeKernel(RouterInterface $router, ErrorRendererInterface $renderer): Kernel
    {
        return new class ($router, $renderer) extends Kernel {};
    }

    private function stubResponse(int $status = 200): ResponseInterface&Stub
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        return $response;
    }

    // -------------------------------------------------------------------------
    // 404 path
    // -------------------------------------------------------------------------

    public function testReturns404ResponseWhenRouteNotFound(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())->method('dispatch')->willReturn(null);

        $renderer = $this->createMock(ErrorRendererInterface::class);
        $renderer->expects($this->once())
            ->method('renderNotFound')
            ->with($this->request)
            ->willReturn($this->stubResponse(404));

        $response = $this->makeKernel($router, $renderer)->handle($this->request);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testRenderNotFoundIsCalledOnlyOnce(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())->method('dispatch')->willReturn(null);

        $renderer = $this->createMock(ErrorRendererInterface::class);
        $renderer->expects($this->once())
            ->method('renderNotFound')
            ->willReturn($this->stubResponse(404));

        $this->makeKernel($router, $renderer)->handle($this->request);
    }

    // -------------------------------------------------------------------------
    // Return type
    // -------------------------------------------------------------------------

    public function testHandleReturnsResponseInterface(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())->method('dispatch')->willReturn(null);

        $renderer = $this->createMock(ErrorRendererInterface::class);
        $renderer->expects($this->once())
            ->method('renderNotFound')
            ->willReturn($this->stubResponse());

        $result = $this->makeKernel($router, $renderer)->handle($this->request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    // -------------------------------------------------------------------------
    // Router receives correct arguments
    // -------------------------------------------------------------------------

    public function testDispatchReceivesUriMethodAndHost(): void
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getUri')->willReturn('/test-path');
        $request->method('getMethod')->willReturn('POST');
        $request->method('getHost')->willReturn('example.com');

        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())
            ->method('dispatch')
            ->with('/test-path', 'POST', 'example.com')
            ->willReturn(null);

        $renderer = $this->createMock(ErrorRendererInterface::class);
        $renderer->expects($this->once())
            ->method('renderNotFound')
            ->willReturn($this->stubResponse());

        $this->makeKernel($router, $renderer)->handle($request);
    }

    // -------------------------------------------------------------------------
    // Renderer receives the request object
    // -------------------------------------------------------------------------

    public function testRendererReceivesRequestOnNotFound(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())->method('dispatch')->willReturn(null);

        $renderer = $this->createMock(ErrorRendererInterface::class);
        $renderer->expects($this->once())
            ->method('renderNotFound')
            ->with($this->identicalTo($this->request))
            ->willReturn($this->stubResponse());

        $this->makeKernel($router, $renderer)->handle($this->request);
    }

    // -------------------------------------------------------------------------
    // Kernel does not call exit or send (process boundary is in Application)
    // -------------------------------------------------------------------------

    public function testHandleDoesNotTerminateProcess(): void
    {
        // If handle() called exit, the assertion below would never be reached.
        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())->method('dispatch')->willReturn(null);

        $renderer = $this->createMock(ErrorRendererInterface::class);
        $renderer->expects($this->once())
            ->method('renderNotFound')
            ->willReturn($this->stubResponse());

        $this->makeKernel($router, $renderer)->handle($this->request);

        $this->assertTrue(true);
    }
}
