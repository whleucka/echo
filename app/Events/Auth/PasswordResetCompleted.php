<?php

namespace App\Events\Auth;

use App\Models\User;
use Echo\Framework\Event\Event;

/**
 * Dispatched after a password is successfully reset.
 */
class PasswordResetCompleted extends Event
{
    public function __construct(
        public readonly User $user,
        public readonly string $ip,
    ) {}
}
