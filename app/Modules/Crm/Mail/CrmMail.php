<?php

namespace App\Modules\Crm\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Every CRM email: TOCO branding, the Case reference, and for customers the
 * five-stage tracker (CRM-NTF-003). Body is plain text, rendered as HTML.
 */
class CrmMail extends Mailable
{
    use Queueable;

    /**
     * @param  array{stage: ?int, labels: array<int, string>}|null  $tracker
     * @param  array{label: string, url: string}|null  $button
     */
    public function __construct(
        public string $mailSubject,
        public string $text,
        public ?string $replyToEmail = null,
        public ?string $replyToName = null,
        public ?string $fromName = null,
        public ?array $tracker = null,
        public ?array $button = null,
        public bool $forCustomer = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), $this->fromName ?: (string) config('mail.from.name')),
            replyTo: $this->replyToEmail ? [new Address($this->replyToEmail, (string) $this->replyToName)] : [],
            subject: $this->mailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'crm::emails.message', text: 'crm::emails.message-text');
    }
}
