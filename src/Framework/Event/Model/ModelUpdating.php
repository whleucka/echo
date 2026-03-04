<?php

namespace Echo\Framework\Event\Model;

use Echo\Framework\Database\Model;
use Echo\Framework\Event\Event;

/**
 * Dispatched before a model is updated.
 *
 * Call stopPropagation() to cancel the update.
 */
class ModelUpdating extends Event
{
    public function __construct(
        public readonly Model $model,
        public readonly array $oldAttributes,
        public readonly array $newAttributes,
    ) {}
}
