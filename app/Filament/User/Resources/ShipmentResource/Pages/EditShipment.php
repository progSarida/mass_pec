<?php

namespace App\Filament\User\Resources\ShipmentResource\Pages;

use App\Filament\User\Resources\ShipmentResource;
use App\Models\Receiver;
use App\Models\Sender;
use App\Models\Shipment;
use Carbon\Carbon;
use Exception;
use Filament\Actions;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPMailer\PHPMailer\PHPMailer;

class EditShipment extends EditRecord
{
    protected static string $resource = ShipmentResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        try {
            if (!empty($data['out_password'])) {
                $data['out_password'] = decrypt($data['out_password']);
            }
            if (!empty($data['password'])) {
                $data['password'] = decrypt($data['password']);
            }
        } catch (\Exception $e) {
            // Ignora se non criptato
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!empty($data['out_password'])) {
            $data['out_password'] = encrypt($data['out_password']);
        }
        if (!empty($data['password'])) {
            $data['password'] = encrypt($data['password']);
        }

        return $data;
    }

    protected $listeners = [                                                                                                // ascolto eventi action
        'start-shipment-send' => 'sendShipmentBackground',
        'shipment-sent-success' => 'onShipmentSuccess',
        'shipment-sent-error' => 'onShipmentError',
    ];

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('send')
                ->label('Invio PEC')
                ->icon('hugeicons-mail-send-01')
                // ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Conferma invio PEC')
                ->modalDescription('L\'invio partirà immediatamente. Continuare?')
                ->modalSubmitActionLabel('Sì, invia')
                ->action(function () {
                    $shipmentId = $this->record->id;
                    try {
                        $this->dispatch('start-shipment-send', shipmentId: $shipmentId);

                        Notification::make()
                            ->title('Invio PEC avviato')
                            ->body('L\'invio è in corso in background...')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Errore')
                            ->body('Impossibile avviare l\'invio: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\Action::make('download')
                ->label('Scarico ricevute')
                ->icon('hugeicons-mail-receive-01')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Scarica ricevute PEC')
                ->modalDescription('Verranno scaricate tutte le ricevute di accettazione, consegna e anomalie.')
                ->modalSubmitActionLabel('Scarica')
                ->action(function () {
                    $shipmentId = $this->record->id;

                    try {
                        $this->downloadReceipts($shipmentId);

                        Notification::make()
                            ->title('Ricevute scaricate')
                            ->body('Tutte le ricevute sono state elaborate con successo.')
                            ->success()
                            ->send();

                        $this->refreshFormData([
                            'no_send_receipt',
                            'no_missed_send_receipt',
                            'no_delivery_receipt',
                            'no_missed_delivery_receipt',
                            'no_anomaly_receipt'
                        ]);

                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Errore scarico')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\Action::make('extract')
                ->label('Estrazione')
                ->action(function (array $data) {
                    dd('ESTRAZIONE');
                }),
            Actions\Action::make('receivers')
                ->label('Pec  destinatari')
                ->modalHeading('Pec destinatari')
                ->modalWidth('5xl')
                ->form([
                    Placeholder::make('receivers_list')
                        ->label('')
                        ->content(function () {
                            $receivers = $this->getReceiversForForm();
                            if (empty($receivers)) {
                                return 'Nessun destinatario';
                            }

                            $html = '<div class="grid grid-cols-1 md:grid-cols-3 gap-3">';
                            foreach ($receivers as $receiver) {
                                $html .= '<div class="p-3 bg-gray-50 rounded-lg text-sm font-medium text-gray-900">';
                                $html .= e($receiver['address']);
                                $html .= '</div>';
                            }
                            $html .= '</div>';

                            return new \Illuminate\Support\HtmlString($html);
                        })
                ]),
            Actions\DeleteAction::make(),
        ];
    }

    private function getReceiversForForm(): array
    {
        $record = $this->record;
        if (!$record) return [];

        return Receiver::where('shipment_id', $record->id)
            ->get()
            ->map(fn($receiver) => ['address' => $receiver->address])
            ->toArray();
    }

    public function sendShipmentBackground($shipmentId)                                                                     // avvia invio in background
    {
        try {
            $this->sendShipment($shipmentId);

            $this->dispatch('shipment-sent-success');
        } catch (\Exception $e) {
            $this->dispatch('shipment-sent-error', message: $e->getMessage());
        }
    }

    public function sendShipment($id)
    {
        set_time_limit(120);
        ini_set('max_execution_time', 120);
        try {
            DB::beginTransaction();

            $shipment = Shipment::find($id);
            if (!$shipment) throw new \Exception("Spedizione non trovata!");

            $sender = Sender::find($shipment->sender_id);
            $recipients = Receiver::join('recipients as R', 'R.id', '=', 'receivers.recipient_id')
                ->where('shipment_id', $shipment->id)
                ->select('receivers.*', 'R.description as r_description')
                ->get();

            $sent = 0;
            $not_sent = 0;

            $smtp = strtolower($sender->out_mail_protocol_type->value) == 'smtp';
            $auth = (bool) $sender->out_authentication;
            $host = $sender->out_mail_server;
            $port = $sender->out_mail_port;
            $secure = $sender->connection_safety_type->value;
            $username = $sender->out_username;
            $password = decrypt($sender->out_password);
            $from = $sender->out_username;
            $name = $sender->public_name;
            $body = $shipment->mail_body;
            $attachmentPath = storage_path('app/public') . $shipment->shipment_path . $shipment->attachment;

            if (!file_exists($attachmentPath)) {
                throw new \Exception("Allegato non trovato: " . $attachmentPath);
            }

            foreach ($recipients as $recipient) {
                if (is_null($recipient->send_date)) {
                    $subject = $shipment->mail_object . " [" . $recipient->ref . "]";
                    $email = new PHPMailer(true);
                    $email->Timeout = 60;

                    if ($smtp) $email->isSMTP();
                    $email->Host = $host;
                    $email->Port = $port;

                    if ($auth) {
                        $email->SMTPAuth = true;
                        $email->Username = $username;
                        $email->Password = $password;
                    }

                    switch (strtolower($secure)) {
                        case 'ssl':
                            $email->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;                                               // porta 465
                            break;
                        case 'tls':
                            $email->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;                                            // porta 587
                            break;
                        default:
                            $email->SMTPSecure = '';                                                                        // nessuna crittografia
                    }

                    $email->setFrom($from, $name);
                    $email->addAddress($recipient->address, $recipient->r_description);
                    $email->Subject = $subject;
                    $email->Body = $body;
                    $email->addAttachment($attachmentPath);

                    try {
                        if ($email->send()) {
                            $recipient->update(['send_date' => now()->format('Y-m-d')]);
                            $sent++;
                        } else {
                            $not_sent++;
                        }
                    } catch (Exception $e) {
                        $not_sent++;
                        Log::error("Errore invio PEC a {$recipient->address}: " . $e->getMessage());
                    }
                } else {
                    $sent++;
                }
            }

            $shipment->update([
                'no_mails_sended' => $sent,
                'no_mails_to_send' => $not_sent
            ]);

            DB::commit();

            // Notifica finale
            $this->dispatch('shipment-sent-success', sent: $sent, failed: $not_sent);

        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error("Errore invio spedizione {$id}: " . $ex->getMessage());
            $this->dispatch('shipment-sent-error', message: $ex->getMessage());
        }
    }

    public function onShipmentSuccess()                                                                                         // notifica successo
    {
        Notification::make()
            ->title('Invio completato')
            ->success()
            ->send();

        $this->refreshFormData(['no_mails_sended', 'no_mails_to_send']);
    }

    public function onShipmentError($message)                                                                                   // notifica errore
    {
        Notification::make()
            ->title('Errore invio')
            ->body($message)
            ->danger()
            ->send();
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

        $imap = imap_open($mailbox, $sender->username, decrypt($sender->password), 0, 1);

        if ($imap === false) {
            Log::error("IMAP fallita: " . implode(', ', imap_errors()));
            return false;
        }

        return $imap;
    }

    private function ensureReceiptsPath($shipmentId)
    {
        $path = storage_path("app/public/archive/shipments/{$shipmentId}/receipts");
        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }
        return $path;
    }

    private function parseSubject($subjectHeader)
    {
        $decoded = iconv_mime_decode($subjectHeader ?? '', 0, "UTF-8");
        $parts = explode(":", $decoded);
        if (count($parts) < 2) return [null, null];

        $receiptType = strtoupper(trim($parts[0]));
        $subjectRef = trim(str_replace("]", "", explode("[", $parts[1])[1] ?? ''));

        return [$receiptType, $subjectRef];
    }

    private function saveReceiptFile($receiptsPath, $subjectRef, $receiptType, $body)
    {
        $filename = "{$subjectRef}_" . str_replace(" ", "-", $receiptType) . ".eml";
        file_put_contents($receiptsPath . $filename, $body);
    }

    private function processReceipts($imap, &$recipient, $subject, $receiptsPath, &$count)
    {
        $uids = imap_search($imap, 'SUBJECT "' . $subject . '"', SE_UID) ?: [];

        foreach ($uids as $uid) {
            $msgno = imap_msgno($imap, $uid);
            $header = imap_headerinfo($imap, $msgno);
            [$receiptType, $subjectRef] = $this->parseSubject($header->subject ?? '');

            if (!$receiptType || !$subjectRef) continue;

            $body = imap_fetchbody($imap, $msgno, '');
            $this->saveReceiptFile($receiptsPath, $subjectRef, $receiptType, $body);

            // Accettazione
            if (empty($recipient->send_receipt)) {
                if ($receiptType === "ACCETTAZIONE") {
                    $recipient->send_receipt = "received";
                    $count["send"]++;
                } elseif ($receiptType === "AVVISO DI MANCATA ACCETTAZIONE") {
                    $recipient->send_receipt = "missed";
                    $count["missedSend"]++;
                }
                imap_delete($imap, $uid, FT_UID);
            }

            // Consegna (solo PEC)
            if (empty($recipient->delivery_receipt) && $recipient->mail_type === "pec") {
                if ($receiptType === "CONSEGNA") {
                    $recipient->delivery_receipt = "received";
                    $count["delivery"]++;
                } elseif ($receiptType === "AVVISO DI MANCATA CONSEGNA") {
                    $recipient->delivery_receipt = "missed";
                    $count["missedDelivery"]++;
                }
                imap_delete($imap, $uid, FT_UID);
            }
        }
    }

    private function processAnomalies($imap, &$recipient, $subject, $receiptsPath, &$count)
    {
        if ($recipient->delivery_receipt === "received" || !empty($recipient->anomaly_receipt)) return;

        $uids = imap_search($imap, 'ALL', SE_UID) ?: [];
        foreach ($uids as $uid) {
            $body = imap_body($imap, $uid, FT_UID);
            if (strpos($body, $subject) === false) continue;

            $header = imap_headerinfo($imap, imap_msgno($imap, $uid));
            [$receiptType, $subjectRef] = $this->parseSubject($header->subject ?? '');

            if ($receiptType === "ANOMALIA MESSAGGIO") {
                $this->saveReceiptFile($receiptsPath, $subjectRef, $receiptType, $body);
                $recipient->anomaly_receipt = "received";
                $count["anomaly"]++;
                imap_delete($imap, $uid, FT_UID);
                break;
            }
        }
    }

    public function downloadReceipts($shipmentId)
    {
        set_time_limit(120);
        ini_set('max_execution_time', 120);
        try {
            DB::beginTransaction();

            $shipment = Shipment::findOrFail($shipmentId);
            $sender = Sender::findOrFail($shipment->sender_id);

            $recipients = Receiver::join('recipients as R', 'R.id', '=', 'receivers.recipient_id')
                ->where('shipment_id', $shipment->id)
                ->select('receivers.*', 'R.description as r_description')
                ->get();

            $receiptsPath = $this->ensureReceiptsPath($shipment->id);

            Log::info("------------------------------------------------------------------------");
            Log::info("Inizio recupero");

            $imap = $this->connectToMail($sender);
            if (!$imap) {
                throw new \Exception("Errore IMAP: " . implode(', ', imap_errors()));
            }
            else{
                Log::info("IMAP collegata!");
            }

            $count = [
                "send" => 0,
                "missedSend" => 0,
                "delivery" => 0,
                "missedDelivery" => 0,
                "anomaly" => 0
            ];

            foreach ($recipients as $recipient) {
                Log::info("Recupero ricevuta di " . $shipment->mail_object . " [{$recipient->ref}] da " . $recipient->r_description);
                if (!empty($recipient->send_receipt) && !empty($recipient->delivery_receipt)) {
                    continue;
                }

                $subject = $shipment->mail_object . " [{$recipient->ref}]";

                $this->processAnomalies($imap, $recipient, $subject, $receiptsPath, $count);
                $this->processReceipts($imap, $recipient, $subject, $receiptsPath, $count);

                $recipient->save();
            }

            Log::info("Inviate: " . $count["send"]);
            Log::info("Mancato invio: " . $count["missedSend"]);
            Log::info("Consegnate: " . $count["delivery"]);
            Log::info("Mancata consegna: " . $count["missedDelivery"]);
            Log::info("Anomalie: " . $count["anomaly"]);

            imap_expunge($imap);
            imap_close($imap);

            $shipment->update([
                'no_send_receipt' => $count["send"],
                'no_missed_send_receipt' => $count["missedSend"],
                'no_delivery_receipt' => $count["delivery"],
                'no_missed_delivery_receipt' => $count["missedDelivery"],
                'no_anomaly_receipt' => $count["anomaly"]
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
