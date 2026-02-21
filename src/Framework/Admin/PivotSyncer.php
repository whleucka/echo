<?php

namespace Echo\Framework\Admin;

use Echo\Framework\Admin\Schema\FieldDefinition;
use RuntimeException;

class PivotSyncer
{
    public function sync(int $id, array $pendingPivotData): void
    {
        foreach ($pendingPivotData as $pivot) {
            $field = $pivot['field'];
            $values = $pivot['values'];

            // Delete existing relations
            $deleted = db()->execute(
                "DELETE FROM {$field->pivotTable} WHERE {$field->pivotLocalKey} = ?",
                [$id]
            );
            if ($deleted === false) {
                throw new RuntimeException("Failed to delete existing pivot relations from {$field->pivotTable}");
            }

            // Insert new relations
            foreach ($values as $foreignId) {
                if ($foreignId) {
                    $inserted = db()->execute(
                        "INSERT INTO {$field->pivotTable} ({$field->pivotLocalKey}, {$field->pivotForeignKey}) VALUES (?, ?)",
                        [$id, $foreignId]
                    );
                    if ($inserted === false) {
                        throw new RuntimeException("Failed to insert pivot relation into {$field->pivotTable}");
                    }
                }
            }
        }
    }

    public function getValues(FieldDefinition $field, int $recordId): array
    {
        if (!$field->hasPivot() || $recordId === 0) {
            return [];
        }

        $rows = db()->fetchAll(
            "SELECT {$field->pivotForeignKey} FROM {$field->pivotTable} WHERE {$field->pivotLocalKey} = ?",
            [$recordId]
        );

        return array_column($rows, $field->pivotForeignKey);
    }
}
