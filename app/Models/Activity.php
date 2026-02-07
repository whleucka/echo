<?php

namespace App\Models;

use Echo\Framework\Database\Model;

class Activity extends Model
{
    public function __construct(?string $id = null)
    {
        parent::__construct('activity', $id);
    }

    public function user(): ?User
    {
        return $this->belongsTo(User::class);
    }
}
