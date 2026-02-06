<?php

namespace App\Models;

use Echo\Framework\Audit\Auditable;
use Echo\Framework\Database\Model;

class UserPermission extends Model
{
    use Auditable;

    public function __construct(?string $id = null)
    {
        parent::__construct('user_permissions', $id);
    }

    public function user(): ?User
    {
        return $this->belongsTo(User::class);
    }

    public function module(): ?Module
    {
        return $this->belongsTo(Module::class);
    }
}
