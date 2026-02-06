<?php

declare(strict_types=1);

namespace Tests\Http;

use Echo\Framework\Http\HtmxResponse;
use Echo\Framework\Http\Response;
use PHPUnit\Framework\TestCase;

class HtmxResponseTest extends TestCase
{
    /**
     * Test redirect sets HX-Redirect header
     */
    public function testRedirectSetsHeader(): void
    {
        $htmx = new HtmxResponse();
        $response = $htmx->redirect('/dashboard')->toResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertResponseHasHeader($response, 'HX-Redirect', '/dashboard');
    }

    /**
     * Test location with simple URL
     */
    public function testLocationWithSimpleUrl(): void
    {
        $htmx = new HtmxResponse();
        $response = $htmx->location('/users')->toResponse();

        $this->assertResponseHasHeader($response, 'HX-Location', '/users');
    }

    /**
     * Test location with target and swap options
     */
    public function testLocationWithOptions(): void
    {
        $htmx = new HtmxResponse();
        $response = $htmx->location('/users', '#content', 'innerHTML')->toResponse();

        $headers = $this->getResponseHeaders($response);
        $this->assertArrayHasKey('HX-Location', $headers);

        $decoded = json_decode($headers['HX-Location'], true);
        $this->assertEquals('/users', $decoded['path']);
        $this->assertEquals('#content', $decoded['target']);
        $this->assertEquals('innerHTML', $decoded['swap']);
    }

    /**
     * Test location with only target (no swap)
     */
    public function testLocationWithTargetOnly(): void
    {
        $htmx = new HtmxResponse();
        $response = $htmx->location('/users', '#content')->toResponse();

        $headers = $this->getResponseHeaders($response);
        $decoded = json_decode($headers['HX-Location'], true);

        $this->assertEquals('/users', $decoded['path']);
        $this->assertEquals('#content', $decoded['target']);
        $this->assertArrayNotHasKey('swap', $decoded);
    }

    /**
     * Test single trigger event
     */
    public function testTriggerSingleEvent(): void
    {
        $htmx = new HtmxResponse();
        $response = $htmx->trigger('rowUpdated')->toResponse();

        $this->assertResponseHasHeader($response, 'HX-Trigger', 'rowUpdated');
    }

    /**
     * Test multiple trigger events
     */
    public function testTriggerMultipleEvents(): void
    {
        $htmx = new HtmxResponse();
        $response = $htmx->trigger(['rowUpdated', 'tableRefresh'])->toResponse();

        $this->assertResponseHasHeader($response, 'HX-Trigger', 'rowUpdated, tableRefresh');
    }

    /**
     * Test trigger accumulates events
     */
    public function testTriggerAccumulatesEvents(): void
    {
        $htmx = new HtmxResponse();
        $response = $htmx
            ->trigger('event1')
            ->trigger('event2')
            ->trigger(['event3', 'event4'])
            ->toResponse();

        $this->assertResponseHasHeader($response, 'HX-Trigger', 'event1, event2, event3, event4');
    }

    /**
     * Test triggerAfterSettle
     */
    public function testTriggerAfterSettle(): void
    {
        $htmx = new HtmxResponse();
        $response = $htmx->triggerAfterSettle('settleEvent')->toResponse();

        $this->assertResponseHasHeader($response, 'HX-Trigger-After-Settle', 'settleEvent');
    }

    /**
     * Test triggerAfterSettle with array
     */
    public function testTriggerAfterSettleWithArray(): void
    {
        $htmx = new HtmxResponse();
        $response = $htmx->triggerAfterSettle(['event1', 'event2'])->toResponse();

        $this->assertResponseHasHeader($response, 'HX-Trigger-After-Settle', 'event1, event2');
    }

    /**
     * Test triggerAfterSwap
     */
    public function testTriggerAfterSwap(): void
    {
        $htmx = new HtmxResponse();
        $response = $htmx->triggerAfterSwap('swapEvent')->toResponse();

        $this->assertResponseHasHeader($response, 'HX-Trigger-After-Swap', 'swapEvent');
    }

