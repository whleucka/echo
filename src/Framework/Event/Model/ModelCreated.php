<?php

namespace Echo\Framework\Event\Model;

use Echo\Framework\Database\Model;
use Echo\Framework\Event\Event;

/**
 * Dispatched after a model is created.
 */
class ModelCreated extends Event
{
    public function __construct(
        public readonly Model $model,
        public readonly array $attributes,
    ) {}
}
