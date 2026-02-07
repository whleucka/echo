<?php

namespace Echo\Framework\Mail;

use Echo\Framework\Logging\Logger;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Sends emails via PHPMailer using SMTP config.
 *
 * Can send immediately or queue for background delivery.
 */
class Mailer
{
    private Logger $log;

    public function __construct(
        private string $host,
        private int $port,
        private string $username,
        private string $password,
        private string $encryption,
        private string $fromAddress,
        private string $fromName,
    ) {
        $this->log = logger()->channel('mail');
    }

    /**
     * Send a Mailable immediately.
     *
     * If the mailable has a scheduled time, it will be queued instead.
     *
     * @return bool True on success
     * @throws \RuntimeException on failure
     */
    public function send(Mailable $mailable): bool
    {
        // If scheduled for the future, queue it
        if ($mailable->getScheduledAt()) {
            return $this->queue($mailable);
        }

        $mail = $this->createPHPMailer();
        $this->configureSender($mail, $mailable);
        $this->configureRecipients($mail, $mailable);
        $this->configureContent($mail, $mailable);
        $this->configureAttachments($mail, $mailable);

        $recipients = implode(', ', array_map(fn($r) => $r['address'], $mailable->getTo()));

        try {
            $mail->send();
            $this->log->info('Email sent', [
                'to' => $recipients,
                'subject' => $mailable->getSubject(),
            ]);
            return true;
        } catch (PHPMailerException $e) {
            $this->log->error('Email send failed', [
                'to' => $recipients,
                'subject' => $mailable->getSubject(),
                'error' => $mail->ErrorInfo,
            ]);
            throw new \RuntimeException("Email failed: " . $mail->ErrorInfo, 0, $e);
        }
    }

    /**
     * Queue a Mailable for background delivery via the email_jobs table.
     */
    public function queue(Mailable $mailable): bool
    {
        $data = $mailable->toArray();

        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);

        $recipients = implode(', ', array_map(
            fn(array $r) => $r['address'],
            $mailable->getTo()
        ));

        \App\Models\EmailJob::create([
            'to_address' => $recipients,
            'subject' => $mailable->getSubject(),
            'payload' => $payload,
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => config('mail.max_retries') ?? 3,
            'scheduled_at' => $mailable->getScheduledAt(),
        ]);

        $this->log->info('Email queued', [
            'to' => $recipients,
            'subject' => $mailable->getSubject(),
            'scheduled_at' => $mailable->getScheduledAt(),
        ]);

        return true;
    }

    /**
     * Send from a pre-serialized payload array (used by queue worker).
     *
     * @return bool True on success
     * @throws \RuntimeException on failure
     */
    public function sendFromPayload(array $data): bool
    {
        $mail = $this->createPHPMailer();

        // Sender
        $from = $data['from_address'] ?? $this->fromAddress;
        $fromName = $data['from_name'] ?? $this->fromName;
        $mail->setFrom($from, $fromName);

        if (!empty($data['reply_to_address'])) {
            $mail->addReplyTo($data['reply_to_address'], $data['reply_to_name'] ?? '');
        }

        // Recipients
        foreach ($data['to'] ?? [] as $r) {
            $mail->addAddress($r['address'], $r['name'] ?? '');
        }
        foreach ($data['cc'] ?? [] as $r) {
            $mail->addCC($r['address'], $r['name'] ?? '');
        }
        foreach ($data['bcc'] ?? [] as $r) {
            $mail->addBCC($r['address'], $r['name'] ?? '');
        }

        // Content
        $mail->Subject = $data['subject'] ?? '';

        if (!empty($data['html_body'])) {
            $mail->isHTML(true);
            $mail->Body = $data['html_body'];
            $mail->AltBody = $data['text_body'] ?? strip_tags($data['html_body']);
        } elseif (!empty($data['text_body'])) {
            $mail->isHTML(false);
            $mail->Body = $data['text_body'];
        }

        // Attachments
        foreach ($data['attachments'] ?? [] as $att) {
            if ($att['type'] === 'path' && file_exists($att['path'])) {
                $mail->addAttachment($att['path'], $att['name'] ?? '', 'base64', $att['mime'] ?? '');
            } elseif ($att['type'] === 'raw') {
                $mail->addStringAttachment($att['content'], $att['name'] ?? 'attachment', 'base64', $att['mime'] ?? '');
            }
        }

        $recipients = implode(', ', array_map(fn($r) => $r['address'], $data['to'] ?? []));

        try {
            $mail->send();
            $this->log->info('Email sent from queue', [
                'to' => $recipients,
                'subject' => $data['subject'] ?? '',
            ]);
            return true;
        } catch (PHPMailerException $e) {
            $this->log->error('Email send from queue failed', [
                'to' => $recipients,
                'subject' => $data['subject'] ?? '',
                'error' => $mail->ErrorInfo,
            ]);
            throw new \RuntimeException("Email failed: " . $mail->ErrorInfo, 0, $e);
        }
    }

    // ── Private helpers ─────────────────────────────────────

    private function createPHPMailer(): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $this->host;
        $mail->Port = $this->port;
        $mail->CharSet = PHPMailer::CHARSET_UTF8;

        if ($this->username || $this->password) {
            $mail->SMTPAuth = true;
            $mail->Username = $this->username;
            $mail->Password = $this->password;
        }

        if ($this->encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($this->encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        return $mail;
    }

    private function configureSender(PHPMailer $mail, Mailable $mailable): void
    {
        $from = $mailable->getFromAddress() ?? $this->fromAddress;
        $name = $mailable->getFromName() ?? $this->fromName;
        $mail->setFrom($from, $name);

        if ($mailable->getReplyToAddress()) {
            $mail->addReplyTo($mailable->getReplyToAddress(), $mailable->getReplyToName() ?? '');
        }
    }

    private function configureRecipients(PHPMailer $mail, Mailable $mailable): void
    {
        foreach ($mailable->getTo() as $r) {
            $mail->addAddress($r['address'], $r['name']);
        }
        foreach ($mailable->getCc() as $r) {
            $mail->addCC($r['address'], $r['name']);
        }
        foreach ($mailable->getBcc() as $r) {
            $mail->addBCC($r['address'], $r['name']);
        }
    }

    private function configureContent(PHPMailer $mail, Mailable $mailable): void
    {
        $mail->Subject = $mailable->getSubject();
        $html = $mailable->resolveHtml();

        if ($html) {
            $mail->isHTML(true);
            $mail->Body = $html;
            $mail->AltBody = $mailable->getTextBody() ?? strip_tags($html);
        } elseif ($mailable->getTextBody()) {
            $mail->isHTML(false);
            $mail->Body = $mailable->getTextBody();
        }
    }

    private function configureAttachments(PHPMailer $mail, Mailable $mailable): void
    {
        foreach ($mailable->getAttachments() as $att) {
            if ($att['type'] === 'path' && file_exists($att['path'])) {
                $mail->addAttachment($att['path'], $att['name'], 'base64', $att['mime'] ?? '');
            } elseif ($att['type'] === 'raw') {
                $mail->addStringAttachment($att['content'], $att['name'], 'base64', $att['mime'] ?? '');
            }
        }
    }
}
