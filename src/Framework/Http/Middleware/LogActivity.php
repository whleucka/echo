<?php

namespace Echo\Framework\Http\Middleware;

use Closure;
use Echo\Framework\Event\Http\RequestReceived;
use Echo\Framework\Http\RequestInterface;
use Echo\Framework\Http\ResponseInterface;
use Echo\Framework\Http\MiddlewareInterface;

/**
 * LogActivity Middleware
 *
 * Dispatches a RequestReceived event for activity logging.
 * The actual logging is handled by the ActivityListener.
 */
class LogActivity implements MiddlewareInterface
{
    public function handle(RequestInterface $request, Closure $next): ResponseInterface
    {
        $route = $request->getAttribute("route");
        $middleware = $route["middleware"] ?? [];

        // Skip logging for benchmark and debug routes
        if (is_array($middleware) && (in_array('benchmark', $middleware, true) || in_array('debug', $middleware, true))) {
            return $next($request);
        }

        // Dispatch RequestReceived event — listeners handle the logging
        try {
            event(new RequestReceived($request));
        } catch (\Throwable) {
            // Event dispatching should never break the request pipeline
        }

        return $next($request);
    }
}
