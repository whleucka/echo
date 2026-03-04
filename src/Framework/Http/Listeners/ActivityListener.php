<?php

namespace Echo\Framework\Http\Listeners;

use App\Models\Activity;
use App\Services\GeoIpService;
use Echo\Framework\Event\EventInterface;
use Echo\Framework\Event\Http\RequestReceived;
use Echo\Framework\Event\ListenerInterface;

/**
 * Activity Listener
 *
 * Logs HTTP request activity to the database.
 * Previously this logic was coupled directly in the LogActivity middleware.
 */
class ActivityListener implements ListenerInterface
{
    public function handle(EventInterface $event): void
    {
        if (!$event instanceof RequestReceived) {
            return;
        }

        try {
            $request = $event->request;
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
            error_log("-- Skipping activity insert --");
        }
    }
}
