<?php

namespace Echo\Framework\Http\Middleware;

use Closure;
use Echo\Framework\Http\RequestInterface;
use Echo\Framework\Http\ResponseInterface;
use Echo\Framework\Http\MiddlewareInterface;

/**
 * LogActivity Middleware
 *
 * Activity logging is now handled by the ActivityListener on the
 * ResponseSending event, which has access to the HTTP status code.
 * This middleware remains as a passthrough for backward compatibility.
 */
class LogActivity implements MiddlewareInterface
{
    public function handle(RequestInterface $request, Closure $next): ResponseInterface
    {
        return $next($request);
    }
}
