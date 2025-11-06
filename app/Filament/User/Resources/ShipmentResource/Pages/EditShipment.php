<?php

namespace App\Filament\User\Resources\ShipmentResource\Pages;

use App\Enums\MailType;
use App\Filament\User\Resources\ShipmentResource;
use App\Models\City;
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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ZipArchive;

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
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Confermi estrazione?')
                ->modalDescription('Verrà generato un file ZIP con Excel e ricevute.')
                ->modalSubmitActionLabel('Sì, estrai')
                ->action(function () {
                    $this->extractShipment($this->record->id);

                    $shipment = $this->record->fresh();
                    if ($shipment->extraction_zip_file) {
                        $path = storage_path("app/public{$shipment->shipment_path}/{$shipment->extraction_zip_file}");
                        if (file_exists($path)) {
                            return response()->download($path, $shipment->extraction_zip_file);
                        }
                    }

                    Notification::make()
                        ->warning()
                        ->title('File non trovato')
                        ->send();
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

    private function isOfficialPecReceiptOk($rawHeaders)
    {
        // Aruba: X-Ricevuta
        if (preg_match('/^X-Ricevuta:\s*(accettazione|avvenuta-consegna|non-accettazione|anomalia)/mi', $rawHeaders)) {
            return true;
        }

        // Poste, LegalMail, Namirial, Register, ecc.: X-TipoRicevuta
        if (preg_match('/^X-TipoRicevuta:\s*(accettazione|consegna|mancata-accettazione|mancata-consegna|anomalia)/mi', $rawHeaders)) {
            return true;
        }

        return false;
    }

    private function parseSubjectOld($subjectHeader)
    {
        $decoded = iconv_mime_decode($subjectHeader ?? '', 0, "UTF-8");
        $parts = explode(":", $decoded);
        if (count($parts) < 2) return [null, null];

        $receiptType = strtoupper(trim($parts[0]));
        $subjectRef = trim(str_replace("]", "", explode("[", $parts[1])[1] ?? ''));

        return [$receiptType, $subjectRef];
    }

    private function parseSubject($subjectHeader)
    {
        $decoded = iconv_mime_decode($subjectHeader ?? '', 0, "UTF-8");

        if (preg_match('/^(ACCETTAZIONE|CONSEGNA|AVVISO DI MANCATA ACCETTAZIONE|AVVISO DI MANCATA CONSEGNA|ANOMALIA MESSAGGIO):\s*(.+?)\s*\[(.+?)\]$/i', $decoded, $matches)) {
            $receiptType = strtoupper(trim($matches[1]));
            $subjectRef = trim($matches[3]);
            return [$receiptType, $subjectRef];
        }

        return [null, null];
    }

    private function saveReceiptFile($receiptsPath, $subjectRef, $receiptType, $body)
    {
        $filename = "{$subjectRef}_" . str_replace(" ", "-", $receiptType) . ".eml";
        file_put_contents($receiptsPath . $filename, $body);
    }

    private function processReceiptsOld($imap, &$recipient, $subject, $receiptsPath, &$count)
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
                // imap_delete($imap, $uid, FT_UID);
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
                // imap_delete($imap, $uid, FT_UID);
            }
        }
    }

    private function processReceipts($imap, &$recipient, $subject, $receiptsPath, &$count)
    {
        $searchCriteria = 'SUBJECT "' . $subject . '"';
        $uids = imap_search($imap, $searchCriteria, SE_UID) ?: [];

        foreach ($uids as $uid) {
            $msgno = imap_msgno($imap, $uid);
            $rawHeaders = imap_fetchheader($imap, $uid, FT_UID);
            $header = imap_headerinfo($imap, $msgno);

            if (!$this->isOfficialPecReceipt($rawHeaders)) {
                continue;
            }

            [$receiptType, $subjectRef] = $this->parseSubject($header->subject ?? '');
            if (!$receiptType || !$subjectRef) {
                continue;
            }

            // Normalizza tipo per Aruba
            if (preg_match('/^X-Ricevuta:\s*avvenuta-consegna/mi', $rawHeaders)) {
                $receiptType = "CONSEGNA";
            } elseif (preg_match('/^X-Ricevuta:\s*non-accettazione/mi', $rawHeaders)) {
                $receiptType = "AVVISO DI MANCATA ACCETTAZIONE";
            }

            $body = imap_fetchbody($imap, $msgno, '');
            $this->saveReceiptFile($receiptsPath, $subjectRef, $receiptType, $body);

            // --- Accettazione ---
            if (empty($recipient->send_receipt)) {
                if ($receiptType === "ACCETTAZIONE") {
                    $recipient->send_receipt = "received";
                    $count["send"]++;
                } elseif ($receiptType === "AVVISO DI MANCATA ACCETTAZIONE") {
                    $recipient->send_receipt = "missed";
                    $count["missedSend"]++;
                }
                // imap_delete($imap, $uid, FT_UID);
            }

            // --- Consegna (solo PEC) ---
            if (empty($recipient->delivery_receipt) && $recipient->mail_type === "pec") {
                if ($receiptType === "CONSEGNA") {
                    $recipient->delivery_receipt = "received";
                    $count["delivery"]++;
                } elseif ($receiptType === "AVVISO DI MANCATA CONSEGNA") {
                    $recipient->delivery_receipt = "missed";
                    $count["missedDelivery"]++;
                }
                // imap_delete($imap, $uid, FT_UID);
            }
        }
    }

    private function processAnomaliesOld($imap, &$recipient, $subject, $receiptsPath, &$count)
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
                // imap_delete($imap, $uid, FT_UID);
                break;
            }
        }
    }

    private function processAnomalies($imap, &$recipient, $subject, $receiptsPath, &$count)
    {
        if ($recipient->delivery_receipt === "received" || !empty($recipient->anomaly_receipt)) {
            return;
        }

        $searchCriteria = 'SUBJECT "' . $subject . '"';
        $uids = imap_search($imap, $searchCriteria, SE_UID) ?: [];

        foreach ($uids as $uid) {
            $msgno = imap_msgno($imap, $uid);
            $rawHeaders = imap_fetchheader($imap, $uid, FT_UID);
            $header = imap_headerinfo($imap, $msgno);

            if (!$this->isOfficialPecReceipt($rawHeaders)) {
                continue;
            }

            [$receiptType, $subjectRef] = $this->parseSubject($header->subject ?? '');
            if ($receiptType !== "ANOMALIA MESSAGGIO") {
                continue;
            }

            $body = imap_body($imap, $uid, FT_UID);
            $this->saveReceiptFile($receiptsPath, $subjectRef, $receiptType, $body);

            $recipient->anomaly_receipt = "received";
            $count["anomaly"]++;
            // imap_delete($imap, $uid, FT_UID);
            break;
        }
    }

    public function downloadReceiptsOk($shipmentId)
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

    private function extractShipment($id)
    {
        try {
            DB::beginTransaction();

            $shipment = \App\Models\Shipment::findOrFail($id);
            $sender = \App\Models\Sender::findOrFail($shipment->sender_id);

            $recipients = \App\Models\Receiver::join('recipients as R', 'R.id', '=', 'receivers.recipient_id')
                ->where('shipment_id', $id)
                ->select('R.description', 'R.city_id', 'receivers.ref as object_ref', 'receivers.address', 'receivers.mail_type')
                ->get();

            $folder = storage_path("app/public" . $shipment->shipment_path);
            if (!is_dir($folder)) {
                mkdir($folder, 0755, true);
            }

            $filename = 'ricevute-pec_' . $id . '_' . now()->format('Y-m-d_h-i-s');
            $zipFilename = $filename . '.zip';
            $xlsFilename = $filename . '.xlsx';
            $zipFile = $folder . $zipFilename;

            // Rimuovi vecchia estrazione
            if ($shipment->extraction_zip_file && file_exists($folder . $shipment->extraction_zip_file)) {
                $oldZip = $folder . $shipment->extraction_zip_file;
                if ($this->extractZip($oldZip, $folder)) {
                    @unlink($oldZip);
                    @unlink($folder . str_replace('.zip', '.xlsx', $shipment->extraction_zip_file));
                }
            }

            // Leggi file nella cartella (ricevute scaricate)
            $receipts = array_diff(scandir($folder), ['.', '..']);

            $header = ["Descrizione", "Comune", "Indirizzo Mail", "Tipo", "Accettazione", "File Acc.", "Consegna", "File Cons.", "Anomalia", "File An."];
            $dataExcel = [];
            $toZip = [];

            foreach ($recipients as $row) {
                $input = $row->object_ref;
                $result = preg_grep("/{$input}/i", $receipts);

                $recSend = $recSendFile = $recDeliver = $recDeliverFile = $recAnomaly = $recAnomalyFile = '';

                foreach ($result as $line) {
                    $fullPath = $folder . $line;
                    if (!is_file($fullPath)) continue;

                    $name = pathinfo($line, PATHINFO_FILENAME);
                    $refs = explode('_', $name);
                    if (count($refs) < 4) continue;

                    $recType = str_replace('-', ' ', $refs[3]);
                    $toZip[] = $fullPath;

                    match (strtoupper($recType)) {
                        'ACCETTAZIONE', 'AVVISO DI MANCATA ACCETTAZIONE' => [$recSend, $recSendFile] = [$recType, $line],
                        'CONSEGNA', 'AVVISO DI MANCATA CONSEGNA' => [$recDeliver, $recDeliverFile] = [$recType, $line],
                        'ANOMALIA MESSAGGIO' => [$recAnomaly, $recAnomalyFile] = [$recType, $line],
                        default => null,
                    };
                }

                $dataExcel[] = [
                    $row->description,
                    City::find($row->city_id)->name ?? '',
                    $row->address,
                    MailType::from($row->mail_type)->getLabel(),
                    $recSend,
                    $recSendFile,
                    $recDeliver,
                    $recDeliverFile,
                    $recAnomaly,
                    $recAnomalyFile,
                ];
            }

            // Crea Excel
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray($header, null, 'A1');
            $sheet->fromArray($dataExcel, null, 'A2');
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($folder . $xlsFilename);
            $toZip[] = $folder . $xlsFilename;

            // Aggiungi allegato originale
            $attachmentPath = storage_path("app/public/archive/shipments/{$id}/{$shipment->attachment}");
            if (file_exists($attachmentPath)) {
                $toZip[] = $attachmentPath;
            }

            // Crea ZIP
            $zip = new ZipArchive();
            if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \Exception("Impossibile creare ZIP");
            }
            foreach ($toZip as $file) {
                if (file_exists($file)) {
                    $zip->addFile($file, basename($file));
                }
            }
            $zip->close();

            // Aggiorna DB
            $shipment->update([
                'extraction_date' => now()->format('Y-m-d'),
                'extraction_zip_file' => $zipFilename
            ]);

            // Pulizia (tranne ZIP finale)
            foreach ($toZip as $file) {
                if (basename($file) !== $zipFilename && file_exists($file)) {
                    @unlink($file);
                }
            }

            DB::commit();

            Notification::make()
                ->success()
                ->title('Estrazione completata')
                ->body("File: {$zipFilename}")
                ->send();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Estrazione fallita [ID: {$id}]: " . $e->getMessage());

            Notification::make()
                ->danger()
                ->title('Errore estrazione')
                ->body($e->getMessage())
                ->send();
        }
    }

    private function readZip($id, $filename)
    {
        try {
            $zipPath = storage_path("app/public/archive/shipments/{$id}/{$filename}");
            if (!file_exists($zipPath)) {
                return [];
            }
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) !== true) {
                return [];
            }
            $fileNames = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $fileNames[] = basename($stat['name']);
            }
            $zip->close();
            return $fileNames;
        } catch (\Exception $e) {
            Log::error("Errore lettura ZIP: " . $e->getMessage());
            return [];
        }
    }

    private function extractZip($file, $path)
    {
        $zip = new ZipArchive();
        if ($zip->open($file) && $zip->extractTo($path)) {
            $zip->close();
            return true;
        }
        return false;
    }

    private function getReceiptInfo($rawHeaders, $subjectHeader)
    {
        // Parse subject (tutti i provider)
        if (preg_match('/^(ACCETTAZIONE|CONSEGNA|AVVISO DI MANCATA (?:ACCETTAZIONE|CONSEGNA)|ANOMALIA MESSAGGIO):\s*(.+?)\s*\[(.+?)\]$/i',
                    iconv_mime_decode($subjectHeader ?? '', 0, "UTF-8"), $matches)) {
            [$type, $ref] = [strtoupper($matches[1]), trim($matches[3])];
        } else {
            return [null, null];
        }

        // Override Aruba da X-Ricevuta (più preciso del subject)
        if (preg_match_all('/^X-Ricevuta:\s*(.+)/mi', $rawHeaders, $arubaTypes)) {
            $arubaType = strtolower(trim($arubaTypes[1][0]));
            $arubaMap = [
                'avvenuta-consegna' => 'CONSEGNA',
                'non-accettazione' => 'AVVISO DI MANCATA ACCETTAZIONE'
            ];
            $type = $arubaMap[$arubaType] ?? $type;
        }

        return [$type, $ref];
    }

    private function isOfficialPecReceipt($rawHeaders)
    {
        return preg_match('/^X-(?:Ricevuta|TipoRicevuta):\s*(?:accettazione|(?:avvenuta-)?consegna?|(?:mancata-)?accettazione?|(?:non-)?accettazione|(?:mancata-)?consegna?|anomalia)/mi', $rawHeaders);
    }

    private function processPecReceipts($imap, &$recipient, $subject, $receiptsPath, &$count)
    {
        $searchCriteria = 'SUBJECT "' . $subject . '"';
        foreach (imap_search($imap, $searchCriteria, SE_UID) ?: [] as $uid) {
            $rawHeaders = imap_fetchheader($imap, $uid, FT_UID);

            if (!$this->isOfficialPecReceipt($rawHeaders)) continue;

            [$type, $ref] = $this->getReceiptInfo($rawHeaders, imap_headerinfo($imap, imap_msgno($imap, $uid))->subject ?? '');

            if (!$type || !$ref) continue;

            // Salva file
            $body = imap_body($imap, $uid, FT_UID);
            $this->saveReceiptFile($receiptsPath, $ref, $type, $body);

            // Anomalia
            if ($type === "ANOMALIA MESSAGGIO" && empty($recipient->anomaly_receipt)) {
                $recipient->anomaly_receipt = "received"; $count["anomaly"]++; imap_delete($imap, $uid, FT_UID); break;
            }

            // Accettazione
            if (empty($recipient->send_receipt)) {
                if ($type === "ACCETTAZIONE") { $recipient->send_receipt = "received"; $count["send"]++; }
                elseif ($type === "AVVISO DI MANCATA ACCETTAZIONE") { $recipient->send_receipt = "missed"; $count["missedSend"]++; }
                imap_delete($imap, $uid, FT_UID);
            }

            // Consegna (solo PEC)
            if (empty($recipient->delivery_receipt) && $recipient->mail_type === "pec") {
                if ($type === "CONSEGNA") { $recipient->delivery_receipt = "received"; $count["delivery"]++; }
                elseif ($type === "AVVISO DI MANCATA CONSEGNA") { $recipient->delivery_receipt = "missed"; $count["missedDelivery"]++; }
                imap_delete($imap, $uid, FT_UID);
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
            Log::info("Inizio recupero ricevute PEC per shipment {$shipment->id}");

            $imap = $this->connectToMail($sender);
            if (!$imap) {
                throw new \Exception("Errore IMAP: " . implode(', ', imap_errors()));
            }
            Log::info("IMAP collegata con successo.");

            $count = ["send" => 0, "missedSend" => 0, "delivery" => 0, "missedDelivery" => 0, "anomaly" => 0];

            foreach ($recipients as $recipient) {
                Log::info("Elaborazione: {$shipment->mail_object} [{$recipient->ref}] → {$recipient->r_description}");

                if (!empty($recipient->send_receipt) && !empty($recipient->delivery_receipt)) {
                    continue;
                }

                $subject = $shipment->mail_object . " [{$recipient->ref}]";
                $this->processPecReceipts($imap, $recipient, $subject, $receiptsPath, $count);
                $recipient->save();
            }

            Log::info("Ricevute elaborate → Accettazione: {$count['send']}, Mancate: {$count['missedSend']}, Consegna: {$count['delivery']}, Mancata consegna: {$count['missedDelivery']}, Anomalie: {$count['anomaly']}");

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
