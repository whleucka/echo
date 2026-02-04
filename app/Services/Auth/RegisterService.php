<?php

namespace App\Services\Auth;

use App\Models\User;

class RegisterService
{
    public function register(string $first_name, string $surname, string $email, string $password): bool
    {
        $user = User::create([
            "first_name" => $first_name,
            "surname" => $surname,
            "email" => $email,
            "password" => password_hash($password, PASSWORD_ARGON2I),
            "role" => "standard",
        ]);

        if ($user) {
            session()->set("user_uuid", $user->uuid);
            return true;
        }

        return false;
    }
}
