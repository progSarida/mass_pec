<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Registry;
use App\Models\Recipient;
use Illuminate\Support\Facades\Storage;
use Exception;

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
     * Prepara gli allegati per l'invio da Registry
     */
    public function prepareAttachments(Registry $registry): array
    {
        $attachments = [];
        $attachmentRelativePath = ltrim($registry->attachment_path, '/');

        if (!$attachmentRelativePath) {
            return $attachments;
        }

        $files = Storage::files($attachmentRelativePath);

        foreach ($files as $file) {
            if (!Storage::exists($file)) {
                continue;
            }

            $attachments[] = [
                'path' => Storage::path($file),
                'name' => basename($file),
                'mime' => Storage::mimeType($file),
            ];
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
