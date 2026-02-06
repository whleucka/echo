<?php

namespace App\Models;

use Echo\Framework\Database\Model;

class Module extends Model
{
    public function __construct(?string $id = null)
    {
        parent::__construct('modules', $id);
    }

    public function parent(): ?Module
    {
        return $this->belongsTo(Module::class, "parent_id");
    }
}
