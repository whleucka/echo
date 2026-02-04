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
        http_response_code($this->code);
        echo $this->content;
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

        // Custom headers
        foreach ($this->headers as $key => $value) {
            header("$key: $value");
            if (in_array($key, ['Location', 'HX-Location', 'HX-Redirect'])) {
                exit;
            }
        }
    }
}
