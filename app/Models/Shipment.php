<?php

namespace App\Models;

use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class Shipment extends Model
{
    protected $fillable = [
        'description',
        'sender_id',
        'mail_object',
        'mail_body',
        'attachment',
        'send_type',
        'insert_date',
        'shipment_path',
        'total_no_mails',
        'no_mails_sended',
        'no_mails_to_send',
        'no_send_receipt',
        'no_missed_send_receipt',
        'no_delivery_receipt',
        'no_missed_delivery_receipt',
        'no_anomaly_receipt',
        'no_anomaly_receipt',
        'extraction_date',
        'extraction_zip_file',
    ];

    protected $casts = [
        //
    ];

    public array $receiverList = [];
    public array $attachmentList = [];

    public function sender(){
        return $this->belongsTo(Sender::class);
    }

    public function receivers(){
        return $this->hasMany(Receiver::class);
    }

    public function shipmentErrors(){
        return $this->hasMany(ShipmentError::class);
    }

    protected static function booted()
    {
        static::creating(function ($shipment) {
            $shipment->sender_id = 1;
            $shipment->insert_date = date('Y-m-d');                                                                                 // inserisco la data di oggi come data di inserimento della spedizione
            // $shipment->attachment = 'allegati_2025-11-03_16-27-00.zip';
            $shipment->attachment = 'allegati_' . now()->format('Y-m-d_H-i-s') . '.zip';
        });

        static::created(function ($shipment) {
            //
        });

        static::updating(function ($shipment) {
            //
        });

        static::saved(function ($shipment) {
            //
        });

        static::deleting(function ($shipment) {
            //
        });

        static::deleted(function ($shipment) {
            if (Storage::exists($shipment->shipment_path)) {
                Storage::deleteDirectory($shipment->shipment_path);
            }
        });

    }

    public function createShipmentFolderOld(): void
    {
        $this->shipment_path = "/archive/shipments/{$this->id}/";
        $this->save();

        $fullPath = storage_path("app/public" . $this->shipment_path);
        if (!File::exists($fullPath)) {
            File::makeDirectory($fullPath, 0755, true);
        }
    }

    public function createShipmentFolder(): void
    {
        $this->shipment_path = "archive/shipments/{$this->id}";
        $this->save();

        if (!Storage::exists($this->shipment_path)) {
            Storage::makeDirectory($this->shipment_path);
        }
    }

    public function createZipOld(): void
    {
        if (empty($this->attachmentList)) return;

        $attachments = Attachment::whereIn('id', $this->attachmentList)->get();                                                     // recupero gli allegati dalla tabella attachments
        if ($attachments->isEmpty()) return;

        $zipFileName = $this->attachment;                                                                                           // nome del file ZIP (da $shipment->attachment)
        $zipPath = storage_path("app/public/archive/shipments/{$this->id}/{$zipFileName}");                                         // percorso in cui salvare lo ZIP

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            Notification::make()->title('Errore creazione ZIP')->danger()->send();
            return;
        }

        foreach ($attachments as $attachment) {
            $filePath = storage_path('app/public/' . $attachment->path);
            if (file_exists($filePath)) {
                $zip->addFile($filePath, basename($attachment->path));                                                              // inserisco file in ZIP
            }
        }

        $zip->close();                                                                                                              // chiudo ZIP

        Notification::make()
            ->title("ZIP creato correttamente ({$zipFileName})")
            ->success()
            ->send();
    }

    public function createZip($attachmentList): void
    {
        if (empty($attachmentList)) return;

        $attachments = Attachment::whereIn('id', $attachmentList)->get();
        if ($attachments->isEmpty()) return;

        $zipFileName = $this->attachment;
        $tempZipPath = storage_path('app/temp/' . uniqid() . '.zip');

        // Assicurati che la directory temp esista
        if (!is_dir(dirname($tempZipPath))) {
            mkdir(dirname($tempZipPath), 0755, true);
        }

        $zip = new ZipArchive();

        if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            Notification::make()->title('Errore creazione ZIP')->danger()->send();
            return;
        }

        foreach ($attachments as $attachment) {
            if (Storage::exists($attachment->path)) {
                $zip->addFromString(
                    basename($attachment->path),
                    Storage::get($attachment->path)
                );
            }
        }

        $zip->close();

        // Sposta lo ZIP nella posizione finale usando Storage
        $zipPath = "archive/shipments/{$this->id}/{$zipFileName}";
        Storage::put($zipPath, file_get_contents($tempZipPath));

        // Pulisci il file temporaneo
        @unlink($tempZipPath);

        Notification::make()
            ->title("ZIP creato correttamente ({$zipFileName})")
            ->success()
            ->send();
    }

    public function createReceivers($receiverList): void
    {
        foreach ($receiverList as $recipientId => $emails) {
            foreach ($emails as $mailField => $isSelected) {
                // Salto se non è selezionato
                if (!$isSelected) continue;

                $recipient = Recipient::find($recipientId);
                if (!$recipient) continue;

                $receiver = new Receiver();
                $receiver->shipment_id = $this->id;
                $receiver->address = $recipient->{$mailField};
                $receiver->mail_type = $recipient->{'mail_type_' . substr($mailField, -1)};
                $receiver->recipient_id = $recipient->id;
                $receiver->save();

                $ref = "{$this->id}_{$receiver->id}_{$recipient->id}-" . substr($mailField, -1);
                $receiver->update([
                    'ref' => $ref,
                ]);
            }
        }

        Notification::make()
            ->title('Destinatari associati correttamente')
            ->success()
            ->send();
    }
}
