<?php

namespace Echo\Framework\Http\Middleware;

use Echo\Interface\Http\{Request, Middleware};
use Closure;

/**
 * Adds an ID to request
 */
class RequestID implements Middleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $request->setAttribute('request_id', uniqid(more_entropy: true));

        $response = $next($request);

        return $response;
    }
}
