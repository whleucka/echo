<?php

namespace App\Providers;

use Echo\Framework\Mail\Mailer;
use Echo\Framework\Mail\EmailQueue;
use Echo\Framework\Support\ServiceProvider;

/**
 * Mail Service Provider
 *
 * Registers the Mailer and EmailQueue services.
 */
class MailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->set(Mailer::class, function () {
            return new Mailer(
                host: config('mail.host'),
                port: (int) config('mail.port'),
                username: config('mail.username'),
                password: config('mail.password'),
                encryption: config('mail.encryption'),
                fromAddress: config('mail.from_address'),
                fromName: config('mail.from_name'),
            );
        });

        $this->container->set(EmailQueue::class, function () {
            return new EmailQueue(
                $this->container->get(Mailer::class),
            );
        });
    }

    public function boot(): void
    {
        // Nothing to boot
    }
}
