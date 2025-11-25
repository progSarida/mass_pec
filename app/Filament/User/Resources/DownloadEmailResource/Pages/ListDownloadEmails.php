<?php

namespace App\Filament\User\Resources\DownloadEmailResource\Pages;

use App\Filament\User\Resources\DownloadEmailResource;
use App\Models\Account;
use App\Models\DownloadEmail;
use App\Models\Registry;
use Ddeboer\Imap\Server;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ListDownloadEmails extends ListRecords
{
    protected static string $resource = DownloadEmailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('download')
                ->label('Scarico email')
                ->icon('fluentui-mail-arrow-down-20-o')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Scarica email')
                ->modalDescription('Verranno scaricate tutte le mail degli account previsti')
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

            $accounts = Account::where('download', true)->get();

            // CREARE CICLO SU TUTTI GLI ACCOUNT CHE HANNO 'download' == true

            foreach($accounts as $account){
                // if (strtolower($account->in_mail_protocol_type->value) !== 'pop3') {
                //     throw new \Exception("Questo sistema supporta solo POP3. Configurare in_mail_protocol_type = 'pop3'.");
                // }

                // --- CONNESSIONE POP3 DA DB ---
                $host = $account->in_mail_server;
                $port = (int)$account->in_mail_port;
                $username = $account->username;
                $password = decrypt($account->password);
                $encryption = strtolower($account->connection_safety_type->value);

                // $flags = '/pop3';
                $flags = '/' . $account->in_mail_protocol_type->value;
                if ($encryption === 'ssl') $flags .= '/ssl';
                elseif ($encryption === 'tls') $flags .= '/tls';
                $flags .= '/novalidate-cert';

                $server = new Server($host, $port, $flags);
                $connection = $server->authenticate($username, $password);

                $mailbox = $connection->getMailbox('INBOX');
                $messages = $mailbox->getMessages();

                foreach ($messages as $message) {
                    $uid = $message->getNumber();

                    // --- SKIP RICEVUTE PEC ---
                    // $rawHeaders = $message->getRawHeaders();
                    // if ($this->isOfficialPecReceipt($rawHeaders)) {
                    //     Log::info("Ignorata ricevuta PEC: UID {$uid}");
                    //     continue;
                    // }

                    // --- DATA ---
                    $date = $message->getDate()?->format('Y-m-d H:i:s');

                    // SKIP GIA' SCARICATA
                    $message_id = $message->getId();
                    $skip = false;
                    if ($message_id) {
                        $skip = DownloadEmail::where('message_id', $message_id)->exists() ||
                                Registry::where('message_id', $message_id)->exists();
                    }

                    if (!$skip && $uid && $date) {
                        $skip = DownloadEmail::where('uid', $uid)
                                    ->where('receive_date', $date)
                                    ->exists() ||
                                Registry::where('uid', $uid)
                                    ->where('receive_date', $date)
                                    ->exists();
                    }

                    if ($skip) {
                        Log::info("Ignorata mail già scaricata/protocollata: UID {$uid}, Message-ID {$message_id}, DATA {$date}");
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
                    $inMail = DownloadEmail::create([
                        'uid' => $uid,
                        'message_id' => $message_id,
                        'from' => $this->sanitizeUtf8($from),
                        'subject' => $this->sanitizeUtf8($subject),
                        'body' => substr($this->sanitizeUtf8($body), 0, 5000),
                        'receive_date' => $date,
                        'download_user_id' => Auth::id(),
                    ]);

                    // --- SALVA ALLEGATI ---
                    $folderPath = storage_path("app/public/download_email/{$inMail->id}");
                    if (!is_dir($folderPath)) {
                        mkdir($folderPath, 0755, true);
                    }

                    foreach ($message->getAttachments() as $attachment) {
                        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $attachment->getFilename());
                        $filePath = $folderPath . '/' . $safeName;
                        file_put_contents($filePath, $attachment->getContent());
                    }

                    $inMail->update([
                        'attachment_path' => "download_email/{$inMail->id}",
                    ]);

                    Log::info("PEC salvata: UID {$uid}, ID {$inMail->id}, corpo: " . strlen($body) . " byte");

                    if ($account->delete && $date) {                                                            // se è prevista la cancellazione dal server
                        if ($account->delete_after_days && $date){
                            $deleteDate = now()->subDays($account->delete_after_days)->startOfDay();
                            if (\Carbon\Carbon::parse($date)->lt($deleteDate)) {                                // se ho indicato i giorni da aspettare per cancellare
                                // $message->delete();
                            }
                        }
                        else{                                                                                   // se non ho indicato i giorni da aspettare per cancellare
                            // $message->delete();
                        }
                    }
                }
            }

            $connection->expunge();
            $connection->close();
            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Errore scarico email: " . $e->getMessage() . ' - ' . $e->getLine());
            throw $e;
        }
    }

    private function sanitizeUtf8($string)
    {
        if (is_null($string)) return null;
        $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        return iconv('UTF-8', 'UTF-8//IGNORE', $string);
    }
}
