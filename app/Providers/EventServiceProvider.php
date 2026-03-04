<?php

namespace App\Providers;

use Echo\Framework\Audit\AuditListener;
use Echo\Framework\Event\EventServiceProvider as BaseEventServiceProvider;
use Echo\Framework\Event\Model\ModelCreated;
use Echo\Framework\Event\Model\ModelUpdated;
use Echo\Framework\Event\Model\ModelDeleted;
use Echo\Framework\Event\Http\RequestReceived;
use Echo\Framework\Http\Listeners\ActivityListener;

/**
 * Application Event Service Provider
 *
 * Register your event-listener mappings here.
 *
 * Format: EventClass::class => [ListenerClass::class, ...]
 */
class EventServiceProvider extends BaseEventServiceProvider
{
    protected array $listen = [
        // Audit logging for model lifecycle events
        ModelCreated::class => [
            AuditListener::class,
        ],
        ModelUpdated::class => [
            AuditListener::class,
        ],
        ModelDeleted::class => [
            AuditListener::class,
        ],

        // HTTP activity logging
        RequestReceived::class => [
            ActivityListener::class,
        ],
    ];
}
