<?php

namespace App\Services\Auth;

use App\Models\User;

class SignInService
{
    public function signIn(string $email_address, string $password): bool
    {
        $log = logger()->channel('auth');
        $ip = request()->getClientIp();
        $user = User::where("email", $email_address)->first();
        $service = container()->get(AuthService::class);

        if ($user && $service->verifyPassword($password, $user->password)) {
            session()->regenerate();
            session()->set("user_uuid", $user->uuid);
            session()->set("dark_mode", $user->theme === "dark");
            $log->info('Login successful', [
                'user_id' => $user->id,
                'email' => $email_address,
                'ip' => $ip,
            ]);
            return true;
        }

        $log->warning('Login failed', [
            'email' => $email_address,
            'ip' => $ip,
            'reason' => $user ? 'invalid_password' : 'unknown_email',
        ]);

        return false;
    }

    public function signOut(): void
    {
        $log = logger()->channel('auth');
        $currentUser = user();
        $log->info('Logout', [
            'user_id' => $currentUser?->id,
            'email' => $currentUser?->email,
            'ip' => request()->getClientIp(),
        ]);
        session()->destroy();
    }
}
