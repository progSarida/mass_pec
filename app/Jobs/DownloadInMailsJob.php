<?php

namespace App\Jobs;

use App\Models\Sender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Ddeboer\Imap\Server;

class DownloadInMailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    public function __construct(
        public int $userId,
    ) {}

    public function handle(): void
    {
        $user = \App\Models\User::find($this->userId);
        if (!$user) {
            Log::error("User non trovato", ['id' => $this->userId]);
            return;
        }

        try {
            DB::beginTransaction();

            $sender = Sender::first();
            if (!$sender) {
                throw new \Exception("Nessun mittente configurato. Inserire i dati nella pagina Mittente.");
            }

            $downloaded = $this->downloadFromSender($sender);

            DB::commit();

            // Notifica finale
            $this->sendNotification($user, $downloaded);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Errore download InMail: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            $this->notifyError($user, $e->getMessage());
        }
    }

    private function downloadFromSender(Sender $sender): int
    {
        $downloaded = 0;

        $host = $sender->in_mail_server;
        $port = (int)$sender->in_mail_port;
        $username = $sender->username;
        $password = decrypt($sender->password);
        $encryption = strtolower($sender->connection_safety_type->value);

        $flags = '/' . $sender->in_mail_protocol_type->value;
        if ($encryption === 'ssl') $flags .= '/ssl';
        elseif ($encryption === 'tls') $flags .= '/tls';
        $flags .= '/novalidate-cert';

        $server = new Server($host, $port, $flags);
        $connection = $server->authenticate($username, $password);

        try {
            $mailbox = $connection->getMailbox('INBOX');
            $messages = $mailbox->getMessages();

            foreach ($messages as $message) {
                try {
                    $uid = $message->getNumber();
                    $rawHeaders = $message->getRawHeaders();

                    // Skip ricevute PEC
                    if ($this->isOfficialPecReceipt($rawHeaders)) {
                        Log::info("Ignorata ricevuta PEC: UID {$uid}");
                        continue;
                    }

                    $date = $message->getDate()?->format('Y-m-d H:i:s');
                    $message_id = $message->getId();

                    // Skip già scaricata
                    if ($this->isAlreadyDownloaded($sender, $message_id, $date)) {
                        Log::info("Ignorata mail già scaricata: Message-ID {$message_id}");
                        $this->handleDeletion($message, $sender, $date);
                        continue;
                    }

                    // Salva email
                    $from = $this->extractFrom($message);
                    $inMail = $this->saveEmail($sender, $message, $uid, $message_id, $date, $from);

                    if ($inMail) {
                        $downloaded++;
                        Log::info("InMail salvata correttamente", [
                            'uid' => $uid,
                            'in_mail_id' => $inMail->id
                        ]);
                    } else {
                        Log::error("Il metodo saveEmail ha restituito null per l'UID: {$uid}");
                    }

                    $this->handleDeletion($message, $sender, $date);

                } catch (\Throwable $e) {
                    Log::error("Errore processamento messaggio InMail", [
                        'sender' => $sender->username,
                        'uid' => $uid ?? 'unknown',
                        'error' => $e->getMessage(),
                        'line' => $e->getLine()
                    ]);
                    continue;
                }
            }

            $connection->expunge();
            $connection->close();

        } catch (\Throwable $e) {
            try {
                if (isset($connection)) {
                    $connection->close();
                }
            } catch (\Throwable $closeError) {
                Log::error("Errore chiusura connessione IMAP", [
                    'error' => $closeError->getMessage()
                ]);
            }

            throw $e;
        }

        return $downloaded;
    }

    private function isOfficialPecReceipt($rawHeaders): bool
    {
        if (preg_match('/^X-Ricevuta:\s*(accettazione|avvenuta-consegna|non-accettazione|anomalia|errore-consegna)/mi', $rawHeaders)) {
            return true;
        }

        if (preg_match('/^X-TipoRicevuta:\s*(accettazione|consegna|mancata-accettazione|mancata-consegna|anomalia|errore-consegna)/mi', $rawHeaders)) {
            return true;
        }

        return false;
    }

    private function isAlreadyDownloaded($sender, $message_id, $date): bool
    {
        if ($message_id) {
            $exists = \App\Models\InMail::where('receiving_mail', $sender->address)
                                       ->where('message_id', $message_id)->exists() ||
                      \App\Models\Registry::where('receiving_mail', $sender->address)
                                          ->where('message_id', $message_id)->exists();
            if ($exists) return true;
        }

        return false;
    }

    private function extractFrom($message): string
    {
        $from = $message->getFrom()?->getName() ?? 'Sconosciuto';
        if (str_contains($from, 'Per conto di:')) {
            preg_match('/Per conto di:?\s*([^\s<"\']+)/i', $from, $m);
            $from = $m[1] ?? $from;
        }
        return $from;
    }

    private function saveEmail($sender, $message, $uid, $message_id, $date, $from)
    {
        $subject = $message->getSubject() ?? '(senza oggetto)';
        $subject = preg_replace('/^POSTA CERTIFICATA:\s*/i', '', $subject);
        $subject = trim(preg_replace('/\s+/', ' ', $subject));

        $body = $message->getCompleteBodyText();

        $inMail = \App\Models\InMail::create([
            'receiving_mail' => $sender->address,
            'uid' => $uid,
            'message_id' => $message_id,
            'sender_id' => $this->getSenderId($from),
            'from' => $this->sanitizeUtf8($from),
            'subject' => $this->sanitizeUtf8($subject),
            'body' => substr($this->sanitizeUtf8($body), 0, 5000),
            'receive_date' => $date,
            'download_user_id' => $this->userId,
        ]);

        // Salva allegati
        $folderPath = "in_mail/{$inMail->id}";
        \Illuminate\Support\Facades\Storage::makeDirectory($folderPath);

        foreach ($message->getAttachments() as $attachment) {
            $originalName = $attachment->getFilename();
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
            $content = $attachment->getDecodedContent();
            \Illuminate\Support\Facades\Storage::put("{$folderPath}/{$safeName}", $content);
        }

        $inMail->update(['attachment_path' => "in_mail/{$inMail->id}"]);

        return $inMail;
    }

    private function handleDeletion($message, $sender, $date): void
    {
        if (!$sender->delete || !$date) return;

        try {
            if ($sender->delete_after_days) {
                $deleteDate = now()->subDays($sender->delete_after_days)->startOfDay();
                if (\Carbon\Carbon::parse($date)->lt($deleteDate)) {
                    $message->delete();
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Errore eliminazione messaggio InMail", [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function getSenderId($from): ?int
    {
        return \App\Models\Recipient::findByEmail($from)?->id;
    }

    private function sanitizeUtf8($string)
    {
        if (is_null($string)) return null;
        $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        return iconv('UTF-8', 'UTF-8//IGNORE', $string);
    }

    private function sendNotification($user, $downloaded): void
    {
        $body = $downloaded === 0
            ? "Nessuna nuova email da scaricare."
            : ($downloaded === 1
                ? "È stata scaricata con successo 1 email."
                : "Sono state scaricate con successo {$downloaded} email.");

        \Filament\Notifications\Notification::make()
            ->title('Download completato')
            ->body($body)
            ->success()
            ->sendToDatabase($user);
    }

    private function notifyError($user, $message): void
    {
        \Filament\Notifications\Notification::make()
            ->title('Errore download email')
            ->body($message)
            ->danger()
            ->sendToDatabase($user);
    }
}
