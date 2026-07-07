<?php

namespace App\Jobs;

use App\Enums\PecInteractionType;
use App\Enums\PecStatus;
use App\Models\Account;
use App\Models\PecInteraction;
use App\Models\Registry;
use App\Models\RegistryReceiver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DownloadReceiptsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;

    public function __construct(
        public int $registryId,
        public int $userId,
    ) {}

    public function handle(): void
    {
        $user = \App\Models\User::find($this->userId);
        if (!$user) {
            Log::error("User non trovato", ['id' => $this->userId]);
            return;
        }

        $registry = Registry::find($this->registryId);
        if (!$registry) {
            Log::error("Registry non trovato", ['id' => $this->registryId]);
            $this->notifyError($user, "Registry non trovato");
            return;
        }

        if (!$registry->account_id) {
            Log::warning("Registry senza account", ['id' => $this->registryId]);
            $this->notifyError($user, "Registry senza account configurato");
            return;
        }

        $sender = Account::find($registry->account_id);
        if (!$sender) {
            Log::error("Account mittente non trovato", ['account_id' => $registry->account_id]);
            $this->notifyError($user, "Account mittente non trovato");
            return;
        }

        $receivers = RegistryReceiver::where('registry_id', $registry->id)->get();
        if ($receivers->isEmpty()) {
            Log::warning("Nessun destinatario trovato", ['registry_id' => $this->registryId]);
            $this->notifyError($user, "Nessun destinatario trovato");
            return;
        }

        $receiptsPath = $this->ensureReceiptsPath($registry->protocol_number);

        Log::info("Inizio recupero ricevute PEC per protocollo {$registry->protocol_number}");

        $imap = null;
        $receiptsProcessed = 0;
        $receiversProcessed = 0;
        $receiversFailed = 0;
        $errors = [];

        try {
            $imap = $this->connectToMail($sender);
            if (!$imap) {
                throw new \Exception("Errore IMAP: " . implode(', ', imap_errors() ?: ['Connessione fallita']));
            }

            foreach ($receivers as $receiver) {
                try {
                    Log::info("Elaborazione: {$registry->subject} → {$receiver->address}");

                    $subject = "[{$registry->protocol_number}] " . $registry->subject;
                    $processed = $this->processPecReceipts($imap, $sender, $receiver, $subject, $receiptsPath);

                    if ($processed) {
                        $receiptsProcessed++;
                    }

                    $receiver->save();
                    $receiversProcessed++;

                } catch (\Throwable $e) {
                    $receiversFailed++;
                    $errorMsg = "Errore destinatario {$receiver->address}: " . $e->getMessage();
                    $errors[] = $errorMsg;

                    Log::error("Errore elaborazione destinatario", [
                        'registry_id' => $this->registryId,
                        'receiver_id' => $receiver->id,
                        'address' => $receiver->address,
                        'error' => $e->getMessage()
                    ]);

                    // Continua con il prossimo destinatario
                    continue;
                }
            }

            imap_expunge($imap);
            imap_close($imap);

            Log::info("Ricevute elaborate per protocollo {$registry->protocol_number}: {$receiptsProcessed}");

            // Notifica finale
            $this->sendFinalNotification($user, $registry, $receiptsProcessed, $receiversProcessed, $receiversFailed, $errors);

            // INSERISCO QUI LA CREAZIONE DEL RECORD DI pec_interactions ('receipt', today())
            PecInteraction::create([
                'pec_interaction_type' => PecInteractionType::RECEIPT,
                'registry_id' => null,
                'interaction_date' => now(),
                'user_id' => $this->userId,
            ]);

        } catch (\Throwable $e) {
            Log::error("Errore download ricevute: " . $e->getMessage(), [
                'registry_id' => $this->registryId,
                'protocol_number' => $registry->protocol_number,
                'trace' => $e->getTraceAsString()
            ]);

            // Chiudi connessione IMAP se aperta
            if ($imap) {
                try {
                    imap_close($imap);
                } catch (\Throwable $closeError) {
                    Log::error("Errore chiusura IMAP", ['error' => $closeError->getMessage()]);
                }
            }

            $this->notifyError($user, $e->getMessage());
            throw $e;
        }
    }

    private function sendFinalNotification($user, $registry, $receiptsProcessed, $receiversProcessed, $receiversFailed, $errors): void
    {
        if ($receiversFailed > 0 && $receiversProcessed === 0) {
            // Tutti i destinatari sono falliti
            \Filament\Notifications\Notification::make()
                ->title('Download ricevute fallito')
                ->body("Protocollo {$registry->protocol_number}: nessun destinatario elaborato. Dettagli: " . implode('; ', $errors))
                ->danger()
                ->persistent()
                ->sendToDatabase($user);
        } elseif ($receiversFailed > 0) {
            // Alcuni destinatari sono falliti
            $body = "Protocollo {$registry->protocol_number}: processate {$receiptsProcessed} ricevute da {$receiversProcessed} destinatari.";
            $body .= " {$receiversFailed} destinatari hanno avuto errori.";

            \Filament\Notifications\Notification::make()
                ->title('Ricevute scaricate con errori')
                ->body($body)
                ->warning()
                ->sendToDatabase($user);
        } else {
            // Tutto ok
            $body = $receiptsProcessed === 0
                ? "Protocollo {$registry->protocol_number}: nessuna nuova ricevuta trovata."
                : "Protocollo {$registry->protocol_number}: processate {$receiptsProcessed} ricevute.";

            \Filament\Notifications\Notification::make()
                ->title('Ricevute scaricate')
                ->body($body)
                ->success()
                ->sendToDatabase($user);
        }
    }

    private function notifyError($user, $message): void
    {
        \Filament\Notifications\Notification::make()
            ->title('Errore download ricevute')
            ->body($message)
            ->danger()
            ->sendToDatabase($user);
    }

    private function ensureReceiptsPath($protocolNumber)
    {
        $path = "registry/{$protocolNumber}/receipts";
        if (!Storage::exists($path)) {
            Storage::makeDirectory($path);
        }
        return $path;
    }

    private function connectToMail($sender)
    {
        $protocol = strtolower($sender->in_mail_protocol_type->value);
        $safety   = strtolower($sender->connection_safety_type->value);

        $mailbox = "{" . $sender->in_mail_server . ":" . $sender->in_mail_port . "/{$protocol}";

        if ($safety === 'ssl') {
            $mailbox .= '/ssl';
        } elseif ($safety === 'tls') {
            $mailbox .= '/tls';
        } else {
            $mailbox .= '/notls';
        }

        $mailbox .= "/novalidate-cert}INBOX";

        $imap = @imap_open($mailbox, $sender->username, decrypt($sender->password), 0, 1);

        if ($imap === false) {
            Log::error("IMAP fallita: " . implode(', ', imap_errors() ?: ['Connessione fallita']));
            return false;
        }

        return $imap;
    }

    private function processPecReceipts($imap, $account, &$receiver, $subject, $receiptsPath): bool
    {
        imap_errors();
        $searchCriteria = 'SUBJECT "' . $subject . '"';

        Log::info("Ricerca: {$searchCriteria} → {$receiver->address}");

        $uids = @imap_search($imap, $searchCriteria, SE_UID);
        imap_errors();

        if (!$uids) return false;

        $processed = false;

        foreach ($uids as $uid) {
            try {
                $overview = @imap_fetch_overview($imap, $uid, FT_UID);
                $date = $overview[0]->udate ?? null;
                $rawHeaders = @imap_fetchheader($imap, $uid, FT_UID);
                $body = @imap_body($imap, $uid, FT_UID);

                if (!$rawHeaders || !$body) {
                    Log::warning("Impossibile leggere messaggio UID {$uid}");
                    continue;
                }

                if (!$this->isOfficialPecReceipt($rawHeaders)) {
                    // non controllo cancellazione perchè la mail potrebbe non essere stata ancora scaricata anche se è passato il periodo indicato
                    // $this->handleDeletion($imap, $uid, $account, $date);
                    Log::info("UID: {$uid} - Non è ricevuta EPC");
                    continue;
                }

                // Verifica destinatario corretto
                if ($receiver->message_id) {
                    if (!$this->isRightReceiptId($rawHeaders, $receiver->message_id)) {
                        if($receiver->pec_status != PecStatus::WAITING && $receiver->pec_status != PecStatus::ACCEPTED)         // controllo la cancellazione della ricevuta se la pec è già indicata come consegnata
                            $this->handleDeletion($imap, $uid, $account, $date);
                        Log::info("UID: {$uid} - Non è il destinatario corretto");
                        continue;
                    }
                } else {
                    if (!$this->isRightReceipt($body, $receiver->address)) {
                        if($receiver->pec_status != PecStatus::WAITING && $receiver->pec_status != PecStatus::ACCEPTED)         // controllo la cancellazione della ricevuta se la pec è già indicata come consegnata
                            $this->handleDeletion($imap, $uid, $account, $date);
                        Log::info("UID: {$uid} - Non è il destinatario corretto");
                        continue;
                    }
                }

                $headerInfo = @imap_headerinfo($imap, imap_msgno($imap, $uid));
                $type = $this->getReceiptInfo($rawHeaders, $headerInfo->subject ?? '');

                if (!$type) {
                    // non controllo cancellazione per gestire successivamente la discrepanza del tipo
                    Log::info("UID: {$uid} - Tipo ricevuta non riconosciuto");
                    // $this->handleDeletion($imap, $uid, $account, $date);
                    continue;
                }

                // Salva file ricevuta
                $this->saveReceiptFile($receiptsPath, $receiver->address, $type, $body);

                // Aggiorna stato
                $oldStatus = $receiver->pec_status;

                if ($type === "ANOMALIA MESSAGGIO") {
                    $receiver->pec_status = PecStatus::ANOMALY;
                }
                elseif ($type === "ACCETTAZIONE" && $receiver->pec_status === PecStatus::WAITING) {
                    $receiver->pec_status = PecStatus::ACCEPTED;
                }
                elseif ($type === "AVVISO DI MANCATA ACCETTAZIONE" && $receiver->pec_status === PecStatus::WAITING) {
                    $receiver->pec_status = PecStatus::NOT_ACCEPTED;
                }
                elseif ($type === "CONSEGNA") {
                    $receiver->pec_status = PecStatus::DELIVERED;
                }
                elseif ($type === "AVVISO DI MANCATA CONSEGNA") {
                    $receiver->pec_status = PecStatus::NOT_DELIVERED;
                }

                if ($oldStatus !== $receiver->pec_status) {
                    Log::info("Stato aggiornato per {$receiver->address}: {$oldStatus->getLabel()} -> {$receiver->pec_status->getLabel()}");
                    $processed = true;
                }

                $this->handleDeletion($imap, $uid, $account, $date);

            } catch (\Throwable $e) {
                Log::error("Errore processamento UID {$uid}", [
                    'receiver' => $receiver->address,
                    'error' => $e->getMessage()
                ]);
                // Continua con il prossimo UID
                continue;
            }
        }

        return $processed;
    }

    private function handleDeletion($imap, $uid, $account, $date): void
    {
        if (!$account->delete || !$date) return;

        try {
            Log::info("Controllo cancellazione UID {$uid}");
            if ($account->delete_after_days) {
                $deleteDate = now()->subDays($account->delete_after_days)->startOfDay();
                if (\Carbon\Carbon::parse($date)->lt($deleteDate)) {
                    Log::info("Cancellato UID {$uid}");
                    @imap_delete($imap, $uid, FT_UID);
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Errore eliminazione messaggio UID {$uid}", [
                'error' => $e->getMessage()
            ]);
        }
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

    private function isRightReceiptId($rawHeaders, $message_id): bool
    {
        $normalizedId = trim($message_id, '<> ');
        $pattern = '/^X-Riferimento-Message-ID:\s*<?' . preg_quote($normalizedId, '/') . '>?/mi';
        return (bool) preg_match($pattern, $rawHeaders);
    }

    private function isRightReceipt($body, $address): bool
    {
        $address = strtolower(trim($address));
        $loweredBody = strtolower($body);

        if (str_contains($loweredBody, $address)) {
            return true;
        }

        $quotedAddress = str_replace('@', '=40', $address);
        if (str_contains($loweredBody, $quotedAddress)) {
            return true;
        }

        if (preg_match_all('/<destinatario[^>]*>(.*?)<\/destinatario>/s', $loweredBody, $matches)) {
            foreach ($matches[1] as $match) {
                if (trim($match) === $address) {
                    return true;
                }
            }
        }

        $localPart = explode('@', $address)[0];
        if (str_contains($loweredBody, $localPart) && str_contains($loweredBody, 'consegna')) {
            Log::debug("isRightReceipt: Trovata corrispondenza parziale per {$address}");
            return true;
        }

        return false;
    }

    private function saveReceiptFile($receiptsPath, $receiverAddress, $receiptType, $body)
    {
        try {
            $filename = "{$receiverAddress}_" . str_replace(" ", "-", $receiptType) . ".eml";
            Storage::put($receiptsPath . '/' . $filename, $body);
        } catch (\Throwable $e) {
            Log::error("Errore salvataggio ricevuta", [
                'path' => $receiptsPath,
                'filename' => $filename ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function getReceiptInfo($rawHeaders, $subjectHeader): ?string
    {
        $type = null;

        $decodedSubject = iconv_mime_decode($subjectHeader ?? '', 0, "UTF-8");

        if (preg_match('/^(ACCETTAZIONE|CONSEGNA|AVVISO DI MANCATA (?:ACCETTAZIONE|CONSEGNA)|ANOMALIA MESSAGGIO):/i', $decodedSubject, $matches)) {
            $type = strtoupper($matches[1]);
        }

        if (preg_match('/^X-Ricevuta:\s*(.+)/mi', $rawHeaders, $arubaMatches)) {
            $arubaType = strtolower(trim($arubaMatches[1]));

            $arubaMap = [
                'accettazione'      => 'ACCETTAZIONE',
                'avvenuta-consegna' => 'CONSEGNA',
                'non-accettazione'  => 'AVVISO DI MANCATA ACCETTAZIONE',
                'errore-consegna'   => 'AVVISO DI MANCATA CONSEGNA',
                'preavviso-errore-consegna' => 'ANOMALIA MESSAGGIO',
            ];

            if (isset($arubaMap[$arubaType])) {
                $type = $arubaMap[$arubaType];
            }
        }

        return $type;
    }
}
