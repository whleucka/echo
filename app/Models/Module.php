<?php

namespace App\Models;

use Echo\Framework\Database\Model;

class Module extends Model
{
    protected string $table_name = "modules";

    public function parent(): ?Module
    {
        return $this->belongsTo(Module::class, "parent_id");
    }
}
