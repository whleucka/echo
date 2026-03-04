# Email

Echo provides a `Mailable` API for sending and queueing emails via SMTP.

## Sending Email

```php
use Echo\Framework\Mail\Mailable;

// Send immediately
mailer()->send(
    Mailable::create()
        ->to('user@example.com')
        ->subject('Welcome!')
        ->template('emails/welcome.html.twig', ['name' => 'Will'])
);

// Queue for background delivery
mailer()->queue(
    Mailable::create()
        ->to('user@example.com')
        ->subject('Newsletter')
        ->template('emails/newsletter.html.twig', $data)
);
```

## Configuration

Set SMTP credentials in `.env`:

```
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="My App"
```

## Queue Commands

```bash
./bin/console mail:queue    # process pending email jobs
./bin/console mail:status   # show email queue status
./bin/console mail:purge    # purge old sent/exhausted jobs
```
