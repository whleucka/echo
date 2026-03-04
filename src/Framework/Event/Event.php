<?php

namespace Echo\Framework\Event;

/**
 * Base Event
 *
 * All concrete events should extend this class.
 * Provides default implementations for the EventInterface.
 */
class Event implements EventInterface
{
    private bool $propagationStopped = false;

    /**
     * Get the event name (defaults to the class FQCN)
     */
    public function getName(): string
    {
        return static::class;
    }

    /**
     * Whether propagation has been stopped
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * Stop further event listeners from being called
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}
