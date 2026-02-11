<?php

namespace App\Models;

use Echo\Framework\Database\Model;

class Session extends Model
{
    protected string $tableName = 'sessions';

    /**
     * Get the user associated with this session
     */
    public function user(): ?User
    {
        return $this->belongsTo(User::class);
    }
}
