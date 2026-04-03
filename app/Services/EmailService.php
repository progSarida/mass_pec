<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Email;
use App\Models\Recipient;
use Illuminate\Support\Facades\Storage;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class EmailService
{
    protected Account $account;

    /**
     * Imposta l'account di invio
     */
    public function setAccount(int $accountId): self
    {
        $account = Account::find($accountId);

        if (!$account) {
            throw new Exception("Account di posta non trovato (ID: {$accountId})");
        }

        $this->account = $account;
        return $this;
    }

    /**
     * Ottiene l'account corrente
     */
    public function getAccount(): Account
    {
        if (!isset($this->account)) {
            throw new Exception("Account non impostato. Chiamare setAccount() prima.");
        }

        return $this->account;
    }

    /**
     * Prepara gli allegati per l'invio da Email, compatibile con S3 e Local.
     */
    public function prepareAttachments(Email $email): array
    {
        $attachments = [];
        $disk = config('filesystems.default');

        // Pulizia del percorso per evitare slash doppi o iniziali
        $path = ltrim((string)$email->attachment_path, '/');

        if (empty($path)) {
            return $attachments;
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $storage */
        $storage = Storage::disk($disk);

        try {
            // 1. Controllo esistenza della cartella/percorso
            if (!$storage->exists($path)) {
                Log::warning("Percorso allegati Email non trovato", [
                    'email_id' => $email->id,
                    'disk' => $disk,
                    'path' => $path
                ]);
                return $attachments;
            }

            // 2. Recupero lista file (restituisce path relativi al disco)
            $files = $storage->files($path);

            foreach ($files as $file) {
                // Verifichiamo l'esistenza del singolo file per ridondanza
                if ($storage->exists($file)) {
                    $attachments[] = [
                        'disk' => $disk,
                        'path' => $file,
                        'name' => basename($file),
                        // Fallback se il Mime Type non è rilevabile (fondamentale su S3)
                        'mime' => $storage->mimeType($file) ?: 'application/octet-stream',
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error("Errore durante la preparazione allegati Email #{$email->id}", [
                'error' => $e->getMessage()
            ]);
        }

        return $attachments;
    }

    /**
     * Ottiene il nome del destinatario dalla rubrica
     */
    private static function getRecipientName($email): ?string
    {
        $recipient = Recipient::findByEmail($email);
        return $recipient?->description;
    }

    /**
     * Estrae i destinatari da Email (che sono salvati come array)
     */
    public function extractRecipients(Email $email): Collection
    {
        $recipients = $email->recipients ?? [];

        if (empty($recipients)) {
            return collect([]);
        }

        // Trasformiamo l'array di email in una Collection con oggetti standard
        return collect($recipients)->map(function ($recipientEmail) {
            return (object) [
                'email' => $recipientEmail,
                'name' => self::getRecipientName($recipientEmail),
            ];
        });
    }
}