    /**
     * Test triggerAfterSwap with array
     */
    public function testTriggerAfterSwapWithArray(): void
    {
        $htmx = new HtmxResponse();
        $response = $htmx->triggerAfterSwap(['event1', 'event2'])->toResponse();

        $this->assertResponseHasHeader($response, 'HX-Trigger-After-Swap', 'event1, event2');
    }

    /**
     * Test retarget sets HX-Retarget header
     */
    public function testRetarget(): void
    {
        $htmx = new HtmxResponse();
        $response = $htmx->retarget('#table-body')->toResponse();

        $this->assertResponseHasHeader($response, 'HX-Retarget', '#table-body');
    }

    /**
     * Test reswap sets HX-Reswap header
     */
    public function testReswap(): void
    {
        $htmx = new HtmxResponse();
        $response = $htmx->reswap('innerHTML')->toResponse();

        $this->assertResponseHasHeader($response, 'HX-Reswap', 'innerHTML');
    }

    /**
     * Test reselect sets HX-Reselect header
     */
    public function testReselect(): void
    {
        $htmx = new HtmxResponse();
        $response = $htmx->reselect('.selected-item')->toResponse();

        $this->assertResponseHasHeader($response, 'HX-Reselect', '.selected-item');
    }

    /**
     * Test refresh sets HX-Refresh header
     */
    public function testRefresh(): void
    {
        $htmx = new HtmxResponse();
        $response = $htmx->refresh()->toResponse();

        $this->assertResponseHasHeader($response, 'HX-Refresh', 'true');
    }

    /**
     * Test pushUrl with string
     */
    public function testPushUrlWithString(): void
    {
        $htmx = new HtmxResponse();
        $response = $htmx->pushUrl('/new-url')->toResponse();

        $this->assertResponseHasHeader($response, 'HX-Push-Url', '/new-url');
    }

    /**
     * Test pushUrl with boolean true
     */
    public function testPushUrlWithTrue(): void
    {
        $htmx = new HtmxResponse();
        $response = $htmx->pushUrl(true)->toResponse();

        $this->assertResponseHasHeader($response, 'HX-Push-Url', 'true');
    }

    /**
     * Test pushUrl with boolean false
     */
    public function testPushUrlWithFalse(): void
    {
        $htmx = new HtmxResponse();
        $response = $htmx->pushUrl(false)->toResponse();

        $this->assertResponseHasHeader($response, 'HX-Push-Url', 'false');
    }

    /**
     * Test replaceUrl with string
     */
    public function testReplaceUrlWithString(): void
    {
        $htmx = new HtmxResponse();
        $response = $htmx->replaceUrl('/replaced-url')->toResponse();

        $this->assertResponseHasHeader($response, 'HX-Replace-Url', '/replaced-url');
    }

    /**
     * Test replaceUrl with boolean true
     */
    public function testReplaceUrlWithTrue(): void
    {
        $htmx = new HtmxResponse();
        $response = $htmx->replaceUrl(true)->toResponse();

        $this->assertResponseHasHeader($response, 'HX-Replace-Url', 'true');
    }

    /**
     * Test replaceUrl with boolean false
     */
    public function testReplaceUrlWithFalse(): void
    {
        $htmx = new HtmxResponse();
        $response = $htmx->replaceUrl(false)->toResponse();

        $this->assertResponseHasHeader($response, 'HX-Replace-Url', 'false');
    }

    /**
     * Test custom header
     */
    public function testCustomHeader(): void
    {
        $htmx = new HtmxResponse();
        $response = $htmx->header('X-Custom', 'value')->toResponse();

        $this->assertResponseHasHeader($response, 'X-Custom', 'value');
    }

    /**
     * Test toResponse with content and status
     */
    public function testToResponseWithContentAndStatus(): void
    {
        $htmx = new HtmxResponse();
        $response = $htmx->toResponse('<div>Content</div>', 201);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(201, $response->getStatusCode());
    }

