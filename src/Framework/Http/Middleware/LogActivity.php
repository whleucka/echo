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
