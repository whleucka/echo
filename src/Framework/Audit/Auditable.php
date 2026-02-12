<?php

namespace Echo\Framework\Audit;

use Echo\Framework\Database\QueryBuilder;
use PDO;

/**
 * Trait Auditable
 *
 * Add this trait to models that need audit logging.
 * It overrides create, save, update, delete, and createBulk methods to log changes.
 */
trait Auditable
{
    /**
     * Create a new model with audit logging
     */
    public static function create(array $data): static|bool
    {
        $result = parent::create($data);

        if ($result instanceof static) {
            AuditLogger::logCreated(
                $result->tableName,
                $result->id,
                $result->getAttributes()
            );
        }

        return $result;
    }

    /**
     * Save the model with audit logging
     */
    public function save(): static
    {
        $oldAttributes = $this->getOldAttributes();
        parent::save();

        AuditLogger::logUpdated(
            $this->tableName,
            $this->id,
            $oldAttributes,
            $this->getAttributes()
        );

        return $this;
    }

    /**
     * Update the model with audit logging
     */
    public function update(array $data): static
    {
        $oldAttributes = $this->getAttributes();
        parent::update($data);

        AuditLogger::logUpdated(
            $this->tableName,
            $this->id,
            $oldAttributes,
            $this->getAttributes()
        );

        return $this;
    }

    /**
     * Delete the model with audit logging
     */
    public function delete(): bool
    {
        $oldAttributes = $this->getAttributes();
        $result = parent::delete();

        if ($result) {
            AuditLogger::logDeleted(
                $this->tableName,
                $this->id,
                $oldAttributes
            );
        }

        return $result;
    }

    /**
     * Bulk insert multiple records with audit logging
     */
    public static function createBulk(array $records): bool
    {
        $result = parent::createBulk($records);

        if ($result && !empty($records)) {
            $model = new static();

            if ($model->autoIncrement) {
                $firstId = (int) db()->lastInsertId();
                foreach ($records as $i => $record) {
                    AuditLogger::logCreated(
                        $model->tableName,
                        $firstId + $i,
                        $record
                    );
                }
            } else {
                foreach ($records as $record) {
                    AuditLogger::logCreated(
                        $model->tableName,
                        $record[$model->primaryKey] ?? 0,
                        $record
                    );
                }
            }
        }

        return $result;
    }

    /**
     * Get old attributes from DB before save (in-memory attrs are already dirty)
     */
    private function getOldAttributes(): array
    {
        $key = $this->primaryKey;
        $qb = new QueryBuilder();

        $result = $qb
            ->select($this->columns)
            ->from($this->tableName)
            ->where(["$key = ?"], $this->id)
            ->execute()
            ->fetch(PDO::FETCH_ASSOC);

        return $result ?: [];
    }
}
