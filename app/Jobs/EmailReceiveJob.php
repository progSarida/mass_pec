<?php

namespace App\Jobs;

use App\Enums\FlowType;
use App\Enums\MailType;
use App\Models\Account;
use App\Models\Email;
use App\Models\Recipient;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Ddeboer\Imap\Server;
use Illuminate\Support\Facades\Storage;

class EmailReceiveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $userId,
        public ?int $accountId = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = User::find($this->userId);
        if (!$user) {
            Log::error("User non trovato", ['id' => $this->userId]);
            return;
        }

        // Determina gli account da processare
        $accounts = $this->accountId
            ? Account::where('id', $this->accountId)->where('download', true)->get()                            // account specifico scaricabile
            : $user->accounts->where('mail_type', MailType::MAIL)->where('download', true);                     // account scaricabili associati all'utente

        if($accounts->isEmpty()) {
             Notification::make()
                ->title('Attenzione')
                ->body('Nessun account scaricabile associato all\'utente')
                ->warning()
                ->persistent()
                ->sendToDatabase($user);
        }

        $totalDownloaded = 0;
        $totalDeleted = 0;
        $totalSkipped = 0;
        $accountsProcessed = 0;
        $accountsFailed = 0;
        $errors = [];

        foreach ($accounts as $account) {
            try {
                DB::beginTransaction();

                [$downloaded, $deleted, $skipped] = $this->downloadFromAccount($account, $user);
                $totalDownloaded += $downloaded;
                $totalDeleted += $deleted;
                $totalSkipped += $skipped;
                $accountsProcessed++;

                DB::commit();

                Log::info("Scaricate {$downloaded} email dall'account {$account->username}");
                Log::info("Saltate {$skipped} email dall'account {$account->username}");
                Log::info("Eliminate {$deleted} email dall'account {$account->username}");

            } catch (\Throwable $e) {
                DB::rollBack();
                $accountsFailed++;

                $errorMsg = "Errore account {$account->username}: " . $e->getMessage();
                $errors[] = $errorMsg;

                Log::error("Errore download email da account", [
                    'account_id' => $account->id,
                    'username' => $account->username,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                // Continua con il prossimo account invece di interrompere tutto
                continue;
            }
        }

        // Notifica finale con riepilogo
        $this->sendFinalNotification($user, $totalDownloaded, $totalDeleted, $totalSkipped, $accountsProcessed, $accountsFailed, $errors);
    }

    private function sendFinalNotification($user, $totalDownloaded, $totalDeleted, $totalSkipped, $accountsProcessed, $accountsFailed, $errors): void
    {
        if ($accountsFailed > 0 && $accountsProcessed === 0) {
            // Tutti gli account sono falliti
            \Filament\Notifications\Notification::make()
                ->title('Download email fallito')
                ->body('Nessun account è stato elaborato con successo. Dettagli: ' . implode('; ', $errors))
                ->danger()
                ->persistent()
                ->sendToDatabase($user);
        } elseif ($accountsFailed > 0) {
            // Alcuni account sono falliti
            $body = "Scaricate {$totalDownloaded} email da {$accountsProcessed} account.";
            $body .= "<br>Saltate {$totalSkipped} email.";
            $body .= "<br>Eliminate {$totalDeleted} email.";
            $body .= "<br>{$accountsFailed} account hanno avuto errori.";

            \Filament\Notifications\Notification::make()
                ->title('Download completato con errori')
                ->body($body)
                ->warning()
                ->sendToDatabase($user);
        } else {
            // Tutto ok
            $body = $totalDownloaded === 0
                ? "Nessuna nuova email da scaricare."
                : ($totalDownloaded === 1
                    ? "È stata scaricata con successo 1 email."
                    : "Sono state scaricate con successo {$totalDownloaded} email.");
            $body .= "<br>Saltate {$totalSkipped} email.";
            $body .= "<br>Eliminate {$totalDeleted} email.";

            \Filament\Notifications\Notification::make()
                ->title('Download completato')
                ->body($body)
                ->success()
                ->sendToDatabase($user);
        }
    }

    private function downloadFromAccount(Account $account, User $user): array
    {
        $downloaded = 0;
        $deleted = 0;
        $skipped = 0;

        $host = $account->in_mail_server;
        $port = (int)$account->in_mail_port;
        $username = $account->username;
        $password = decrypt($account->password);
        $encryption = strtolower($account->connection_safety_type->value);

        $flags = '/' . $account->in_mail_protocol_type->value;
        if ($encryption === 'ssl') $flags .= '/ssl';
        elseif ($encryption === 'tls') $flags .= '/tls';
        $flags .= '/novalidate-cert';

        $server = new Server($host, $port, $flags);
        $connection = $server->authenticate($username, $password);

        try {
            $mailbox = $connection->getMailbox('INBOX');
            $messages = $mailbox->getMessages();
            $count = count($messages);

            if($count == 0)
                $body = "Nessuna mail trovata da scaricare dall'account {$account->public_name}";
            else if ($count == 1)
                $body = "Trovata una mail da scaricare dall'account {$account->public_name}";
            else if ($count > 1)
                $body = "Trovate " . $count . " email da scaricare dall'account {$account->public_name}";

            Log::info($body);

            \Filament\Notifications\Notification::make()
                ->title('Inizio download')
                ->body($body)
                ->success()
                ->sendToDatabase($user);

            foreach ($messages as $message) {

                try {
                    $messageDate = $message->getDate(); // Ottieni l'oggetto DateTime

                    $uid = $message->getNumber();
                    $rawHeaders = $message->getRawHeaders();

                    $from = $this->extractFrom($message);

                    $date = $messageDate?->format('Y-m-d H:i:s');
                    $message_id = $message->getId();

                    // Skip già scaricata
                    $skip = $this->isAlreadyDownloaded($account, $uid, $message_id, $date);

                    if ($skip) {
                        Log::info("Ignorata mail già scaricata/protocollata: UID {$uid}, Message-ID {$message_id}, Ricevente {$account->address}");
                        $emailDeleted = $this->handleDeletion($message, $account, $date, $from);
                        if($emailDeleted) {$deleted++;} else {$skipped++;}
                        continue;
                    }

                    // Salva email
                    $inMail = $this->saveEmail($account, $message, $uid, $message_id, $date, $from);
                    $downloaded++;

                    Log::info("Email salvata: UID {$uid}, ID {$inMail->id}");

                    $this->handleDeletion($message, $account, $date, $from);
                    $emailDeleted = $this->handleDeletion($message, $account, $date, $from);
                    if($emailDeleted) {$deleted++;}

                } catch (\Throwable $e) {
                    // Log errore sul singolo messaggio ma continua con il prossimo
                    Log::error("Errore processamento messaggio", [
                        'account' => $account->username,
                        'uid' => $uid ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }

            $connection->expunge();
            $connection->close();

        } catch (\Throwable $e) {
            // Chiudi la connessione anche in caso di errore
            try {
                if (isset($connection)) {
                    $connection->close();
                }
            } catch (\Throwable $closeError) {
                Log::error("Errore chiusura connessione IMAP", [
                    'error' => $closeError->getMessage()
                ]);
            }

            throw $e; // Rilancia l'eccezione per il catch nel metodo handle
        }

        return [$downloaded, $deleted, $skipped];
    }

    private function isAlreadyDownloaded($account, $uid, $message_id, $date): bool
    {
        Log::info("Controllo {$message_id} su {$account->address}");
        if ($message_id) {
            $exists = Email::where('receiving_mail', $account->address)
                            ->where('message_id', $message_id)->exists();
            if ($exists) return true;
        }

        if ($uid && $date) {
            $exists = Email::where('uid', $uid)
                        ->where('receive_date', $date)->exists();
            if ($exists) return true;
        }
        Log::info("{$message_id} su {$account->address} da scaricare");
        return false;
    }

    private function extractFrom($message): string
    {
        Log::info($message->getFrom()?->getAddress());
        $from = $message->getFrom()?->getAddress() ?? 'Sconosciuto';
        return $from;
    }

    private function saveEmail($account, $message, $uid, $message_id, $date, $from)
    {
        $subject = $message->getSubject() ?? '(senza oggetto)';
        $subject = preg_replace('/^POSTA CERTIFICATA:\s*/i', '', $subject);
        $subject = trim(preg_replace('/\s+/', ' ', $subject));

        $body = $message->getCompleteBodyText();

        $inMail = Email::create([
            'flow_type' => FlowType::RECEIVED,
            'flow_index' => Email::getNextIndex(FlowType::RECEIVED),
            'receiving_mail' => $account->address,
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
        $folderPath = "email_receive/{$inMail->id}";
        Storage::makeDirectory($folderPath);

        foreach ($message->getAttachments() as $attachment) {
            $originalName = $attachment->getFilename();
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
            $content = $attachment->getDecodedContent();
            Storage::put("{$folderPath}/{$safeName}", $content);
        }

        $inMail->update(['attachment_path' => $folderPath]);

        return $inMail;
    }

    private function handleDeletion($message, $account, $date, $from): bool
    {
        if (!$account->delete || !$date) return false;
        $messageId = $message->getId();
        try {
            Log::info("Controllo cancellazione ID {$messageId}");
            if ($account->delete_after_days && $from !== 'Sconosciuto') {
                $deleteDate = now()->subDays($account->delete_after_days)->startOfDay();
                if (\Carbon\Carbon::parse($date)->lt($deleteDate)) {
                    Log::info("Cancellato ID {$messageId}");
                    $message->delete();
                    return true;
                }
            }
            return false;
        } catch (\Throwable $e) {
            Log::warning("Errore eliminazione messaggio ID {$messageId}", [
                'error' => $e->getMessage()
            ]);
        }
        return false;
    }

    private function getSenderId($from): ?int
    {
        return Recipient::findByEmail($from)?->id;
    }

    private function sanitizeUtf8($string)
    {
        if (is_null($string)) return null;
        $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        return iconv('UTF-8', 'UTF-8//IGNORE', $string);
    }
}
