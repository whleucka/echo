<?php

namespace Echo\Framework\Event;

use Echo\Framework\Support\ServiceProvider;

/**
 * Base Event Service Provider
 *
 * Provides the foundation for registering event-listener mappings.
 * Application-level providers should extend this class and populate
 * the $listen array.
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * Event-to-listener mappings
     *
     * Format: [EventClass::class => [ListenerClass::class, ...]]
     *
     * @var array<string, array<string|callable>>
     */
    protected array $listen = [];

    /**
     * Register the event dispatcher with the container
     */
    public function register(): void
    {
        $this->container()->set(
            EventDispatcherInterface::class,
            fn() => new EventDispatcher()
        );
    }

    /**
     * Boot the event system — register all listener mappings
     */
    public function boot(): void
    {
        $dispatcher = $this->container()->get(EventDispatcherInterface::class);

        foreach ($this->listen as $eventClass => $listeners) {
            foreach ($listeners as $listener) {
                $dispatcher->listen($eventClass, $listener);
            }
        }
    }
}
