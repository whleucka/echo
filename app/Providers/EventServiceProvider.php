<?php

namespace App\Providers;

use App\Events\Auth\PasswordResetCompleted;
use App\Events\Auth\PasswordResetRequested;
use App\Events\Auth\SignInFailed;
use App\Events\Auth\UserRegistered;
use App\Events\Auth\UserSignedIn;
use App\Events\Auth\UserSignedOut;
use App\Listeners\AuthListener;
use Echo\Framework\Audit\AuditListener;
use Echo\Framework\Event\EventServiceProvider as BaseEventServiceProvider;
use Echo\Framework\Event\Model\ModelCreated;
use Echo\Framework\Event\Model\ModelUpdated;
use Echo\Framework\Event\Model\ModelDeleted;
use Echo\Framework\Event\Http\ResponseSending;
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

        // HTTP activity logging (on ResponseSending to capture status code)
        ResponseSending::class => [
            ActivityListener::class,
        ],

        // Authentication events
        UserSignedIn::class => [
            AuthListener::class,
        ],
        SignInFailed::class => [
            AuthListener::class,
        ],
        UserSignedOut::class => [
            AuthListener::class,
        ],
        UserRegistered::class => [
            AuthListener::class,
        ],
        PasswordResetRequested::class => [
            AuthListener::class,
        ],
        PasswordResetCompleted::class => [
            AuthListener::class,
        ],
    ];
}
