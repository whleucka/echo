<?php

namespace Echo\Framework\Event\Model;

use Echo\Framework\Database\Model;
use Echo\Framework\Event\Event;

/**
 * Dispatched after a model is deleted.
 */
class ModelDeleted extends Event
{
    public function __construct(
        public readonly string $modelClass,
        public readonly string|int $modelId,
        public readonly array $attributes,
    ) {}
}
