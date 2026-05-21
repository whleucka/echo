<?php

namespace Echo\Framework\Database;

/**
 * Internal descriptor of a relationship between two models.
 *
 * App-level relation methods (hasOne / hasMany / belongsTo on a Model) keep
 * returning their natural type (?Model or array). The eager loader captures
 * this Relation via Model::captureRelation() to learn the related class and
 * join columns so it can issue a single batched WHERE ... IN (...) query.
 *
 * App code should not see or construct this class directly.
 */
class Relation
{
    public const HAS_ONE = 'hasOne';
    public const HAS_MANY = 'hasMany';
    public const BELONGS_TO = 'belongsTo';

    private mixed $resolved = null;
    private bool $isResolved = false;

    public function __construct(
        public readonly string $type,
        public readonly Model $parent,
        public readonly string $related,
        public readonly string $parentColumn,
        public readonly string $relatedColumn,
    ) {
    }

    /**
     * Resolve the relation now, returning ?Model (hasOne / belongsTo) or
     * Model[] (hasMany). Used as a manual lazy-fetch entry point; the eager
     * loader populates results via setResults() instead.
     */
    public function getResults(): mixed
    {
        if (!$this->isResolved) {
            $this->resolved = $this->resolve();
            $this->isResolved = true;
        }
        return $this->resolved;
    }

    public function setResults(mixed $results): void
    {
        $this->resolved = $results;
        $this->isResolved = true;
    }

    public function isLoaded(): bool
    {
        return $this->isResolved;
    }

    private function resolve(): mixed
    {
        $key = $this->parent->{$this->parentColumn} ?? null;
        if ($key === null) {
            return $this->type === self::HAS_MANY ? [] : null;
        }
        $query = ($this->related)::where($this->relatedColumn, $key);
        return $this->type === self::HAS_MANY ? $query->get() : $query->first();
    }
}
