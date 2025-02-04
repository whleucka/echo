<?php

namespace Echo\Framework\Http;

use Echo\Interface\Http\Response as HttpResponse;

class Response implements HttpResponse
{
    public function __construct(private string $content)
    {
    }

    public function send(int $code = 200): void
    {
        ob_start();
        ob_clean();
        http_response_code($code);
        echo $this->content;
    }
}
