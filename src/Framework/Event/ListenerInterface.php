<?php

namespace Echo\Framework\Event;

/**
 * Listener Interface
 *
 * Listener classes should implement this interface for type-safety.
 * Closures are also accepted by the dispatcher as listeners.
 */
interface ListenerInterface
{
    /**
     * Handle the event
     */
    public function handle(EventInterface $event): void;
}
