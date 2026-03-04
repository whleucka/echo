<?php

namespace Echo\Framework\Event;

/**
 * Event Interface
 *
 * Defines the contract for all events in the system.
 * Inspired by PSR-14's event dispatching concepts.
 */
interface EventInterface
{
    /**
     * Get the event name (defaults to FQCN)
     */
    public function getName(): string;

    /**
     * Whether propagation has been stopped
     */
    public function isPropagationStopped(): bool;
}
