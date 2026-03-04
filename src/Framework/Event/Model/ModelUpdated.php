<?php

namespace Echo\Framework\Event\Model;

use Echo\Framework\Database\Model;
use Echo\Framework\Event\Event;

/**
 * Dispatched after a model is updated.
 */
class ModelUpdated extends Event
{
    public function __construct(
        public readonly Model $model,
        public readonly array $oldAttributes,
        public readonly array $newAttributes,
    ) {}
}
