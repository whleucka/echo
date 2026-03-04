<?php

namespace App\Services\Auth;

use App\Events\Auth\UserRegistered;
use App\Models\User;

class RegisterService
{
    public function register(string $first_name, string $surname, string $email, string $password): bool
    {
        $service = container()->get(AuthService::class);
        $ip = request()->getClientIp();

        $user = User::create([
            "first_name" => $first_name,
            "surname" => $surname,
            "email" => $email,
            "password" => $service->hashPassword($password),
            "role" => "standard",
        ]);

        if ($user) {
            $user->grantDefaultPermissions();
            session()->regenerate();
            session()->set("user_uuid", $user->uuid);
            event(new UserRegistered($user, $ip));
            return true;
        }

        logger()->channel('auth')->warning('Registration failed', [
            'email' => $email,
            'ip' => $ip,
        ]);

        return false;
    }
}
