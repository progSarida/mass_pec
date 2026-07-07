<?php

namespace App\Jobs;

use App\Enums\PecInteractionType;
use App\Enums\ShipmentErrorType;
use App\Models\PecInteraction;
use App\Models\Receiver;
// use App\Models\Sender;
use App\Models\Shipment;
use App\Models\ShipmentError;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;

class DownloadShipmentReceiptsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    public function __construct(
        public int $shipmentId,
        public int $userId,
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (!$user) return;

        imap_timeout(IMAP_OPENTIMEOUT, 30);
        imap_timeout(IMAP_READTIMEOUT, 60);

        // Teniamo traccia dei file creati per poterli cancellare in caso di Rollback
        $createdFiles = [];

        try {
            DB::beginTransaction();

            $shipment = Shipment::with('sender')->findOrFail($this->shipmentId);
            $sender = $shipment->sender;
            $receiptsPath = $this->ensureReceiptsPath($shipment->id);

            $imap = $this->connectToMail($sender);
            if (!$imap) throw new \Exception("Connessione IMAP fallita.");

            // 1. Ricerca Massiva
            $allUids = @imap_search($imap, 'SUBJECT "' . $shipment->mail_object . '"', SE_UID);
            $receiptsMap = [];

            if ($allUids) {
                foreach ($allUids as $uid) {
                    $rawHeaders = @imap_fetchheader($imap, $uid, FT_UID);
                    if (!$this->isOfficialPecReceipt($rawHeaders)) continue;

                    $headerInfo = @imap_headerinfo($imap, imap_msgno($imap, $uid));
                    [$type, $ref] = $this->getReceiptInfo($rawHeaders, $headerInfo->subject ?? '');



                    if ($type && $ref) {
                        $receiptsMap[$ref][] = [
                            'uid'  => $uid,
                            'type' => $type,
                            'body' => @imap_body($imap, $uid, FT_UID)
                        ];
                    }
                }
            }

            // 2. Elaborazione
            $recipients = Receiver::where('shipment_id', $shipment->id)->get();

            $count = ["send" => 0, "missedSend" => 0, "delivery" => 0, "missedDelivery" => 0, "anomaly" => 0];

            foreach ($recipients as $recipient) {
                if (isset($receiptsMap[$recipient->ref])) {
                    foreach ($receiptsMap[$recipient->ref] as $receiptData) {
                        // Salvataggio file e tracciamento per eventuale cleanup
                        $filename = "{$recipient->ref}_" . str_replace(" ", "-", $receiptData['type']) . ".eml";
                        $fullPath = "{$receiptsPath}/{$filename}";
                        Storage::put($fullPath, $receiptData['body']);
                        $createdFiles[] = $fullPath;

                        $this->updateRecipientData($recipient, $receiptData['type'], $count, $shipment);

                        if ($sender->delete) {
                            @imap_delete($imap, $receiptData['uid'], FT_UID);
                        }
                    }
                    $recipient->save();
                }
            }

            // 3. Update finale Shipment
            $shipment->increment('no_send_receipt', $count["send"]);
            $shipment->increment('no_missed_send_receipt', $count["missedSend"]);
            $shipment->increment('no_delivery_receipt', $count["delivery"]);
            $shipment->increment('no_missed_delivery_receipt', $count["missedDelivery"]);
            $shipment->increment('no_anomaly_receipt', $count["anomaly"]);

            // Se arriviamo qui, tutto è andato bene
            DB::commit();

            @imap_expunge($imap);
            @imap_close($imap);

            // INSERISCO QUI LA CREAZIONE DEL RECORD DI pec_interactions ('shipment_receipts', today())
            PecInteraction::create([
                'pec_interaction_type' => PecInteractionType::SHIPMENT_RECEIPT,
                'registry_id' => null,
                'interaction_date' => now(),
                'user_id' => $user->id,
            ]);

            $this->sendFinalNotification($user, $shipment, $count);

        } catch (\Throwable $e) {
            DB::rollBack();

            // PULIZIA FILE: Se il DB torna indietro, cancelliamo i file creati in questa sessione
            foreach ($createdFiles as $filePath) {
                Storage::delete($filePath);
            }

            Log::error("Rollback eseguito. Errore: " . $e->getMessage());
            $this->notifyError($user, "Errore critico: i dati non sono stati salvati.");

            if (isset($imap)) @imap_close($imap);
            throw $e;
        }
    }

    private function updateRecipientData($recipient, $type, &$count, $shipment): void
    {
        if ($type === "ACCETTAZIONE" && empty($recipient->send_receipt)) {
            $recipient->send_receipt = "received";
            $count["send"]++;
        } elseif ($type === "AVVISO DI MANCATA ACCETTAZIONE" && $recipient->send_receipt !== "missed") {
            $recipient->send_receipt = "missed";
            $count["missedSend"]++;
            $this->logShipmentError($shipment, $recipient, ShipmentErrorType::NOT_ACCEPTED);
        } elseif ($type === "CONSEGNA" && empty($recipient->delivery_receipt)) {
            $recipient->delivery_receipt = "received";
            $count["delivery"]++;
        } elseif ($type === "AVVISO DI MANCATA CONSEGNA" && $recipient->delivery_receipt !== "missed") {
            $recipient->delivery_receipt = "missed";
            $count["missedDelivery"]++;
            $this->logShipmentError($shipment, $recipient, ShipmentErrorType::NOT_DELIVERED);
        } elseif ($type === "ANOMALIA MESSAGGIO" && empty($recipient->anomaly_receipt)) {
            $recipient->anomaly_receipt = "received";
            $count["anomaly"]++;
            $this->logShipmentError($shipment, $recipient, ShipmentErrorType::ANOMALY);
        }
    }

    private function getReceiptInfo($rawHeaders, $subjectHeader): array
    {
        $type = null;
        $ref = null;

        // 1. Identifichiamo il TIPO tramite gli Header (Più affidabile)
        if (preg_match('/^X-Ricevuta:\s*([a-z-]+)/mi', $rawHeaders, $m)) {
            $type = match(strtolower(trim($m[1]))) {
                'accettazione'      => 'ACCETTAZIONE',
                'avvenuta-consegna' => 'CONSEGNA',
                'non-accettazione'  => 'AVVISO DI MANCATA ACCETTAZIONE',
                'errore-consegna'   => 'AVVISO DI MANCATA CONSEGNA',
                'anomalia'          => 'ANOMALIA MESSAGGIO',
                default             => null
            };
        }

        // 2. Se X-Ricevuta fallisce, proviamo X-TipoRicevuta
        if (!$type && preg_match('/^X-TipoRicevuta:\s*([a-z-]+)/mi', $rawHeaders, $m)) {
            $type = match(strtolower(trim($m[1]))) {
                'accettazione'         => 'ACCETTAZIONE',
                'consegna'             => 'CONSEGNA',
                'mancata-accettazione' => 'AVVISO DI MANCATA ACCETTAZIONE',
                'mancata-consegna'     => 'AVVISO DI MANCATA CONSEGNA',
                'anomalia'             => 'ANOMALIA MESSAGGIO',
                default                => null
            };
        }

        // 3. Estraiamo il [REF] dal Soggetto (Indispensabile per il match)
        // Usiamo iconv_mime_decode per gestire accenti o caratteri speciali nel soggetto
        $decodedSubject = iconv_mime_decode($subjectHeader, 0, "UTF-8");

        // Cerchiamo il valore tra le ultime parentesi quadre della stringa
        if (preg_match('/\[([^\]]+)\]\s*$/', $decodedSubject, $matches)) {
            $ref = trim($matches[1]);
        }

        return [$type, $ref];
    }

    private function isOfficialPecReceipt($rawHeaders): bool
    {
        if (preg_match('/^X-Ricevuta:\s*(accettazione|avvenuta-consegna|non-accettazione|anomalia|errore-consegna)/mi', $rawHeaders)) return true;

        if (preg_match('/^X-TipoRicevuta:\s*(accettazione|consegna|mancata-accettazione|mancata-consegna|anomalia|errore-consegna)/mi', $rawHeaders)) return true;

        return false;
    }

    private function connectToMail($sender)
    {
        $protocol = strtolower($sender->in_mail_protocol_type->value);
        $safety = strtolower($sender->connection_safety_type->value);

        // Costruiamo la stringa esattamente come la facevi tu
        $flags = "/{$protocol}";
        if ($safety === 'ssl') $flags .= '/ssl';
        elseif ($safety === 'tls') $flags .= '/tls';
        else $flags .= '/notls';

        $flags .= '/novalidate-cert';

        $mailbox = "{" . $sender->in_mail_server . ":" . $sender->in_mail_port . $flags . "}INBOX";

        // Il flag 0, 1 alla fine indica: 0 tentativi extra, 1 connessione singola
        return @imap_open($mailbox, $sender->username, decrypt($sender->password), 0, 1);
    }

    private function ensureReceiptsPath($id)
    {
        $path = "shipments/{$id}/receipts";
        if (!Storage::exists($path)) Storage::makeDirectory($path);
        return $path;
    }

    private function logShipmentError($shipment, $recipient, $type)
    {
        ShipmentError::updateOrCreate([
            'shipment_id' => $shipment->id,
            'recipient_id' => $recipient->id,
            'shipment_error_type' => $type,
        ], [
            'address' => $recipient->address,
            'send_date' => $recipient->send_date,
        ]);
    }

    private function sendFinalNotification($user, $shipment, $count)
    {
        $total = array_sum($count);
        if ($total > 0) {
            Notification::make()->title("Ricevute scaricate")->success()->sendToDatabase($user);
        }
    }

    private function notifyError($user, $msg)
    {
        Notification::make()->title("Errore")->body($msg)->danger()->sendToDatabase($user);
    }
}
