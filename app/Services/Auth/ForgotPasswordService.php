<?php

namespace App\Services\Auth;

use App\Events\Auth\PasswordResetRequested;
use App\Models\User;
use Echo\Framework\Crypto\Crypto;
use Echo\Framework\Mail\Mailable;

class ForgotPasswordService
{
    public function requestReset(string $email): void
    {
        $ip = request()->getClientIp();

        $user = User::where("email", $email)->first();

        if (!$user) {
            logger()->channel('auth')->info('Password reset requested for unknown email', [
                'email' => $email,
                'ip' => $ip,
            ]);
            return;
        }

        $rawToken = Crypto::randomToken(32);
        $hashedToken = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $user->update([
            'reset_token' => $hashedToken,
            'reset_expires_at' => $expiresAt,
        ]);

        $resetUrl = config('app.url')
            . uri('auth.password-reset.index')
            . '?token=' . urlencode($rawToken)
            . '&email=' . urlencode($user->email);

        $mailable = Mailable::create()
            ->to($user->email, $user->fullName())
            ->subject('Reset Your Password')
            ->template('emails/password-reset.html.twig', [
                'user' => $user,
                'reset_url' => $resetUrl,
                'expires_minutes' => 60,
            ]);

        mailer()->queue($mailable);

        event(new PasswordResetRequested($user, $ip));
    }
}
