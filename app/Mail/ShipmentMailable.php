<?php

namespace App\Mail;

use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;

class ShipmentMailable extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Shipment $shipment,
        public array $attachmentsData = [],
        public ?string $customSubject = null
    ) {}

    /**
     * Get the message envelope (Oggetto e Mittente).
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            // Recupera il mittente direttamente dal Sender legato alla spedizione
            from: new Address(
                $this->shipment->sender->address,
                $this->shipment->sender->public_name
            ),
            subject: $this->customSubject ?? $this->shipment->mail_object ?? 'Invio Spedizione',
        );
    }

    /**
     * Get the message content definition (Il corpo della mail).
     */
    public function content(): Content
    {
        // Usiamo view() se hai un template blade,
        // oppure htmlString se il corpo è testo semplice/HTML salvato nel DB
        return new Content(
            htmlString: nl2br($this->shipment->mail_body),
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        $attachmentsList = [];

        foreach ($this->attachmentsData as $attachment) {
            $attachmentsList[] = Attachment::fromStorageDisk(
                    $attachment['disk'],
                    $attachment['path']
                )
                ->as($attachment['name'])
                ->withMime($attachment['mime']);
        }

        return $attachmentsList;
    }
}
