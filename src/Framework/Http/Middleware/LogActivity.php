<?php

namespace Echo\Framework\Http\Middleware;

use App\Models\Activity;
use Closure;
use Echo\Framework\Http\RequestInterface;
use Echo\Framework\Http\ResponseInterface;
use Echo\Framework\Http\MiddlewareInterface;

/**
 * LogActivity
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

        try {
            $user = user();
            Activity::createBulk([[
                "user_id" => $user ? $user->id : null,
                "uri" => $request->getUri(),
                "ip" => ip2long($request->getClientIp())
            ]]);
        } catch (\Exception|\Error|\PDOException $e) {
            error_log("-- Skipping session insert --");
        }

        return $next($request);
    }
}
