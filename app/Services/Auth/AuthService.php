<?php

namespace App\Services\Auth;

class AuthService
{
    public function hashPassword(string $password): string
    {
        return password_hash($password, config("security.password_algorithm"));
    }
}
