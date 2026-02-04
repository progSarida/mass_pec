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
        $disk = config('filesystems.default'); // 's3' o 'local'

        // Pulisci il path per evitare slash doppi o iniziali (fondamentale per S3)
        $folder = ltrim((string)$shipment->shipment_path, '/');
        $fileName = ltrim((string)$shipment->attachment, '/');
        $zipPath = $folder . '/' . $fileName;

        // Verifichiamo che il file esista sul disco (S3 o locale)
        if (Storage::disk($disk)->exists($zipPath)) {
            return [[
                'disk' => $disk,
                'path' => $zipPath,
                'name' => $shipment->attachment,
                'mime' => 'application/zip',
            ]];
        }

        return [];
    }
}
