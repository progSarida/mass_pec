<?php

namespace App\Filament\User\Resources\InMailResource\Pages;

use App\Filament\User\Resources\InMailResource;
use App\Models\InMail;
use App\Models\Sender;
use Ddeboer\Imap\Server;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Webklex\PHPIMAP\Message as ImapMessage;

class ListInMails extends ListRecords
{
    protected static string $resource = InMailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download')
                ->label('Scarico casella')
                ->icon('hugeicons-mail-receive-01')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Scarica ricevute PEC')
                ->modalDescription('Verranno scaricate tutte le mail che non siano ricevute di accettazione, consegna e anomalie.')
                ->modalSubmitActionLabel('Scarica')
                ->action(function () {
                    try {
                        $this->downloadEmails();
                        Notification::make()
                            ->title('Mail scaricate')
                            ->body('Tutte le mail sono state scaricate con successo.')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Errore scarico')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
    }

    public function downloadEmails()
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        try {
            DB::beginTransaction();

            $sender = Sender::first();
            if (!$sender) {
                throw new \Exception("Nessun mittente configurato. Inserire i dati nella pagina Mittente.");
            }

            // if (strtolower($sender->in_mail_protocol_type->value) !== 'pop3') {
            //     throw new \Exception("Questo sistema supporta solo POP3. Configurare in_mail_protocol_type = 'pop3'.");
            // }

            // --- CONNESSIONE POP3 DA DB ---
            $host = $sender->in_mail_server;
            $port = (int)$sender->in_mail_port;
            $username = $sender->username;
            $password = decrypt($sender->password);
            $encryption = strtolower($sender->connection_safety_type->value);

            // $flags = '/pop3';
            $flags = '/' . $sender->in_mail_protocol_type->value;
            if ($encryption === 'ssl') $flags .= '/ssl';
            elseif ($encryption === 'tls') $flags .= '/tls';
            $flags .= '/novalidate-cert';

            $server = new Server($host, $port, $flags);
            $connection = $server->authenticate($username, $password);

            $mailbox = $connection->getMailbox('INBOX');
            $messages = $mailbox->getMessages();

            foreach ($messages as $message) {
                $uid = $message->getNumber();

                if (InMail::where('uid', $uid)->exists()) {
                    Log::info("Mail già presente: UID {$uid}");
                    continue;
                }

                // --- SKIP RICEVUTE PEC ---
                $rawHeaders = $message->getRawHeaders();
                if ($this->isOfficialPecReceipt($rawHeaders)) {
                    Log::info("Ignorata ricevuta PEC: UID {$uid}");
                    continue;
                }

                // --- DATA ---
                $date = $message->getDate()?->format('Y-m-d H:i:s');

                // SKIP GIA' SCARICATA
                $message_id = $message->getId();
                if (
                    ($message_id && InMail::where('message_id', $message_id)->exists()) ||
                    InMail::where('uid', $uid)->where('receive_date', $date)->exists()
                ) {
                    Log::info("Ignorata mail già scaricata: UID {$uid}, Message-ID {$message_id}, DATA {$date}");
                    continue;
                }

                // --- MITTENTE REALE ---
                $from = $message->getFrom()?->getName() ?? 'Sconosciuto';
                if (str_contains($from, 'Per conto di:')) {
                    preg_match('/Per conto di:?\s*([^\s<"\']+)/i', $from, $m);
                    $from = $m[1] ?? $from;
                }

                // --- OGGETTO ---
                $subject = $message->getSubject() ?? '(senza oggetto)';
                $subject = preg_replace('/^POSTA CERTIFICATA:\s*/i', '', $subject);
                $subject = trim(preg_replace('/\s+/', ' ', $subject));

                // --- CORPO PULITO ---
                // $body = $this->getCleanBodyFromMessage($message);
                $body = $message->getCompleteBodyText();

                // --- CREA RECORD ---
                $inMail = InMail::create([
                    'uid' => $uid,
                    'message_id' => $message_id,
                    'from' => $this->sanitizeUtf8($from),
                    'subject' => $this->sanitizeUtf8($subject),
                    'body' => substr($this->sanitizeUtf8($body), 0, 5000),
                    'receive_date' => $date,
                    'download_user_id' => Auth::id(),
                ]);

                // --- SALVA ALLEGATI ---
                $folderPath = storage_path("app/public/in_mail/{$inMail->id}");
                if (!is_dir($folderPath)) {
                    mkdir($folderPath, 0755, true);
                }

                foreach ($message->getAttachments() as $attachment) {
                    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $attachment->getFilename());
                    $filePath = $folderPath . '/' . $safeName;
                    file_put_contents($filePath, $attachment->getContent());
                }

                $inMail->update([
                    'attachment_path' => "in_mail/{$inMail->id}",
                ]);

                Log::info("PEC salvata: UID {$uid}, ID {$inMail->id}, corpo: " . strlen($body) . " byte");

                if ($sender->delete_after_days && $date) {
                    $deleteDate = now()->subDays($sender->delete_after_days)->startOfDay();
                    if (\Carbon\Carbon::parse($date)->lt($deleteDate)) {
                        $message->delete();
                    }
                }
            }

            $connection->expunge();
            $connection->close();
            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Errore scarico PEC: " . $e->getMessage());
            throw $e;
        }
    }

    private function sanitizeUtf8($string)
    {
        if (is_null($string)) return null;
        $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        return iconv('UTF-8', 'UTF-8//IGNORE', $string);
    }

    private function isOfficialPecReceipt($rawHeaders)
    {
        return preg_match(
            '/^X-(?:Ricevuta|TipoRicevuta):\s*(?:accettazione|(?:avvenuta-)?consegna?|(?:mancata-)?accettazione?|(?:non-)?accettazione|(?:mancata-)?consegna?|anomalia)/mi',
            $rawHeaders
        );
    }
}
