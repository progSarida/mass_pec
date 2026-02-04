<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Registry;
use App\Models\Recipient;
use Illuminate\Support\Facades\Storage;
use Exception;
use Illuminate\Support\Facades\Log;

class RegistryEmailService
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
     * Prepara gli allegati per l'invio da Registry, compatibile con S3 e Local.
     */
    public function prepareAttachments(Registry $registry): array
    {
        $attachments = [];
        $disk = config('filesystems.default'); // Recupera il disco (es. 's3' o 'public')

        // Pulizia del percorso per evitare slash doppi o iniziali
        $path = ltrim((string)$registry->attachment_path, '/');

        if (empty($path)) {
            return $attachments;
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $storage */
        $storage = Storage::disk($disk);

        try {
            // 1. Controllo esistenza della cartella/percorso
            if (!$storage->exists($path)) {
                Log::warning("Percorso allegati Registry non trovato", [
                    'registry_id' => $registry->id,
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
            Log::error("Errore durante la preparazione allegati Registry #{$registry->id}", [
                'error' => $e->getMessage()
            ]);
        }

        return $attachments;
    }

    /**
     * Ottiene il nome del destinatario dalla rubrica
     */
    public function getRecipientName(string $email): string
    {
        $recipient = Recipient::where(function ($query) use ($email) {
            $query->where('mail_1', $email)
                ->orWhere('mail_2', $email)
                ->orWhere('mail_3', $email)
                ->orWhere('mail_4', $email)
                ->orWhere('mail_5', $email);
        })
        ->select('description')
        ->first();

        return $recipient?->description ?? $email;
    }

    /**
     * Estrae i destinatari da Registry
     */
    public function extractRecipients(Registry $registry): array
    {
        // Il campo recipients è già un JSON che viene automaticamente
        // convertito in array grazie al cast nel model
        return $registry->recipients ?? [];
    }
}
