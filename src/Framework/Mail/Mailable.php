<?php

namespace Echo\Framework\Mail;

/**
 * Fluent value object for composing an email message.
 *
 * Usage:
 *   $mail = Mailable::create()
 *       ->to('user@example.com')
 *       ->subject('Welcome')
 *       ->html('<h1>Hello</h1>')           // raw HTML string
 *       ->template('emails/welcome.html.twig', ['name' => 'Will'])  // OR twig template
 *       ->attach('/path/to/file.pdf')
 *       ->replyTo('support@example.com');
 */
class Mailable
{
    private array $to = [];
    private array $cc = [];
    private array $bcc = [];
    private ?string $fromAddress = null;
    private ?string $fromName = null;
    private ?string $replyToAddress = null;
    private ?string $replyToName = null;
    private string $subject = '';
    private ?string $textBody = null;
    private ?string $htmlBody = null;
    private ?string $templatePath = null;
    private array $templateData = [];
    private array $attachments = [];
    private ?string $scheduledAt = null;

    public static function create(): static
    {
        return new static();
    }

    // ── Recipients ──────────────────────────────────────────

    public function to(string $address, string $name = ''): static
    {
        $this->to[] = ['address' => $address, 'name' => $name];
        return $this;
    }

    public function cc(string $address, string $name = ''): static
    {
        $this->cc[] = ['address' => $address, 'name' => $name];
        return $this;
    }

    public function bcc(string $address, string $name = ''): static
    {
        $this->bcc[] = ['address' => $address, 'name' => $name];
        return $this;
    }

    public function from(string $address, string $name = ''): static
    {
        $this->fromAddress = $address;
        $this->fromName = $name;
        return $this;
    }

    public function replyTo(string $address, string $name = ''): static
    {
        $this->replyToAddress = $address;
        $this->replyToName = $name;
        return $this;
    }

    // ── Content ─────────────────────────────────────────────

    public function subject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Set plain text body
     */
    public function text(string $body): static
    {
        $this->textBody = $body;
        return $this;
    }

    /**
     * Set raw HTML body (mutually exclusive with template)
     */
    public function html(string $body): static
    {
        $this->htmlBody = $body;
        $this->templatePath = null;
        $this->templateData = [];
        return $this;
    }

    /**
     * Use a Twig template for the HTML body (mutually exclusive with html)
     */
    public function template(string $path, array $data = []): static
    {
        $this->templatePath = $path;
        $this->templateData = $data;
        $this->htmlBody = null;
        return $this;
    }

    // ── Attachments ─────────────────────────────────────────

    /**
     * Attach a file by path, or pass raw content with a name
     */
    public function attach(string $path, ?string $name = null, ?string $mimeType = null): static
    {
        $this->attachments[] = [
            'type' => 'path',
            'path' => $path,
            'name' => $name ?? basename($path),
            'mime' => $mimeType,
        ];
        return $this;
    }

    /**
     * Attach raw string content
     */
    public function attachData(string $content, string $name, ?string $mimeType = null): static
    {
        $this->attachments[] = [
            'type' => 'raw',
            'content' => $content,
            'name' => $name,
            'mime' => $mimeType,
        ];
        return $this;
    }

    // ── Scheduling ──────────────────────────────────────────

    /**
     * Schedule email for future delivery (Y-m-d H:i:s)
     */
    public function delay(string $datetime): static
    {
        $this->scheduledAt = $datetime;
        return $this;
    }

    // ── Getters ─────────────────────────────────────────────

    public function getTo(): array { return $this->to; }
    public function getCc(): array { return $this->cc; }
    public function getBcc(): array { return $this->bcc; }
    public function getFromAddress(): ?string { return $this->fromAddress; }
    public function getFromName(): ?string { return $this->fromName; }
    public function getReplyToAddress(): ?string { return $this->replyToAddress; }
    public function getReplyToName(): ?string { return $this->replyToName; }
    public function getSubject(): string { return $this->subject; }
    public function getTextBody(): ?string { return $this->textBody; }
    public function getHtmlBody(): ?string { return $this->htmlBody; }
    public function getTemplatePath(): ?string { return $this->templatePath; }
    public function getTemplateData(): array { return $this->templateData; }
    public function getAttachments(): array { return $this->attachments; }
    public function getScheduledAt(): ?string { return $this->scheduledAt; }

    /**
     * Resolve HTML body — renders Twig template if set, otherwise returns raw HTML
     */
    public function resolveHtml(): ?string
    {
        if ($this->templatePath) {
            return twig()->render($this->templatePath, $this->templateData);
        }
        return $this->htmlBody;
    }

    /**
     * Serialize to array for queue storage
     */
    public function toArray(): array
    {
        return [
            'to' => $this->to,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'from_address' => $this->fromAddress,
            'from_name' => $this->fromName,
            'reply_to_address' => $this->replyToAddress,
            'reply_to_name' => $this->replyToName,
            'subject' => $this->subject,
            'text_body' => $this->textBody,
            'html_body' => $this->resolveHtml(),
            'attachments' => $this->attachments,
        ];
    }
}
