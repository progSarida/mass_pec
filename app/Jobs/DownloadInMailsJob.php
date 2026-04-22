<?php

namespace App\Jobs;

use App\Models\Sender;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Ddeboer\Imap\Server;
use ZBateson\MailMimeParser\MailMimeParser;

class DownloadInMailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    public function __construct(
        public int $userId,
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
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

            [$downloaded, $deleted, $skipped] = $this->downloadFromSender($sender, $user);

            DB::commit();

            // Notifica finale
            $this->sendNotification($user, $downloaded, $deleted, $skipped);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Errore download InMail: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            $this->notifyError($user, $e->getMessage());
        }
    }

    private function downloadFromSender(Sender $sender, User $user): array
    {
        $downloaded = 0;
        $deleted = 0;
        $skipped = 0;

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
            $count = count($messages);

            if($count == 0)
                $body = "Nessuna mail trovata da scaricare dall'account {$sender->public_name}";
            else if ($count == 1)
                $body = "Trovata una mail da scaricare dall'account {$sender->public_name}";
            else if ($count > 1)
                $body = "Trovate " . $count . " email da scaricare dall'account {$sender->public_name}";

            Log::info($body);

            \Filament\Notifications\Notification::make()
                ->title('Inizio download')
                ->body($body)
                ->success()
                ->sendToDatabase($user);

            foreach ($messages as $message) {
                try {
                    $uid = $message->getNumber();
                    $rawHeaders = $message->getRawHeaders();

                    // Skip ricevute PEC
                    if ($this->isOfficialPecReceipt($rawHeaders)) {
                        Log::info("Ignorata ricevuta PEC: UID {$uid}");
                        $receiptDeleted = $this->handleDeletion($message, $sender, $date);
                        if($receiptDeleted) {$deleted++;} else {$skipped++;}
                        continue;
                    }

                    $date = $message->getDate()?->format('Y-m-d H:i:s');
                    $message_id = $message->getId();

                    // Skip già scaricata
                    if ($this->isAlreadyDownloaded($sender, $message_id, $date)) {
                        Log::info("Ignorata mail già scaricata: Message-ID {$message_id}");
                        $emailDeleted = $this->handleDeletion($message, $sender, $date);
                        if($emailDeleted) {$deleted++;} else {$skipped++;}
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

        return [$downloaded, $deleted, $skipped];
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
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
            $content = $attachment->getDecodedContent();
            \Illuminate\Support\Facades\Storage::put("{$folderPath}/{$safeName}", $content);

            if ($extension == 'eml') {
                $parser = new MailMimeParser();
                $disk = config('filesystems.default');
                $storage = \Illuminate\Support\Facades\Storage::disk($disk);
                $content = $storage->get("{$folderPath}/{$safeName}");
                $message = $parser->parse($content, false);

                // 1. Prova a prendere il testo semplice
                $testo_semplice = $message->getTextContent();

                // 2. Se non c'è testo semplice, estrai dal HTML e converti in testo pulito
                if (empty($testo_semplice)) {
                    $html = $message->getHtmlContent();

                    if (!empty($html)) {
                        // Converte HTML → testo semplice (rimuove tag, converte entità, ecc.)
                        $testo_semplice = $this->htmlToPlainText($html);
                    }
                }

                $inMail->update(['eml_body' => substr($this->sanitizeUtf8($testo_semplice), 0, 10000)]);

                Log::info("Testo semplice: " . ($testo_semplice ?: '[NESSUN TESTO SEMPLICE]'));
                Log::info("Testo HTML: " . ($html ?? '[NESSUN HTML]'));
            }
        }

        $inMail->update(['attachment_path' => "in_mail/{$inMail->id}"]);

        return $inMail;
    }

    private function htmlToPlainText(string $html): string
    {
        // Opzione 1: Usa strip_tags + entity decode (semplice)
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Opzione 2: Migliore (consigliata) - usa una libreria leggera
        // composer require soundasleep/html2text
        // $text = \Html2Text\Html2Text::convert($html);

        // Pulizia extra
        $text = preg_replace('/\s+/', ' ', $text);   // riduce spazi multipli
        $text = trim($text);

        return $text;
    }

    private function handleDeletion($message, $sender, $date): bool
    {
        if (!$sender->delete || !$date) return false;

        try {
            if ($sender->delete_after_days) {
                $deleteDate = now()->subDays($sender->delete_after_days)->startOfDay();
                if (\Carbon\Carbon::parse($date)->lt($deleteDate)) {
                    $message->delete();
                    return true;
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Errore eliminazione messaggio InMail", [
                'error' => $e->getMessage()
            ]);
        }
        return false;
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

    private function sendNotification($user, $downloaded, $deleted, $skipped): void
    {
        $body = $downloaded === 0
            ? "Nessuna nuova email da scaricare."
            : ($downloaded === 1
                ? "È stata scaricata con successo 1 email."
                : "Sono state scaricate con successo {$downloaded} email.");
        $body .= "<br>Già scaricate {$skipped} email.";
        $body .= "<br>Eliminate {$deleted} email.";

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
