<?php

namespace App\Events\Auth;

use Echo\Framework\Event\Event;

/**
 * Dispatched after a failed login attempt.
 */
class SignInFailed extends Event
{
    public function __construct(
        public readonly string $email,
        public readonly string $ip,
        public readonly string $reason,
    ) {}
}
