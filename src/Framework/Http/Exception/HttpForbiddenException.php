<?php

namespace Echo\Framework\Http\Exception;

class HttpForbiddenException extends HttpException
{
    public function __construct(string $message = 'Forbidden', ?\Throwable $previous = null)
    {
        parent::__construct(403, $message, $previous);
    }
}
