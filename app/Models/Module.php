<?php

namespace App\Models;

use Echo\Framework\Database\Model;

class Module extends Model
{
    protected string $tableName = "modules";

    public function parent(): ?Module
    {
        return $this->belongsTo(Module::class, "parent_id");
    }

    /**
     * Get child modules
     */
    public function children(): array
    {
        $result = Module::where('parent_id', $this->id)
            ->andWhere('enabled', '1')
            ->orderBy('item_order')
            ->get();

        if (is_null($result)) {
            return [];
        }

        return is_array($result) ? $result : [$result];
    }

    /**
     * Get the admin URL for this module
     */
    public function url(): string
    {
        return '/admin/' . $this->link;
    }
}
