<?php

namespace Echo\Framework\Http\Middleware;

use App\Models\Activity;
use App\Services\GeoIpService;
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
            $clientIp = $request->getClientIp();

            // Resolve country code from IP
            $countryCode = null;
            try {
                $geoIp = container()->get(GeoIpService::class);
                $countryCode = $geoIp->getCountryCode($clientIp);
            } catch (\Exception) {
                // GeoIP lookup is best-effort
            }

            Activity::createBulk([[
                "user_id" => $user ? $user->id : null,
                "uri" => $request->getUri(),
                "ip" => ip2long($clientIp),
                "country_code" => $countryCode,
            ]]);
        } catch (\Exception|\Error|\PDOException $e) {
            error_log("-- Skipping session insert --");
        }

        return $next($request);
    }
}
