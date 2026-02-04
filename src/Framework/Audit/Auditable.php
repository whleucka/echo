<?php

namespace Echo\Framework\Audit;

use Echo\Framework\Database\QueryBuilder;
use PDO;

/**
 * Trait Auditable
 *
 * Add this trait to models that need audit logging.
 * It overrides create, save, update, and delete methods to log changes.
 */
trait Auditable
{
    /**
     * Create a new model with audit logging
     */
    public static function create(array $data): static|bool
    {
        $class = get_called_class();
        $model = new $class();
        $qb = new QueryBuilder();

        $result = $qb
            ->insert($data)
            ->into($model->table_name)
            ->params(array_values($data))
            ->execute();

        if ($result && $model->auto_increment) {
            $id = db()->lastInsertId();
            $newModel = self::find($id);

            if ($newModel) {
                AuditLogger::logCreated(
                    $class,
                    $id,
                    $newModel->getAttributes()
                );
            }

            return $newModel;
        } elseif ($result && !$model->auto_increment) {
            return true;
        }

        return false;
    }

    /**
     * Save the model with audit logging
     */
    public function save(): static
    {
        $key = $this->primary_key;
        $oldAttributes = $this->getOldAttributes();

        $params = [...array_values($this->attributes), $this->id];
        $qb = new QueryBuilder();

        $result = $qb
            ->update($this->attributes)
            ->table($this->table_name)
            ->where(["$key = ?"])
            ->params($params)
            ->execute();

        if ($result) {
            $this->loadAttributesForAudit($this->id);

            AuditLogger::logUpdated(
                get_class($this),
                $this->id,
                $oldAttributes,
                $this->getAttributes()
            );
        }

        return $this;
    }

    /**
     * Update the model with audit logging
     */
    public function update(array $data): static
    {
        $key = $this->primary_key;
        $oldAttributes = $this->getAttributes();

        $params = [...array_values($data), $this->id];
        $qb = new QueryBuilder();

        $result = $qb
            ->update($data)
            ->table($this->table_name)
            ->where(["$key = ?"])
            ->params($params)
            ->execute();

        if ($result) {
            $this->loadAttributesForAudit($this->id);

            AuditLogger::logUpdated(
                get_class($this),
                $this->id,
                $oldAttributes,
                $this->getAttributes()
            );
        }

        return $this;
    }

    /**
     * Delete the model with audit logging
     */
    public function delete(): bool
    {
        $key = $this->primary_key;
        $oldAttributes = $this->getAttributes();
        $qb = new QueryBuilder();

        $result = $qb
            ->delete()
            ->from($this->table_name)
            ->where(["$key = ?"], $this->id)
            ->execute();

        if ($result) {
            AuditLogger::logDeleted(
                get_class($this),
                $this->id,
                $oldAttributes
            );
        }

        return (bool) $result;
    }

    /**
     * Get old attributes before update (for save method)
     */
    private function getOldAttributes(): array
    {
        $key = $this->primary_key;
        $qb = new QueryBuilder();

        $result = $qb
            ->select($this->columns)
            ->from($this->table_name)
            ->where(["$key = ?"], $this->id)
            ->execute()
            ->fetch(PDO::FETCH_ASSOC);

        return $result ?: [];
    }

    /**
     * Load attributes for audit (after save/update)
     */
    private function loadAttributesForAudit(string $id): void
    {
        $key = $this->primary_key;
        $qb = new QueryBuilder();

        $result = $qb
            ->select($this->columns)
            ->from($this->table_name)
            ->where(["$key = ?"], $id)
            ->execute()
            ->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $this->attributes = $result;
        }
    }
}
