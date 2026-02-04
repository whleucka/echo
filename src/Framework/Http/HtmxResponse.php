<?php

namespace Echo\Framework\Http;

/**
 * Fluent builder for HTMX response headers
 *
 * Usage:
 *   return (new HtmxResponse())
 *       ->trigger('rowUpdated')
 *       ->retarget('#table-body')
 *       ->reswap('innerHTML')
 *       ->toResponse($html);
 */
class HtmxResponse
{
    private array $headers = [];
    private array $triggers = [];
    private array $triggersAfterSettle = [];
    private array $triggersAfterSwap = [];

    /**
     * Client-side redirect (full page)
     */
    public function redirect(string $url): self
    {
        $this->headers['HX-Redirect'] = $url;
        return $this;
    }

    /**
     * Client-side redirect that preserves current URL in history
     */
    public function location(string $url, ?string $target = null, ?string $swap = null): self
    {
        if ($target || $swap) {
            $value = ['path' => $url];
            if ($target) $value['target'] = $target;
            if ($swap) $value['swap'] = $swap;
            $this->headers['HX-Location'] = json_encode($value);
        } else {
            $this->headers['HX-Location'] = $url;
        }
        return $this;
    }

    /**
     * Trigger client-side events
     */
    public function trigger(string|array $events): self
    {
        if (is_string($events)) {
            $this->triggers[] = $events;
        } else {
            $this->triggers = array_merge($this->triggers, $events);
        }
        return $this;
    }

    /**
     * Trigger events after the settle step
     */
    public function triggerAfterSettle(string|array $events): self
    {
        if (is_string($events)) {
            $this->triggersAfterSettle[] = $events;
        } else {
            $this->triggersAfterSettle = array_merge($this->triggersAfterSettle, $events);
        }
        return $this;
    }

    /**
     * Trigger events after the swap step
     */
    public function triggerAfterSwap(string|array $events): self
    {
        if (is_string($events)) {
            $this->triggersAfterSwap[] = $events;
        } else {
            $this->triggersAfterSwap = array_merge($this->triggersAfterSwap, $events);
        }
        return $this;
    }

    /**
     * Change the target element for the response
     */
    public function retarget(string $selector): self
    {
        $this->headers['HX-Retarget'] = $selector;
        return $this;
    }

    /**
     * Change the swap method for the response
     */
    public function reswap(string $strategy): self
    {
        $this->headers['HX-Reswap'] = $strategy;
        return $this;
    }

    /**
     * Select a subset of the response to swap in
     */
    public function reselect(string $selector): self
    {
        $this->headers['HX-Reselect'] = $selector;
        return $this;
    }

    /**
     * Trigger a full page refresh
     */
    public function refresh(): self
    {
        $this->headers['HX-Refresh'] = 'true';
        return $this;
    }

    /**
     * Push a URL onto the browser history
     */
    public function pushUrl(string|bool $url): self
    {
        $this->headers['HX-Push-Url'] = is_bool($url) ? ($url ? 'true' : 'false') : $url;
        return $this;
    }

    /**
     * Replace the current URL in browser history
     */
    public function replaceUrl(string|bool $url): self
    {
        $this->headers['HX-Replace-Url'] = is_bool($url) ? ($url ? 'true' : 'false') : $url;
        return $this;
    }

    /**
     * Set a custom header
     */
    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Build and return a Response object
     */
    public function toResponse(string $content = '', int $status = 200): Response
    {
        $response = new Response($content, $status);

        // Add trigger headers
        if (!empty($this->triggers)) {
            $response->setHeader('HX-Trigger', $this->formatTriggers($this->triggers));
        }
        if (!empty($this->triggersAfterSettle)) {
            $response->setHeader('HX-Trigger-After-Settle', $this->formatTriggers($this->triggersAfterSettle));
        }
        if (!empty($this->triggersAfterSwap)) {
            $response->setHeader('HX-Trigger-After-Swap', $this->formatTriggers($this->triggersAfterSwap));
        }

        // Add other headers
        foreach ($this->headers as $name => $value) {
            $response->setHeader($name, $value);
        }

        return $response;
    }

    /**
     * Create an empty response (for operations that don't need content)
     */
    public function empty(int $status = 200): Response
    {
        return $this->toResponse('', $status);
    }

    /**
     * Create a "no content" response (204)
     */
    public function noContent(): Response
    {
        return $this->toResponse('', 204);
    }

    /**
     * Format triggers for header value
     */
    private function formatTriggers(array $triggers): string
    {
        // Check if any trigger has data (is an associative array)
        $hasData = false;
        foreach ($triggers as $trigger) {
            if (is_array($trigger)) {
                $hasData = true;
                break;
            }
        }

        if ($hasData) {
            return json_encode($triggers);
        }

        return implode(', ', $triggers);
    }

    /**
     * Static factory for fluent usage
     */
    public static function make(): self
    {
        return new self();
    }
}
