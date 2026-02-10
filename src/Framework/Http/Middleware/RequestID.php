<?php

namespace Echo\Framework\Http\Middleware;

use Closure;
use Echo\Framework\Http\RequestInterface;
use Echo\Framework\Http\ResponseInterface;
use Echo\Framework\Http\MiddlewareInterface;

/**
 * Adds an ID to request
 */
class RequestID implements MiddlewareInterface
{
    public function handle(RequestInterface $request, Closure $next): ResponseInterface
    {
        $request->setAttribute('request_id', md5(random_bytes(32)));

        return $next($request);
    }
}
