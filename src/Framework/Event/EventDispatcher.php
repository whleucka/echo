<?php

namespace Echo\Framework\Event;

/**
 * Event Dispatcher
 *
 * Synchronous event dispatcher that resolves listeners from the
 * DI container when registered as class names.
 */
class EventDispatcher implements EventDispatcherInterface
{
    /**
     * Registered listeners keyed by event class FQCN
     *
     * @var array<string, array<array{listener: callable|string, priority: int}>>
     */
    private array $listeners = [];

    /**
     * Cache of sorted listeners per event
     *
     * @var array<string, array<callable|string>>
     */
    private array $sorted = [];

    /**
     * Dispatch an event to all registered listeners
     */
    public function dispatch(EventInterface $event): EventInterface
    {
        $eventClass = $event->getName();

        foreach ($this->getListeners($eventClass) as $listener) {
            if ($event->isPropagationStopped()) {
                break;
            }

            $resolved = $this->resolveListener($listener);
            $resolved($event);
        }

        return $event;
    }

    /**
     * Register an event listener
     */
    public function listen(string $eventClass, callable|string $listener, int $priority = 0): void
    {
        $this->listeners[$eventClass][] = [
            'listener' => $listener,
            'priority' => $priority,
        ];

        // Invalidate the sorted cache for this event
        unset($this->sorted[$eventClass]);
    }

    /**
     * Check if an event has any registered listeners
     */
    public function hasListeners(string $eventClass): bool
    {
        return !empty($this->listeners[$eventClass]);
    }

    /**
     * Get all listeners for an event, sorted by priority (lower = earlier)
     *
     * @return array<callable|string>
     */
    public function getListeners(string $eventClass): array
    {
        if (!isset($this->listeners[$eventClass])) {
            return [];
        }

        if (!isset($this->sorted[$eventClass])) {
            $this->sorted[$eventClass] = $this->sortListeners($eventClass);
        }

        return $this->sorted[$eventClass];
    }

    /**
     * Remove all listeners for an event
     */
    public function forget(string $eventClass): void
    {
        unset($this->listeners[$eventClass]);
        unset($this->sorted[$eventClass]);
    }

    /**
     * Sort listeners by priority (lower = earlier)
     *
     * @return array<callable|string>
     */
    private function sortListeners(string $eventClass): array
    {
        $entries = $this->listeners[$eventClass];

        usort($entries, fn(array $a, array $b) => $a['priority'] <=> $b['priority']);

        return array_column($entries, 'listener');
    }

    /**
     * Resolve a listener to a callable
     *
     * If the listener is a string (class name), it will be resolved
     * from the DI container, enabling constructor injection.
     */
    private function resolveListener(callable|string $listener): callable
    {
        if (is_string($listener)) {
            $instance = container()->get($listener);

            if ($instance instanceof ListenerInterface) {
                return [$instance, 'handle'];
            }

            throw new \InvalidArgumentException(
                "Listener class {$listener} must implement " . ListenerInterface::class
            );
        }

        return $listener;
    }
}
