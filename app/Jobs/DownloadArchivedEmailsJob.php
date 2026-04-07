<?php

namespace App\Jobs;

use App\Enums\FlowType;
use App\Enums\MailboxType;
use App\Enums\MailType;
use App\Models\Account;
use App\Models\ArchivedReceiver;
use App\Models\User;
use DateTime;
use Ddeboer\Imap\Server;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DownloadArchivedEmailsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $userId,
        public ?int $accountId = null,
        public string $box,
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
            ? Account::where('id', $this->accountId)->where('download', true)->get()                        // account specifico scaricabile
            : $user->accounts->where('mail_type', MailType::PEC)->where('download', true);                  // account scaricabili associati all'utente

        $totalDownloaded = 0;
        $accountsProcessed = 0;
        $accountsFailed = 0;
        $errors = [];

        foreach ($accounts as $account) {
            try {
                DB::beginTransaction();

                $downloaded = $this->downloadFromAccount($account, $this->box, $user);
                $totalDownloaded += $downloaded;
                $accountsProcessed++;

                DB::commit();

                Log::info("Scaricate {$downloaded} email dall'account {$account->public_name}");

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
        $this->sendFinalNotification($user, $totalDownloaded, $accountsProcessed, $accountsFailed, $errors);
    }

    private function sendFinalNotification($user, $totalDownloaded, $accountsProcessed, $accountsFailed, $errors): void
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
            $body .= " {$accountsFailed} account hanno avuto errori.";

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

            \Filament\Notifications\Notification::make()
                ->title('Download completato')
                ->body($body)
                ->success()
                ->sendToDatabase($user);
        }
    }

    private function downloadFromAccount(Account $account, $box, User $user): int
    {
        $downloaded = 0;

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

        $limitDate = new DateTime('2025-12-31 23:59:59');

        try {
            $mailbox = $connection->getMailbox($box);
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
// $i = 1;                                                                                                             // indice per test
            foreach ($messages as $message) {
// if($i > 5)  continue;                                                                                               // blocco per test
                try {
                    $messageDate = $message->getDate();

                    // Se la data del messaggio è superiore al limite, saltalo
                    if (!$messageDate || $messageDate > $limitDate) {
                        continue;
                    }

                    $uid = $message->getNumber();
                    $rawHeaders = $message->getRawHeaders();

                    $from = $this->extractFrom($message);

                    // Skip ricevute PEC
                    if ($this->isOfficialPecReceipt($rawHeaders)) {
                        Log::info("Message ID: {$message->getId()} - È ricevuta EPC");
                        $this->handleDeletion($message, $account, $message->getDate()?->format('Y-m-d H:i:s'), $from);
                        continue;
                    }

                    $date = $messageDate?->format('Y-m-d H:i:s');
                    $message_id = $message->getId();

                    // Skip già scaricata
                    $skip = $this->isAlreadyDownloaded($account, $uid, $message_id, $date);

                    if ($skip) {
                        Log::info("Ignorata mail già scaricata/protocollata: UID {$uid}, Message-ID {$message_id}, Ricevente {$account->address}");
                        $this->handleDeletion($message, $account, $date, $from);
                        continue;
                    }

                    $flowType = $box == MailboxType::RECEIVED->getParameter() ? FlowType::RECEIVED : FlowType::ISSUED;

                    // Salva email
                    $inMail = $this->saveEmail($account, $message, $uid, $message_id, $date, $from, $flowType);
                    $downloaded++;
                    Log::info("Email salvata: UID {$uid}, ID {$inMail->id}");
// $i++;                                                                                                               // incremento indice per test

                    if($box == MailboxType::ISSUED->getParameter()){                                                // creazione destinatari mail inviata
                        $receivers = $message->getTo();
                        foreach($receivers as $receiver){
                            $address = $receiver->getAddress();
                            ArchivedReceiver::create([
                                'archived_email_id' => $inMail->id,
                                'protocol_number' => null,
                                'recipient_id' => $this->getReceiverId($address),
                                'name' => $receiver->getName(),
                                'address' => $address,
                                'message_id' => $message_id,
                                'pec_status' => null,
                            ]);
                        }
                    }

                    $this->handleDeletion($message, $account, $date, $from);

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

    private function isAlreadyDownloaded($account, $uid, $message_id, $date): bool
    {
        Log::info("Controllo {$message_id} su {$account->address}");
        if ($message_id) {
            $exists = \App\Models\ArchivedEmail::where('receiving_mail', $account->address)
                                         ->where('message_id', $message_id)->exists();
            if ($exists) return true;
        }

        if ($uid && $date) {
            $exists = \App\Models\ArchivedEmail::where('uid', $uid)
                                ->where('receive_date', $date)
                                ->exists();
            if ($exists) return true;
        }
        Log::info("{$message_id} su {$account->address} da scaricare");
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

    private function saveEmail($account, $message, $uid, $message_id, $date, $from, $flowType)
    {
        $subject = $message->getSubject() ?? '(senza oggetto)';
        $subject = preg_replace('/^POSTA CERTIFICATA:\s*/i', '', $subject);
        $subject = trim(preg_replace('/\s+/', ' ', $subject));

        $body = $message->getCompleteBodyText();

        $archivedMail = \App\Models\ArchivedEmail::create([
            'flow_type' => $flowType,
            'receiving_mail' => $account->address,
            'uid' => $uid,
            'message_id' => $message_id,
            'account_id' => $flowType == FlowType::ISSUED ? $account->id : null,
            'sender_id' => $flowType == FlowType::RECEIVED ? $this->getSenderId($from) : null,
            'from' => $flowType == FlowType::RECEIVED ? $this->sanitizeUtf8($from) : null,
            'subject' => $this->sanitizeUtf8($subject),
            'body' => substr($this->sanitizeUtf8($body), 0, 5000),
            'send_date' => $flowType == FlowType::ISSUED ? $date : null,
            'receive_date' => $flowType == FlowType::RECEIVED ? $date : null,
            'download_user_id' => $this->userId,
        ]);

        // Salva allegati
        $folderPath = "archived_email/{$archivedMail->id}";
        \Illuminate\Support\Facades\Storage::makeDirectory($folderPath);

        foreach ($message->getAttachments() as $attachment) {
            $originalName = $attachment->getFilename();
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
            $content = $attachment->getDecodedContent();
            \Illuminate\Support\Facades\Storage::put("{$folderPath}/{$safeName}", $content);
        }

        $archivedMail->update(['attachment_path' => $folderPath]);

        return $archivedMail;
    }

    private function handleDeletion($message, $account, $date, $from): void
    {
        if (!$account->delete || !$date) return;
        $messageId = $message->getId();
        try {
            Log::info("Controllo cancellazione ID {$messageId}");
            if ($account->delete_after_days && $from !== 'Sconosciuto') {
                $deleteDate = now()->subDays($account->delete_after_days)->startOfDay();
                if (\Carbon\Carbon::parse($date)->lt($deleteDate)) {
                    Log::info("Cancellato ID {$messageId}");
                    $message->delete();
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Errore eliminazione messaggio ID {$messageId}", [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function getSenderId($from): ?int
    {
        return \App\Models\Recipient::findByEmail($from)?->id;
    }

    private function getReceiverId($from): ?int
    {
        return \App\Models\Recipient::findByEmail($from)?->id;
    }

    private function sanitizeUtf8($string)
    {
        if (is_null($string)) return null;
        $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        return iconv('UTF-8', 'UTF-8//IGNORE', $string);
    }
}
