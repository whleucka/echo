<?php

namespace Echo\Framework\Event;

/**
 * Event Dispatcher Interface
 *
 * Defines the contract for dispatching events to registered listeners.
 */
interface EventDispatcherInterface
{
    /**
     * Dispatch an event to all registered listeners
     *
     * @param EventInterface $event The event to dispatch
     * @return EventInterface The (potentially modified) event
     */
    public function dispatch(EventInterface $event): EventInterface;

    /**
     * Register an event listener
     *
     * @param string $eventClass The event class FQCN to listen for
     * @param callable|string $listener A callable or class name implementing ListenerInterface
     * @param int $priority Listener priority (lower = earlier execution)
     */
    public function listen(string $eventClass, callable|string $listener, int $priority = 0): void;

    /**
     * Check if an event has any registered listeners
     */
    public function hasListeners(string $eventClass): bool;

    /**
     * Get all listeners for an event, sorted by priority
     *
     * @return array<callable|string>
     */
    public function getListeners(string $eventClass): array;

    /**
     * Remove all listeners for an event
     */
    public function forget(string $eventClass): void;
}
