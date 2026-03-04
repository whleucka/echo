<?php

namespace App\Listeners;

use App\Events\Auth\PasswordResetCompleted;
use App\Events\Auth\PasswordResetRequested;
use App\Events\Auth\SignInFailed;
use App\Events\Auth\UserRegistered;
use App\Events\Auth\UserSignedIn;
use App\Events\Auth\UserSignedOut;
use Echo\Framework\Event\EventInterface;
use Echo\Framework\Event\ListenerInterface;

/**
 * Auth Listener
 *
 * Handles all authentication events and logs them
 * to the auth logging channel.
 */
class AuthListener implements ListenerInterface
{
    public function handle(EventInterface $event): void
    {
        try {
            match (true) {
                $event instanceof UserSignedIn => $this->onSignedIn($event),
                $event instanceof SignInFailed => $this->onSignInFailed($event),
                $event instanceof UserSignedOut => $this->onSignedOut($event),
                $event instanceof UserRegistered => $this->onRegistered($event),
                $event instanceof PasswordResetRequested => $this->onResetRequested($event),
                $event instanceof PasswordResetCompleted => $this->onResetCompleted($event),
                default => null,
            };
        } catch (\Exception|\Error $e) {
            error_log("-- Auth listener error: {$e->getMessage()} --");
        }
    }

    private function onSignedIn(UserSignedIn $event): void
    {
        logger()->channel('auth')->info('Login successful', [
            'user_id' => $event->user->id,
            'email' => $event->user->email,
            'ip' => $event->ip,
        ]);
    }

    private function onSignInFailed(SignInFailed $event): void
    {
        logger()->channel('auth')->warning('Login failed', [
            'email' => $event->email,
            'ip' => $event->ip,
            'reason' => $event->reason,
        ]);
    }

    private function onSignedOut(UserSignedOut $event): void
    {
        logger()->channel('auth')->info('Logout', [
            'user_id' => $event->userId,
            'email' => $event->email,
            'ip' => $event->ip,
        ]);
    }

    private function onRegistered(UserRegistered $event): void
    {
        logger()->channel('auth')->info('Registration successful', [
            'user_id' => $event->user->id,
            'email' => $event->user->email,
            'ip' => $event->ip,
        ]);
    }

    private function onResetRequested(PasswordResetRequested $event): void
    {
        logger()->channel('auth')->info('Password reset token generated', [
            'user_id' => $event->user->id,
            'email' => $event->user->email,
            'ip' => $event->ip,
        ]);
    }

    private function onResetCompleted(PasswordResetCompleted $event): void
    {
        logger()->channel('auth')->info('Password reset successful', [
            'user_id' => $event->user->id,
            'email' => $event->user->email,
            'ip' => $event->ip,
        ]);
    }
}
