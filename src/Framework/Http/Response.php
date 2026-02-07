<?php

namespace Echo\Framework\Http;

use Echo\Interface\Http\Response as HttpResponse;

class Response implements HttpResponse
{
    private int $code;
    private array $headers = [];

    public function __construct(private ?string $content, ?int $code = null)
    {
        if (is_null($code)) {
            $this->code = http_response_code();
        } else {
            $this->code = $code;
        }
    }

    public function send(): void
    {
        ob_start();
        ob_clean();
        $this->sendHeaders();
        $this->sendDebugHeaders();
        http_response_code($this->code);

        $content = $this->content;

        // Inject debug toolbar for HTML responses (only on initial page load, not HTMX)
        if (config('app.debug') && $this->isHtmlResponse() && !$this->isHtmxRequest()) {
            $toolbar = \Echo\Framework\Debug\DebugToolbar::render();
            $content = str_replace('</body>', $toolbar . '</body>', $content);
        }

        echo $content;
    }

    /**
     * Send debug headers for HTMX request tracking
     */
    private function sendDebugHeaders(): void
    {
        if (!config('app.debug')) {
            return;
        }

        $headers = \Echo\Framework\Debug\DebugToolbar::getDebugHeaders();
        foreach ($headers as $name => $value) {
            header("$name: $value");
        }
    }

    /**
     * Check if this is an HTMX request
     */
    private function isHtmxRequest(): bool
    {
        return isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';
    }

    /**
     * Check if response contains HTML body tag
     */
    private function isHtmlResponse(): bool
    {
        return $this->content && strpos($this->content, '</body>') !== false;
    }

    public function setHeader(string $key, string $value): void
    {
        $this->headers[$key] = $value;
    }

    public function getStatusCode(): int
    {
        return $this->code;
    }

    private function sendHeaders(): void
    {
        // Security headers
        header("X-Frame-Options: DENY");
        header("X-Content-Type-Options: nosniff");
        header("Referrer-Policy: strict-origin-when-cross-origin");
        header("X-XSS-Protection: 1; mode=block");
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data:; font-src 'self' https://cdn.jsdelivr.net; connect-src 'self'; frame-ancestors 'none'; object-src 'none'; base-uri 'self'");
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
        }

        // Custom headers
        foreach ($this->headers as $key => $value) {
            header("$key: $value");
            if (in_array($key, ['Location', 'HX-Location', 'HX-Redirect'])) {
                exit;
            }
        }
    }
}
