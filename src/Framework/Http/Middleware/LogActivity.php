<?php

namespace Echo\Framework\Http\Middleware;

use App\Models\Activity;
use Closure;
use Echo\Interface\Http\{Request, Middleware, Response};

/**
 * LogActivity
 */
class LogActivity implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = user();
            Activity::insert([[
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
