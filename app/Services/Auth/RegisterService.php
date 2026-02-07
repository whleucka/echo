<?php

namespace App\Services\Auth;

use App\Models\User;

class RegisterService
{
    public function register(string $first_name, string $surname, string $email, string $password): bool
    {
        $log = logger()->channel('auth');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $user = User::create([
            "first_name" => $first_name,
            "surname" => $surname,
            "email" => $email,
            "password" => password_hash($password, PASSWORD_ARGON2I),
            "role" => "standard",
        ]);

        if ($user) {
            session()->regenerate();
            session()->set("user_uuid", $user->uuid);
            $log->info('Registration successful', [
                'user_id' => $user->id,
                'email' => $email,
                'ip' => $ip,
            ]);
            return true;
        }

        $log->warning('Registration failed', [
            'email' => $email,
            'ip' => $ip,
        ]);

        return false;
    }
}
