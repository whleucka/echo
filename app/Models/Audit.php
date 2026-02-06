<?php

namespace App\Models;

use Echo\Framework\Database\Model;

class Audit extends Model
{
    public function __construct(?string $id = null)
    {
        parent::__construct('audits', $id);
    }

    /**
     * Get the user who performed the action
     */
    public function user(): ?User
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get decoded old values
     */
    public function getOldValues(): array
    {
        $values = $this->old_values;
        if (is_string($values)) {
            return json_decode($values, true) ?? [];
        }
        return $values ?? [];
    }

    /**
     * Get decoded new values
     */
    public function getNewValues(): array
    {
        $values = $this->new_values;
        if (is_string($values)) {
            return json_decode($values, true) ?? [];
        }
        return $values ?? [];
    }

    /**
     * Get the auditable type (table name)
     */
    public function getAuditableShortType(): string
    {
        return $this->auditable_type ?? '';
    }

    /**
     * Get a formatted description of the audit event
     */
    public function getDescription(): string
    {
        return sprintf(
            '%s #%d was %s',
            $this->getAuditableShortType(),
            $this->auditable_id,
            $this->event
        );
    }

    /**
     * Get the changes between old and new values
     */
    public function getChanges(): array
    {
        $old = $this->getOldValues();
        $new = $this->getNewValues();
        $changes = [];

        $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));

        foreach ($allKeys as $key) {
            $oldValue = $old[$key] ?? null;
            $newValue = $new[$key] ?? null;

            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }
}