    /**
     * Test toResponse default values
     */
    public function testToResponseDefaults(): void
    {
        $htmx = new HtmxResponse();
        $response = $htmx->toResponse();

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test empty response
     */
    public function testEmptyResponse(): void
    {
        $htmx = new HtmxResponse();
        $response = $htmx->trigger('event')->empty();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertResponseHasHeader($response, 'HX-Trigger', 'event');
    }

    /**
     * Test empty response with custom status
     */
    public function testEmptyResponseWithStatus(): void
    {
        $htmx = new HtmxResponse();
        $response = $htmx->empty(202);

        $this->assertEquals(202, $response->getStatusCode());
    }

    /**
     * Test noContent response
     */
    public function testNoContentResponse(): void
    {
        $htmx = new HtmxResponse();
        $response = $htmx->noContent();

        $this->assertEquals(204, $response->getStatusCode());
    }

    /**
     * Test fluent interface chaining
     */
    public function testFluentChaining(): void
    {
        $htmx = new HtmxResponse();
        $response = $htmx
            ->trigger('rowUpdated')
            ->retarget('#table-body')
            ->reswap('innerHTML')
            ->pushUrl('/users/1')
            ->header('X-Custom', 'value')
            ->toResponse('<tr>...</tr>', 200);

        $headers = $this->getResponseHeaders($response);

        $this->assertEquals('rowUpdated', $headers['HX-Trigger']);
        $this->assertEquals('#table-body', $headers['HX-Retarget']);
        $this->assertEquals('innerHTML', $headers['HX-Reswap']);
        $this->assertEquals('/users/1', $headers['HX-Push-Url']);
        $this->assertEquals('value', $headers['X-Custom']);
    }

    /**
     * Test static make factory
     */
    public function testStaticMakeFactory(): void
    {
        $response = HtmxResponse::make()
            ->trigger('created')
            ->toResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertResponseHasHeader($response, 'HX-Trigger', 'created');
    }

    /**
     * Test triggers with complex data (JSON format)
     */
    public function testTriggersWithComplexData(): void
    {
        $htmx = new HtmxResponse();
        $response = $htmx->trigger([
            'simpleEvent',
            ['eventWithData' => ['id' => 123, 'name' => 'test']]
        ])->toResponse();

        $headers = $this->getResponseHeaders($response);
        $this->assertArrayHasKey('HX-Trigger', $headers);

        // When there's complex data, it should be JSON encoded
        $this->assertJson($headers['HX-Trigger']);
    }

    /**
     * Test multiple different trigger types together
     */
    public function testMultipleTriggerTypes(): void
    {
        $htmx = new HtmxResponse();
        $response = $htmx
            ->trigger('immediate')
            ->triggerAfterSettle('afterSettle')
            ->triggerAfterSwap('afterSwap')
            ->toResponse();

        $headers = $this->getResponseHeaders($response);

        $this->assertEquals('immediate', $headers['HX-Trigger']);
        $this->assertEquals('afterSettle', $headers['HX-Trigger-After-Settle']);
        $this->assertEquals('afterSwap', $headers['HX-Trigger-After-Swap']);
    }

    /**
     * Test no triggers produces no trigger headers
     */
    public function testNoTriggersNoHeaders(): void
    {
        $htmx = new HtmxResponse();
        $response = $htmx->retarget('#content')->toResponse();

        $headers = $this->getResponseHeaders($response);

        $this->assertArrayNotHasKey('HX-Trigger', $headers);
        $this->assertArrayNotHasKey('HX-Trigger-After-Settle', $headers);
        $this->assertArrayNotHasKey('HX-Trigger-After-Swap', $headers);
    }

    /**
     * Helper to get headers from a Response via reflection
     */
    private function getResponseHeaders(Response $response): array
    {
        $reflection = new \ReflectionClass($response);
        $property = $reflection->getProperty('headers');
        $property->setAccessible(true);
        return $property->getValue($response);
    }

    /**
     * Helper to assert a response has a specific header value
     */
    private function assertResponseHasHeader(Response $response, string $name, string $expectedValue): void
    {
        $headers = $this->getResponseHeaders($response);
        $this->assertArrayHasKey($name, $headers, "Header '$name' not found");
        $this->assertEquals($expectedValue, $headers[$name], "Header '$name' value mismatch");
    }
}
