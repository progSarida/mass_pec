<?php

namespace App\Services;

use App\Models\Sender;
use App\Models\Shipment;
use Illuminate\Support\Facades\Storage;
use Exception;

class ShipmentEmailService
{
    protected Sender $sender;

    /**
     * Imposta il mittente (Sender) recuperandolo dal record con ID 1
     * o dall'ID associato alla spedizione.
     */
    public function setSender(int $senderId = 1): self
    {
        $sender = Sender::find($senderId);

        if (!$sender) {
            throw new Exception("Configurazione Mittente (Sender) non trovata (ID: {$senderId})");
        }

        $this->sender = $sender;
        return $this;
    }

    public function getSender(): Sender
    {
        if (!isset($this->sender)) {
            $this->setSender(1); // Default a 1 se non impostato
        }
        return $this->sender;
    }

    /**
     * Recupera i destinatari dalla tabella receivers
     */
    public function extractRecipients(Shipment $shipment): array
    {
        return $shipment->receivers()
            ->select('id', 'address')
            ->get()
            ->toArray();
    }

    /**
     * Prepara il file ZIP come allegato
     */
    public function prepareAttachments(Shipment $shipment): array
    {
        $attachments = [];

        // Costruiamo il percorso: shipments/{id}/{nome_zip}
        $zipPath = $shipment->shipment_path . '/' . $shipment->attachment;

        if (Storage::exists($zipPath)) {
            $attachments[] = [
                'path' => Storage::path($zipPath),
                'name' => $shipment->attachment,
                'mime' => 'application/zip',
            ];
        }

        return $attachments;
    }
}
