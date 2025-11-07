<?php

namespace App\Filament\User\Resources\InMailResource\Pages;

use App\Filament\User\Resources\InMailResource;
use App\Models\InMail;
use App\Models\Sender;
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
        ini_set('memory_limit', '512M');   // Necessario per mail grandi
        set_time_limit(300);               // 5 minuti per mail

        try {
            DB::beginTransaction();
            Log::info("------------------------------------------------------------------------");
            Log::info("Inizio scarico email");

            $sender = Sender::first();
            if (!$sender) {
                throw new \Exception("Nessun mittente configurato. Inserire i dati nella pagina Mittente.");
            }

            $imap = $this->connectToMail($sender);
            if (!$imap) {
                $errors = imap_errors() ?: ['Errore sconosciuto'];
                throw new \Exception("Connessione IMAP fallita: " . implode(', ', $errors));
            }

            Log::info("IMAP collegata con successo.");

            foreach (imap_search($imap, 'ALL', SE_UID) ?: [] as $uid) {
                Log::info("Scarico mail: {$uid}");

                if (InMail::where('uid', $uid)->exists()) {
                    Log::info("Ignorata mail esistente: {$uid}");
                    continue;
                }

                $overview   = imap_fetch_overview($imap, $uid, FT_UID)[0];
                $rawHeaders = imap_fetchheader($imap, $uid, FT_UID);

                if ($this->isOfficialPecReceipt($rawHeaders)) {
                    Log::info("Ignorata ricevuta PEC: {$uid}");
                    continue;
                }

                $rawSubject = $overview->subject ?? '(senza oggetto)';
                $subject = $this->cleanSubject(imap_utf8($rawSubject));

                $rawFrom = $overview->from ?? '';
                $from = $this->extractRealSender($rawFrom);

                if (!$from) {
                    Log::warning("Mittente non riconosciuto: {$rawFrom}");
                    $from = 'Sconosciuto';
                }
                $date    = $overview->date ? date('Y-m-d H:i:s', strtotime($overview->date)) : null;
                $structure = imap_fetchstructure($imap, $uid, FT_UID);

                Log::info('Subject length: ' . strlen($subject));
                Log::info('Is valid UTF-8 subject: ' . (mb_check_encoding($subject, 'UTF-8') ? 'yes' : 'NO'));

                // Crea record senza corpo
                $inMail = InMail::create([
                    'uid'              => $uid,
                    'from'             => $this->sanitizeUtf8($from),
                    'subject'          => $this->sanitizeUtf8($subject),
                    'receive_date'     => $date,
                    'download_user_id' => Auth::id(),
                ]);

                // Salva allegati
                $attachmentsPath = $this->saveAttachments($imap, $uid, $structure, $inMail);
                if ($attachmentsPath) {
                    $inMail->update(['attachments_path' => $attachmentsPath]);
                }
                Log::info("Allegati salvati");

                // Salva corpo completo su disco (streaming)
                $bodyPath = "in_mail/{$inMail->id}/body.txt";
                $fullPath = storage_path("app/public/{$bodyPath}");

                $dir = dirname($fullPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                $fh = fopen($fullPath, 'wb');
                $this->streamBody($imap, $uid, $structure, $fh);
                fclose($fh);

                // Anteprima per DB (max 5000 caratteri)
                $previewRaw = file_get_contents($fullPath, false, null, 0, 5000);
                $preview = $this->sanitizeUtf8($previewRaw);

                $inMail->update([
                    'body_preview' => $preview,
                    'body_path'    => $bodyPath,
                ]);

                Log::info("Mail salvata (anteprima: " . strlen($preview) . " byte)");

                // Cancellazione opzionale dalla casella
                if ($date && !is_null($sender->delete_after_days)) {
                    if (strtotime($date) < now()->subDays($sender->delete_after_days)->timestamp) {
                        // imap_delete($imap, $uid, FT_UID);
                    }
                }
            }

            imap_expunge($imap);
            imap_close($imap);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function connectToMail($sender)
    {
        $protocol = strtolower($sender->in_mail_protocol_type->value);
        $safety   = strtolower($sender->connection_safety_type->value);
        $mailbox  = "{" . $sender->in_mail_server . ":" . $sender->in_mail_port . "/{$protocol}";

        if ($safety === 'ssl') {
            $mailbox .= '/ssl';
        } elseif ($safety === 'tls') {
            $mailbox .= '/tls';
        } else {
            $mailbox .= '/notls';
        }

        $mailbox .= "/novalidate-cert}INBOX";

        $imap = imap_open($mailbox, $sender->username, decrypt($sender->password), 0, 1);
        if ($imap === false) {
            Log::error("IMAP fallita: " . implode(', ', imap_errors()));
            return false;
        }
        return $imap;
    }

    private function isOfficialPecReceipt($rawHeaders)
    {
        return preg_match('/^X-(?:Ricevuta|TipoRicevuta):\s*(?:accettazione|(?:avvenuta-)?consegna?|(?:mancata-)?accettazione?|(?:non-)?accettazione|(?:mancata-)?consegna?|anomalia)/mi', $rawHeaders);
    }

    private function streamBody($imap, $uid, $structure, $handle, $partNumber = null)
    {
        if (!isset($structure->parts)) {
            $body = imap_fetchbody($imap, $uid, $partNumber ?? 1, FT_UID | FT_PEEK);
            if ($structure->encoding == 3) {
                $body = base64_decode($body, true) ?: $body;
            } elseif ($structure->encoding == 4) {
                $body = quoted_printable_decode($body);
            }
            fwrite($handle, $body);
            return;
        }

        foreach ($structure->parts as $i => $subPart) {
            $this->streamBody($imap, $uid, $subPart, $handle, ($partNumber ? $partNumber . '.' : '') . ($i + 1));
        }
    }

    private function saveAttachments($imap, $uid, $structure, $inMail)
    {
        $folderPath = storage_path("app/public/in_mail/{$inMail->id}");
        if (!file_exists($folderPath)) {
            mkdir($folderPath, 0755, true);
        }

        $originalEmlPath = null;

        if (!isset($structure->parts)) {
            return "in_mail/{$inMail->id}";
        }

        foreach ($structure->parts as $partNumber => $part) {
            $this->extractAttachmentPart($imap, $uid, $part, $partNumber + 1, $folderPath, $inMail, $originalEmlPath);
        }

        // Se trovato .eml, estrai il corpo reale
        if ($originalEmlPath) {
            $this->extractBodyFromEml($originalEmlPath, $inMail);
        }

        return "in_mail/{$inMail->id}";
    }

    private function extractAttachmentPart($imap, $uid, $part, $partNumber, $folderPath, $inMail)
    {
        $isAttachment = false;
        $fileName = null;

        if (isset($part->dparameters)) {
            foreach ($part->dparameters as $object) {
                if (strtolower($object->attribute) == 'filename') {
                    $isAttachment = true;
                    $fileName = imap_utf8($object->value);
                }
            }
        }

        if (isset($part->parameters)) {
            foreach ($part->parameters as $object) {
                if (strtolower($object->attribute) == 'name') {
                    $isAttachment = true;
                    $fileName = imap_utf8($object->value);
                }
            }
        }

        if ($isAttachment && $fileName) {
            $body = imap_fetchbody($imap, $uid, $partNumber, FT_UID);

            switch ($part->encoding) {
                case 3:
                    $body = base64_decode($body);
                    break;
                case 4:
                    $body = quoted_printable_decode($body);
                    break;
            }

            $filePath = "{$folderPath}/{$fileName}";
            file_put_contents($filePath, $body);
        }

        if (isset($part->parts)) {
            foreach ($part->parts as $index => $subPart) {
                $this->extractAttachmentPart($imap, $uid, $subPart, $partNumber . '.' . ($index + 1), $folderPath, $inMail);
            }
        }
    }

    private function sanitizeUtf8($string)
    {
        if (is_null($string)) {
            return null;
        }
        $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        $string = iconv('UTF-8', 'UTF-8//IGNORE', $string);
        return $string;
    }

    private function extractRealSender($fromHeader)
    {
        if (!$fromHeader) return null;

        // 1. CERCA "Per conto di: email" (Aruba PEC)
        if (preg_match('/Per conto di:?\s*([^\s<"\'()]+)/i', $fromHeader, $matches)) {
            $email = trim($matches[1]);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        // 2. Solo se NON è Aruba: estrai tra < >
        if (preg_match('/<([^>]+)>/', $fromHeader, $matches)) {
            $email = $matches[1];
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        // 3. Ultimo tentativo: qualsiasi email
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $fromHeader, $matches)) {
            return $matches[0];
        }

        return null;
    }

    private function cleanSubject($subject)
    {
        if (!$subject) return null;

        // Rimuovi "POSTA CERTIFICATA: " (con o senza spazi)
        $subject = preg_replace('/^POSTA CERTIFICATA:\s*/i', '', $subject);

        // Rimuovi "R: ", "FWD: ", "RE: ", ecc. (opzionale)
        $subject = preg_replace('/^(R|RE|FWD|FW|TR|AW|VS):\s*/i', '', $subject);

        // Normalizza spazi
        $subject = trim($subject);

        return $subject;
    }
}
