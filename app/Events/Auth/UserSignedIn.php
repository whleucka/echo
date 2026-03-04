<?php

namespace App\Events\Auth;

use App\Models\User;
use Echo\Framework\Event\Event;

/**
 * Dispatched after a successful login.
 */
class UserSignedIn extends Event
{
    public function __construct(
        public readonly User $user,
        public readonly string $ip,
    ) {}
}
