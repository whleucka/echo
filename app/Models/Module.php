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
        return Module::where('parent_id', $this->id)
            ->andWhere('enabled', '1')
            ->orderBy('item_order')
            ->get() ?? [];
    }

    /**
     * Get the admin URL for this module
     */
    public function url(): string
    {
        return uri("{$this->link}.admin.index") ?? '/';
    }
}
