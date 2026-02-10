<?php

namespace App\Models;

use Echo\Framework\Database\Model;

class Activity extends Model
{
    protected string $table_name = "activity";

    public function user(): ?User
    {
        return $this->belongsTo(User::class);
    }
}
