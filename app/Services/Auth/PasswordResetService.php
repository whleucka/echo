<?php

namespace App\Services\Auth;

use App\Models\User;

class PasswordResetService
{
    public function validateToken(string $email, string $token): ?User
    {
        $user = User::where("email", $email)->first();

        if (!$user || !$user->reset_token || !$user->reset_expires_at) {
            return null;
        }

        if (strtotime($user->reset_expires_at) < time()) {
            return null;
        }

        $hashedToken = hash('sha256', $token);

        if (!hash_equals($user->reset_token, $hashedToken)) {
            return null;
        }

        return $user;
    }

    public function resetPassword(User $user, string $newPassword): bool
    {
        $log = logger()->channel('auth');
        $ip = request()->getClientIp();

        $authService = container()->get(AuthService::class);
        $hashedPassword = $authService->hashPassword($newPassword);

        $user->update([
            'password' => $hashedPassword,
            'reset_token' => null,
            'reset_expires_at' => null,
        ]);

        $log->info('Password reset successful', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $ip,
        ]);

        return true;
    }
}
