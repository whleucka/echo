<?php

namespace App\Services\Auth;

use App\Events\Auth\PasswordResetCompleted;
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
        $authService = container()->get(AuthService::class);
        $hashedPassword = $authService->hashPassword($newPassword);

        $user->update([
            'password' => $hashedPassword,
            'reset_token' => null,
            'reset_expires_at' => null,
        ]);

        event(new PasswordResetCompleted($user, request()->getClientIp()));

        return true;
    }
}
