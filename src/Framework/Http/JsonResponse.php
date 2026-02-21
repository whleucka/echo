<?php

namespace Echo\Framework\Http;

class JsonResponse implements ResponseInterface
{
    private int $code;
    private array $headers = [];

    public function __construct(private array $content, ?int $code = null)
    {
        if (is_null($code)) {
            $this->code = http_response_code();
        } else {
            $this->code = $code;
        }
        $this->setHeader("Content-type", "application/json; charset=utf-8");
    }

    public function send(): void
    {
        ob_start();
        ob_clean();
        http_response_code($this->code);
        $this->sendHeaders();
        $this->sendDebugHeaders();
        echo json_encode($this->content, JSON_PRETTY_PRINT);
    }

    /**
     * Send debug headers for request tracking
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
        header("X-Content-Type-Options: nosniff");
        header("X-Frame-Options: DENY");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Pragma: no-cache");

        // Custom headers
        foreach ($this->headers as $key => $value) {
            header("$key: $value");
        }
    }
}
