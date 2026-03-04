<?php

namespace Echo\Framework\Event\Model;

use Echo\Framework\Database\Model;
use Echo\Framework\Event\Event;

/**
 * Dispatched before a model is deleted.
 *
 * Call stopPropagation() to cancel the deletion.
 */
class ModelDeleting extends Event
{
    public function __construct(
        public readonly Model $model,
        public readonly array $attributes,
    ) {}
}
