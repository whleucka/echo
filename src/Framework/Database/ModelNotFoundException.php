<?php

namespace Echo\Framework\Database;

use RuntimeException;

/**
 * Thrown by findOrFail() and firstOrFail() when no row matches the query.
 *
 * Carries the model class and (when known) the lookup id so controllers can
 * render a meaningful 404 without re-running the query.
 */
class ModelNotFoundException extends RuntimeException
{
    public function __construct(
        public readonly string $modelClass,
        public readonly ?string $id = null,
    ) {
        $message = "No query results for model [{$modelClass}]"
            . ($id !== null ? " with id [{$id}]" : "");
        parent::__construct($message);
    }
}
