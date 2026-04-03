<?php

namespace Echo\Framework\Http\Listeners;

use App\Models\Activity;
use App\Services\GeoIpService;
use Echo\Framework\Event\EventInterface;
use Echo\Framework\Event\Http\ResponseSending;
use Echo\Framework\Event\ListenerInterface;

/**
 * Activity Listener
 *
 * Logs HTTP request activity to the database after the response is built.
 * Listens on ResponseSending so the HTTP status code is available.
 */
class ActivityListener implements ListenerInterface
{
    public function handle(EventInterface $event): void
    {
        if (!$event instanceof ResponseSending) {
            return;
        }

        try {
            $request = $event->request;

            // Skip logging for benchmark and debug routes
            $route = $request->getAttribute('route');
            $middleware = $route['middleware'] ?? [];
            if (is_array($middleware) && (in_array('benchmark', $middleware, true) || in_array('debug', $middleware, true))) {
                return;
            }

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
                "status_code" => $event->response->getStatusCode(),
            ]]);
        } catch (\Exception|\Error|\PDOException $e) {
            error_log("-- Skipping activity insert --");
        }
    }
}
