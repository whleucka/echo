<?php

namespace Echo\Framework\Event\Model;

use Echo\Framework\Event\Event;

/**
 * Dispatched before a model is created.
 *
 * Call stopPropagation() to cancel the creation.
 */
class ModelCreating extends Event
{
    public function __construct(
        public readonly string $modelClass,
        public readonly array $attributes,
    ) {}
}
