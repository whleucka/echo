<?php

namespace App\Events\Auth;

use App\Models\User;
use Echo\Framework\Event\Event;

/**
 * Dispatched after a password reset token is generated.
 */
class PasswordResetRequested extends Event
{
    public function __construct(
        public readonly User $user,
        public readonly string $ip,
    ) {}
}
