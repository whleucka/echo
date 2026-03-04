<?php

namespace App\Events\Auth;

use Echo\Framework\Event\Event;

/**
 * Dispatched after a user logs out.
 */
class UserSignedOut extends Event
{
    public function __construct(
        public readonly ?int $userId,
        public readonly ?string $email,
        public readonly string $ip,
    ) {}
}
