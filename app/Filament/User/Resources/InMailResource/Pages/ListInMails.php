<?php

namespace App\Filament\User\Resources\InMailResource\Pages;

use App\Filament\User\Resources\InMailResource;
use App\Models\InMail;
use App\Models\Recipient;
use App\Models\Registry;
use App\Models\Sender;
use Ddeboer\Imap\Server;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ListInMails extends ListRecords
{
    protected static string $resource = InMailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download')
                ->label('Scarico email')
                ->icon('fluentui-mail-arrow-down-20')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Scarica ricevute PEC')
                ->modalDescription('Verranno scaricate tutte le mail che non siano ricevute di accettazione, consegna e anomalie.')
                ->modalSubmitActionLabel('Scarica')
                ->action(function () {
                    try {
                        $downloaded = $this->downloadEmails();
                        if($downloaded > 0){
                            $body = $downloaded = 1 ? "È stata scaricata con successo una mail." : "Sono state scaricate con successo {$downloaded} mail.";
                            Notification::make()
                                ->title('Procedura completata')
                                ->body($body)
                                ->success()
                                ->send();
                            }
                        else
                            Notification::make()
                                ->title('Procedura completata')
                                ->body('Nessuna nuova mail da scaricare')
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

    public function downloadEmails(): int
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        try {
            DB::beginTransaction();

            $downloaded = 0;

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
// $index = 0;
            foreach ($messages as $message) {
                $uid = $message->getNumber();

                // if (InMail::where('uid', $uid)->exists()) {
                //     Log::info("Mail già presente: UID {$uid}");
                //     continue;
                // }

                // --- SKIP RICEVUTE PEC ---
                $rawHeaders = $message->getRawHeaders();
                if ($this->isOfficialPecReceipt($rawHeaders)) {
                    Log::info("Ignorata ricevuta PEC: UID {$uid}");
                    // if ($sender->delete && $date) {                                                        // se è prevista la cancellazione dal server
                    //     if ($sender->delete_after_days && $date && $from != 'Sconosciuto') {
                    //         $deleteDate = now()->subDays($sender->delete_after_days)->startOfDay();
                    //         if (\Carbon\Carbon::parse($date)->lt($deleteDate)) {
                    //             $message->delete();
                    //         }
                    //     }
                    // }
                    continue;
                }

                // --- DATA ---
                $date = $message->getDate()?->format('Y-m-d H:i:s');

                // --- MITTENTE REALE ---
                $from = $message->getFrom()?->getName() ?? 'Sconosciuto';
                if (str_contains($from, 'Per conto di:')) {
                    preg_match('/Per conto di:?\s*([^\s<"\']+)/i', $from, $m);
                    $from = $m[1] ?? $from;
                }

                // SKIP GIA' SCARICATA
                $message_id = $message->getId();
                if (
                    $message_id && (InMail::where('message_id', $message_id)->exists() || Registry::where('registry_origin_type', 'in_mail')->where('message_id', $message_id)->exists())
                    // InMail::where('uid', $uid)->where('receive_date', $date)->exists()
                ) {
                    Log::info("Ignorata mail già scaricata: Message-ID {$message_id}, DATA {$date}");
                    if ($sender->delete && $date) {                                                        // se è prevista la cancellazione dal server
                        if ($sender->delete_after_days && $date && $from != 'Sconosciuto') {
                            $deleteDate = now()->subDays($sender->delete_after_days)->startOfDay();
                            if (\Carbon\Carbon::parse($date)->lt($deleteDate)) {
                                $message->delete();
                            }
                        }
                    }
                    continue;
                }
// if($index > 0) continue; $index++;
                // --- MITTENTE REALE ---
                // $from = $message->getFrom()?->getName() ?? 'Sconosciuto';
                // if (str_contains($from, 'Per conto di:')) {
                //     preg_match('/Per conto di:?\s*([^\s<"\']+)/i', $from, $m);
                //     $from = $m[1] ?? $from;
                // }

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
                    'sender_id' => $this->getSenderId($from),
                    'from' => $this->sanitizeUtf8($from),
                    'subject' => $this->sanitizeUtf8($subject),
                    'body' => substr($this->sanitizeUtf8($body), 0, 5000),
                    'receive_date' => $date,
                    'download_user_id' => Auth::id(),
                ]);

                $downloaded++;

                // --- SALVA ALLEGATI ---
                // $folderPath = storage_path("app/public/in_mail/{$inMail->id}");
                $folderPath = "in_mail/{$inMail->id}";

                // if (!is_dir($folderPath)) { mkdir($folderPath, 0755, true); }
                Storage::makeDirectory($folderPath);

                foreach ($message->getAttachments() as $attachment) {
                    // $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $attachment->getFilename());
                    // $filePath = $folderPath . '/' . $safeName;
                    $originalName = $attachment->getFilename();
                    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
                    // file_put_contents($filePath, $attachment->getContent());
                    $content = $attachment->getDecodedContent();
                        Storage::put( "{$folderPath}/{$safeName}",$content );
                }

                $inMail->update([
                    'attachment_path' => "in_mail/{$inMail->id}",
                ]);

                Log::info("PEC salvata: UID {$uid}, ID {$inMail->id}, corpo: " . strlen($body) . " byte");

                if ($sender->delete && $date) {                                                        // se è prevista la cancellazione dal server
                    if ($sender->delete_after_days && $date) {
                        $deleteDate = now()->subDays($sender->delete_after_days)->startOfDay();
                        if (\Carbon\Carbon::parse($date)->lt($deleteDate)) {
                            $message->delete();
                        }
                    }
                }
            }

            $connection->expunge();
            $connection->close();
            DB::commit();

            return $downloaded;

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
            '/^X-(?:Ricevuta|TipoRicevuta):\s*(?:accettazione|(?:avvenuta-)?consegna?|(?:mancata-)?accettazione?|(?:non-)?accettazione|(?:mancata-)?consegna?|anomalia|(?:errore-)?consegna)/mi',
            $rawHeaders
        );
    }

    // private function getSenderIdOld($from)
    // {
    //     $recipient = Recipient::where('mail_1', $from)
    //                     ->orWhere('mail_2', $from)
    //                     ->orWhere('mail_3', $from)
    //                     ->orWhere('mail_4', $from)
    //                     ->orWhere('mail_5', $from)
    //                     ->first();
    //     return $recipient?->id;
    // }

    private static function getSenderId($from): int|null
    {
        return Recipient::findByEmail($from)?->id;
    }
}
