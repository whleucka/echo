<?php

namespace App\Models;

use Echo\Framework\Audit\Auditable;
use Echo\Framework\Database\Model;

class UserPermission extends Model
{
    use Auditable;

    protected string $tableName = "user_permissions";

    public function user(): ?User
    {
        return $this->belongsTo(User::class);
    }

    public function module(): ?Module
    {
        return $this->belongsTo(Module::class);
    }
}
