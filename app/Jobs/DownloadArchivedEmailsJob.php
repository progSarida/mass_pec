<?php

namespace App\Jobs;

use App\Enums\FlowType;
use App\Enums\MailboxType;
use App\Enums\MailType;
use App\Models\Account;
use App\Models\ArchivedEmail;
use App\Models\ArchivedReceiver;
use App\Models\User;
use App\Models\Recipient;
use DateTime;
use Ddeboer\Imap\SearchExpression;
use Ddeboer\Imap\Search\Date\Since;
use Ddeboer\Imap\Search\Date\Before;
use Ddeboer\Imap\Server;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class DownloadArchivedEmailsJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param int $userId ID dell'utente che avvia il download
     * @param int|null $accountId ID account specifico (se null, scarica tutti quelli abilitati)
     * @param MailboxType $mailboxType Enum (RECEIVED o ISSUED)
     * @param string|null $semester Formato "1-2024" o "2-2024"
     */
    public function __construct(
        public int $userId,
        public ?int $accountId = null,
        public MailboxType $mailboxType,
        public ?string $semester = null,
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (!$user) {
            Log::error("User non trovato", ['id' => $this->userId]);
            return;
        }

        $accounts = $this->accountId
            ? Account::where('id', $this->accountId)->where('download', true)->get()
            : $user->accounts->where('mail_type', MailType::PEC)->where('download', true);

        $totalDownloaded = 0;
        $accountsProcessed = 0;
        $accountsFailed = 0;
        $errors = [];

        foreach ($accounts as $account) {
            try {
                // Avviamo una transazione per ogni account
                DB::beginTransaction();

                $downloaded = $this->downloadFromAccount($account, $this->mailboxType, $user);

                $totalDownloaded += $downloaded;
                $accountsProcessed++;

                DB::commit();
                Log::info("Completato account {$account->public_name}: {$downloaded} email.");

            } catch (\Throwable $e) {
                DB::rollBack();
                $accountsFailed++;
                $errors[] = "Errore {$account->username}: " . $e->getMessage();

                Log::error("Errore download account", [
                    'account_id' => $account->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                continue;
            }
        }

        $this->sendFinalNotification($user, $totalDownloaded, $accountsProcessed, $accountsFailed, $errors);
    }

    private function downloadFromAccount(Account $account, MailboxType $mailboxType, User $user): int
    {
        $downloaded = 0;
        $processedCount = 0;
        $maxReconnections = 3;
        $reconnectionCount = 0;
        $batchSize = 25; // Commit ogni 25 messaggi

        // Decrittazione e config
        $host = $account->in_mail_server;
        $port = (int)$account->in_mail_port;
        $username = $account->username;
        $password = decrypt($account->password);
        $encryption = strtolower($account->connection_safety_type->value);

        $flags = '/' . $account->in_mail_protocol_type->value;
        if ($encryption === 'ssl') $flags .= '/ssl';
        elseif ($encryption === 'tls') $flags .= '/tls';
        $flags .= '/novalidate-cert';

        $box = $mailboxType->getParameter();

        // Closure per gestire la connessione (riutilizzabile in caso di reconnection)
        $connect = function() use ($host, $port, $flags, $username, $password, $box) {
            $server = new Server($host, $port, $flags);
            $connection = $server->authenticate($username, $password);
            $mailbox = $connection->getMailbox($box);
            return [$connection, $mailbox];
        };

        [$connection, $mailbox] = $connect();

        // Configurazione filtro periodo: senza semestre si scarica l'intera casella
        $search = null;

        if (filled($this->semester)) {
            [$startYear, $endYear] = $this->getDates($this->semester);

            $search = new SearchExpression();
            $search->addCondition(new Since($startYear));
            $search->addCondition(new Before($endYear));
        } else {
            Log::info("Account {$account->public_name}: nessun periodo selezionato, scarico l'intera casella");
        }

        $messages = $mailbox->getMessages($search);
        $totalInPeriod = count($messages);

        Log::info("Account {$account->public_name}: trovate {$totalInPeriod} email");

        if($totalInPeriod == 0){
            $flow = $mailboxType == MailboxType::ISSUED ? 'inviata' : 'ricevuta';
            $body = "Nessuna mail {$flow} trovata da scaricare dall'account {$account->public_name}";
        }
        else if ($totalInPeriod == 1){
            $flow = $mailboxType == MailboxType::ISSUED ? 'inviata' : 'ricevuta';
            $body = "Trovata una mail {$flow} da scaricare dall'account {$account->public_name}";
        }
        else if ($totalInPeriod > 1){
            $flow = $mailboxType == MailboxType::ISSUED ? 'inviate' : 'ricevute';
            $body = "Trovate " . $totalInPeriod . " email {$flow} da scaricare dall'account {$account->public_name}";
        }

        \Filament\Notifications\Notification::make()
            ->title('Inizio download')
            ->body($body)
            ->success()
            ->sendToDatabase($user);

        foreach ($messages as $message) {
            try {
                // Verifica connessione ogni 10 messaggi per prevenire timeout IMAP
                if ($processedCount > 0 && $processedCount % 10 === 0) {
                    try {
                        $mailbox->count();
                    } catch (\Throwable $e) {
                        Log::warning("Verifica connessione fallita per {$account->username}, riconnessione...");
                        if ($reconnectionCount < $maxReconnections) {
                            $reconnectionCount++;
                            sleep(2);

                            try {
                                $connection->close();
                            } catch (\Throwable $ignored) {}

                            [$connection, $mailbox] = $connect();
                            Log::info("Riconnessione {$reconnectionCount} riuscita");
                        } else {
                            throw new \Exception("Superate {$maxReconnections} riconnessioni");
                        }
                    }
                }

                $messageDate = $message->getDate();
                $messageId = $message->getId();
                $uid = $message->getNumber();

                if (!$messageDate || !$messageId) {
                    $processedCount++;
                    continue;
                }

                $rawHeaders = $message->getRawHeaders();
                $from = $this->extractFrom($message);
                $dateString = $messageDate->format('Y-m-d H:i:s');

                // 1. Skip Ricevute PEC (e cancellazione se previsto)
                if ($this->isOfficialPecReceipt($rawHeaders)) {
                    Log::info("Message ID: {$messageId} - È ricevuta PEC");
                    $this->handleDeletion($message, $account, $dateString, $from);
                    $processedCount++;
                    continue;
                }

                // 2. Skip se già presente nel database
                if ($this->isAlreadyDownloaded($account, $uid, $messageId, $dateString)) {
                    Log::info("Ignorata mail già scaricata: UID {$uid}, Message-ID {$messageId}");
                    $this->handleDeletion($message, $account, $dateString, $from);
                    $processedCount++;
                    continue;
                }

                // 3. Salvataggio Email
                $flowType = FlowType::tryFrom($mailboxType->value);
                $archivedMail = $this->saveEmail($account, $message, $uid, $messageId, $dateString, $from, $flowType);
                Log::info("Email salvata: UID {$uid}, ID {$archivedMail->id} ({$downloaded}/{$totalInPeriod})");

                // 4. Gestione Destinatari (solo se inviata)
                if ($mailboxType == MailboxType::ISSUED) {
                    Log::info('Inizio creazione destinatari');
                    foreach ($message->getTo() as $receiver) {
                        $emailAddress = $receiver->getAddress();
                        if (!$emailAddress) continue;

                        ArchivedReceiver::create([
                            'archived_email_id' => $archivedMail->id,
                            'recipient_id'      => Recipient::findByEmail($emailAddress)?->id,
                            'name'              => $this->sanitizeUtf8($receiver->getName()),
                            'address'           => $emailAddress,
                            'message_id'        => $messageId,
                        ]);
                    }
                    Log::info("Creazione destinatari completato");
                }

                $downloaded++;
                $processedCount++;

                if ($processedCount % 50 === 0) {
                    sleep(1);
                }

                // 5. Cancellazione fisica dal server (se abilitata)
                $this->handleDeletion($message, $account, $dateString, $from);

                // Commit parziale per non saturare la memoria e salvare i progressi
                if ($downloaded % $batchSize === 0) {
                    DB::commit();
                    DB::beginTransaction();
                }

                // Ogni 100 messaggi, invia notifica di progresso
                if ($downloaded > 0 && $downloaded % 100 === 0) {
                    \Filament\Notifications\Notification::make()
                        ->title('Download in corso')
                        ->body("Scaricate {$downloaded} di ~{$totalInPeriod} email per {$account->public_name}")
                        ->info()
                        ->sendToDatabase($user);
                }

            } catch (\Throwable $msgEx) {
                // Gestione errori di connessione
                $errorMsg = $msgEx->getMessage();
                $isConnectionError = str_contains(strtolower($errorMsg), 'connection') ||
                                str_contains(strtolower($errorMsg), 'closed');

                if ($isConnectionError && $reconnectionCount < $maxReconnections) {
                    Log::warning("Errore connessione durante processamento, riconnessione...");

                    try {
                        $connection->close();
                    } catch (\Throwable $ignored) {}

                    $reconnectionCount++;
                    sleep(3); // Pausa più lunga dopo un errore

                    try {
                        [$connection, $mailbox] = $connect();
                        Log::info("Riconnessione {$reconnectionCount} dopo errore riuscita");
                        $processedCount++;
                        continue;
                    } catch (\Throwable $reconnectError) {
                        Log::error("Riconnessione fallita", ['error' => $reconnectError->getMessage()]);
                    }
                }

                Log::error("Errore processamento messaggio UID {$uid} su {$account->username}: " . $errorMsg);
                $processedCount++;
                continue;
            }
        }

        $connection->expunge();
        $connection->close();

        return $downloaded;
    }

    private function saveEmail($account, $message, $uid, $messageId, $date, $from, $flowType): ArchivedEmail
    {
        $subject = $message->getSubject() ?? '(senza oggetto)';
        $subject = preg_replace('/^POSTA CERTIFICATA:\s*/i', '', $subject);
        $subject = trim(preg_replace('/\s+/', ' ', $subject));

        $body = $message->getCompleteBodyText();

        $archivedMail = ArchivedEmail::create([
            'flow_type'        => $flowType,
            'receiving_mail'   => $account->address,
            'uid'              => $uid,
            'message_id'       => $messageId,
            'account_id'       => $flowType == FlowType::ISSUED ? $account->id : null,
            'sender_id'        => $flowType == FlowType::RECEIVED ? Recipient::findByEmail($from)?->id : null,
            'from'             => $flowType == FlowType::RECEIVED ? $this->sanitizeUtf8($from) : null,
            'subject'          => $this->sanitizeUtf8($subject),
            'body'             => substr($this->sanitizeUtf8($body), 0, 10000), // Esteso a 10k per sicurezza
            'send_date'        => $flowType == FlowType::ISSUED ? $date : null,
            'receive_date'     => $flowType == FlowType::RECEIVED ? $date : null,
            'download_user_id' => $this->userId,
        ]);

        $folderPath = "archived_email/{$archivedMail->id}";
        Storage::makeDirectory($folderPath);

        foreach ($message->getAttachments() as $attachment) {
            $filename = $this->sanitizeUtf8($attachment->getFilename()) ?: 'unnamed_attachment';
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
            Storage::put("{$folderPath}/{$safeName}", $attachment->getDecodedContent());
        }

        $archivedMail->update(['attachment_path' => $folderPath]);

        return $archivedMail;
    }

    private function isOfficialPecReceipt(string $rawHeaders): bool
    {
        return (bool) preg_match('/^(X-Ricevuta|X-TipoRicevuta):\s*(accettazione|avvenuta-consegna|consegna|non-accettazione|mancata-accettazione|mancata-consegna|anomalia|errore-consegna)/mi', $rawHeaders);
    }

    private function isAlreadyDownloaded($account, $uid, $messageId, $date): bool
    {
        if ($messageId) {
            $exists = ArchivedEmail::where('receiving_mail', $account->address)
                                   ->where('message_id', $messageId)
                                   ->exists();
            if ($exists) return true;
        }

        return ArchivedEmail::where('uid', $uid)
                            ->where('receiving_mail', $account->address)
                            ->where(fn($q) => $q->where('receive_date', $date)->orWhere('send_date', $date))
                            ->exists();
    }

    private function handleDeletion($message, $account, $date, $from): void
    {
        if (!$account->delete || !$date || $from === 'Sconosciuto') return;

        try {
            if ($account->delete_after_days) {
                $deleteDate = now()->subDays($account->delete_after_days)->startOfDay();

                // Cancella se la mail è più vecchia della soglia
                if (Carbon::parse($date)->lt($deleteDate)) {
                    $message->delete();
                    Log::info("Cancellato messaggio {$message->getId()} del {$date}");
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Errore cancellazione ID {$message->getId()}: " . $e->getMessage());
        }
    }

    private function extractFrom($message): string
    {
        $from = $message->getFrom()?->getAddress() ?? 'Sconosciuto';
        // Logica specifica per "Per conto di" nelle PEC
        $name = $message->getFrom()?->getName();
        if ($name && str_contains($name, 'Per conto di:')) {
            preg_match('/Per conto di:?\s*([^\s<"\']+)/i', $name, $m);
            return $m[1] ?? $from;
        }
        return $from;
    }

    private function sanitizeUtf8(?string $string): ?string
    {
        if (!$string) return null;
        $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        return iconv('UTF-8', 'UTF-8//IGNORE', $string);
    }

    private function sendFinalNotification($user, $total, $processed, $failed, $errors): void
    {
        $notif = \Filament\Notifications\Notification::make()
            ->title($failed > 0 ? 'Download completato con anomalie' : 'Download completato');

        $body = "Processati {$processed} account, scaricate {$total} email.<br>";
        if ($failed > 0) $body .= " Fallimenti: {$failed}. Dettagli: " . implode(', ', $errors);

        $notif->body($body);
        if($failed > 0)
            $notif->warning();
        else
            $notif->success();

        $notif->sendToDatabase($user);
    }

    private function getDates($semester): array
    {
        $semArray = explode('-', (string) $semester);

        // Formato atteso: "S-1-2024" (semestre) oppure "T-3-2022" (trimestre)
        if (count($semArray) !== 3) {
            throw new \InvalidArgumentException("Periodo non riconosciuto: '{$semester}', atteso il formato S-1-2024 o T-3-2022");
        }

        $year = (int) $semArray[2];

        if($semArray[0] == 'S'){
            $period = 'semestre';
            if($semArray[1] == '1'){
                $startYear = new DateTime("{$year}-01-01 00:00:00");
                $endYear = new DateTime(($year) . "-07-01 00:00:00");
            }
            else{
                $startYear = new DateTime("{$year}-07-01 00:00:00");
                $endYear = new DateTime(($year + 1) . "-01-01 00:00:00");
            }
        } else {
            $period = 'trimestre';
            if($semArray[1] == '1'){
                $startYear = new DateTime("{$year}-01-01 00:00:00");
                $endYear = new DateTime(($year) . "-04-01 00:00:00");
            }
            else if($semArray[1] == '2'){
                $startYear = new DateTime("{$year}-04-01 00:00:00");
                $endYear = new DateTime(($year) . "-07-01 00:00:00");
            }
            else if($semArray[1] == '3'){
                $startYear = new DateTime("{$year}-07-01 00:00:00");
                $endYear = new DateTime(($year) . "-10-01 00:00:00");
            }
            else{
                $startYear = new DateTime("{$year}-10-01 00:00:00");
                $endYear = new DateTime(($year + 1) . "-01-01 00:00:00");
            }
        }
        return [$startYear, $endYear];
    }
}
