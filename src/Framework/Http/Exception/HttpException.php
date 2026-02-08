<?php

namespace Echo\Framework\Http\Exception;

use Exception;

/**
 * Base HTTP exception.
 *
 * Thrown to short-circuit request handling and produce
 * the appropriate HTTP error response via the Kernel.
 */
class HttpException extends Exception
{
    public function __construct(
        public readonly int $statusCode,
        string $message = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
}
