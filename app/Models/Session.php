<?php

namespace App\Models;

use Echo\Framework\Database\Model;

class Session extends Model
{
    public function __construct(?string $id = null)
    {
        parent::__construct('sessions', $id);
    }

    public function user(): ?User
    {
        return $this->belongsTo(User::class);
    }
}
