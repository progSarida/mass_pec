<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class RegistryMailable extends Mailable
{
    use Queueable, SerializesModels;

    public string $mailSubject;
    public array $mailAttachments;

    public function __construct(
        string $subject,
        public string $body,
        public string $fromAddress,
        public string $fromName,
        array $attachments = [],
        public ?string $protocolNumber = null,
    ) {
        $this->mailSubject = $subject;
        $this->mailAttachments = $attachments;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromAddress, $this->fromName),
            subject: $this->protocolNumber
                ? "[{$this->protocolNumber}] {$this->mailSubject}"
                : $this->mailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->body,
        );
    }

    public function attachments(): array
    {
        return collect($this->mailAttachments)
            ->map(fn($attachment) => Attachment::fromPath($attachment['path'])
                ->as($attachment['name'])
                ->withMime($attachment['mime'] ?? 'application/octet-stream')
            )
            ->toArray();
    }
}
