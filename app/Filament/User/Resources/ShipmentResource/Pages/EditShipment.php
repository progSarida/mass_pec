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
use Illuminate\Support\Facades\Storage;
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

    protected $listeners = [
        'start-shipment-send' => 'sendShipmentBackground',
        'shipment-sent-success' => 'onShipmentSuccess',
        'shipment-sent-error' => 'onShipmentError',
    ];

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Indietro')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
            Actions\Action::make('send')
                ->label('Invio PEC')
                ->icon('hugeicons-mail-send-01')
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
                        $relativePath = ltrim($shipment->shipment_path, '/') . '/' . $shipment->extraction_zip_file;

                        if (Storage::exists($relativePath)) {
                            return Storage::download($relativePath, $shipment->extraction_zip_file);
                        }
                    }

                    Notification::make()
                        ->warning()
                        ->title('File non trovato')
                        ->send();
                }),
            Actions\Action::make('receivers')
                ->label('Pec destinatari')
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
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()->color('success'),
            $this->getCancelFormAction(),
            $this->getResetFormAction(),
            $this->getDeleteFormAction()
                ->extraAttributes([
                    'class' => ' md:ml-auto md:w-auto ',
                ]),
        ];
    }

    protected function getDeleteFormAction()
    {
        return Actions\DeleteAction::make('delete')
                ->requiresConfirmation()
                ->modalHeading('Conferma eliminazione spedizione')
                ->modalDescription('Sei sicuro di voler eliminare questa spedizione? Questa azione non può essere annullata.')
                ->modalSubmitActionLabel('Elimina')
                ->modalCancelActionLabel('Annulla');
    }

    protected function getCancelFormAction(): Actions\Action
    {
        return Actions\Action::make('cancel')
            ->label('Indietro')
            ->color('gray')
            ->url(function () {
                if ($this->previousUrl && str($this->previousUrl)->contains('/contacts?')) {
                    return $this->previousUrl;
                }
                return ShipmentResource::getUrl('index');
            });
    }

    protected function getResetFormAction(): Actions\Action
    {
        return Actions\Action::make('reset')
            ->label('Annulla')
            ->color('gray')
            ->action(function () {
                $this->data = $this->getRecord()->toArray();
                $this->fillForm();
            });
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

    public function sendShipmentBackground($shipmentId)
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

        $tempAttachment = null;

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

            // Gestione allegato compatibile con Storage
            $attachmentRelativePath = ltrim($shipment->shipment_path . '/' . $shipment->attachment, '/');

            if (!Storage::exists($attachmentRelativePath)) {
                throw new \Exception("Allegato non trovato: " . $attachmentRelativePath);
            }

            // Scarica allegato in file temporaneo per PHPMailer
            $tempAttachment = tempnam(sys_get_temp_dir(), 'attachment_');
            file_put_contents($tempAttachment, Storage::get($attachmentRelativePath));

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
                            $email->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                            break;
                        case 'tls':
                            $email->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            break;
                        default:
                            $email->SMTPSecure = '';
                    }

                    $email->setFrom($from, $name);
                    $email->addAddress($recipient->address, $recipient->r_description);
                    $email->Subject = $subject;
                    $email->Body = $body;
                    $email->addAttachment($tempAttachment, $shipment->attachment);

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

            // Elimina file temporaneo
            if ($tempAttachment && file_exists($tempAttachment)) {
                @unlink($tempAttachment);
            }

            $shipment->update([
                'no_mails_sended' => $sent,
                'no_mails_to_send' => $not_sent
            ]);

            DB::commit();

            $this->dispatch('shipment-sent-success', sent: $sent, failed: $not_sent);

        } catch (\Exception $ex) {
            DB::rollBack();

            if ($tempAttachment && file_exists($tempAttachment)) {
                @unlink($tempAttachment);
            }

            Log::error("Errore invio spedizione {$id}: " . $ex->getMessage());
            $this->dispatch('shipment-sent-error', message: $ex->getMessage());
        }
    }

    public function onShipmentSuccess()
    {
        Notification::make()
            ->title('Invio completato')
            ->success()
            ->send();

        $this->refreshFormData(['no_mails_sended', 'no_mails_to_send']);
    }

    public function onShipmentError($message)
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
        $path = "archive/shipments/{$shipmentId}/receipts";
        if (!Storage::exists($path)) {
            Storage::makeDirectory($path);
        }
        return $path;
    }

    private function isOfficialPecReceipt($rawHeaders)
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
        Storage::put($receiptsPath . '/' . $filename, $body);
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

    private function processPecReceipts($imap, &$recipient, $subject, $receiptsPath, &$count)
    {
        $searchCriteria = 'SUBJECT "' . $subject . '"';
        foreach (imap_search($imap, $searchCriteria, SE_UID) ?: [] as $uid) {
            $rawHeaders = imap_fetchheader($imap, $uid, FT_UID);

            if (!$this->isOfficialPecReceipt($rawHeaders)) continue;

            [$type, $ref] = $this->getReceiptInfo($rawHeaders, imap_headerinfo($imap, imap_msgno($imap, $uid))->subject ?? '');

            if (!$type || !$ref) continue;

            // Salva file usando Storage
            $body = imap_body($imap, $uid, FT_UID);
            $this->saveReceiptFile($receiptsPath, $ref, $type, $body);

            // Anomalia
            if ($type === "ANOMALIA MESSAGGIO" && empty($recipient->anomaly_receipt)) {
                $recipient->anomaly_receipt = "received";
                $count["anomaly"]++;
            }

            // Accettazione
            if (empty($recipient->send_receipt)) {
                if ($type === "ACCETTAZIONE") {
                    $recipient->send_receipt = "received";
                    $count["send"]++;
                }
                elseif ($type === "AVVISO DI MANCATA ACCETTAZIONE") {
                    $recipient->send_receipt = "missed";
                    $count["missedSend"]++;
                }
            }

            // Consegna (solo PEC)
            if (empty($recipient->delivery_receipt) && $recipient->mail_type === "pec") {
                if ($type === "CONSEGNA") {
                    $recipient->delivery_receipt = "received";
                    $count["delivery"]++;
                }
                elseif ($type === "AVVISO DI MANCATA CONSEGNA") {
                    $recipient->delivery_receipt = "missed";
                    $count["missedDelivery"]++;
                }
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

    private function extractShipment($id)
    {
        $tempDir = null;

        try {
            DB::beginTransaction();

            $shipment = Shipment::findOrFail($id);
            $recipients = Receiver::join('recipients as R', 'R.id', '=', 'receivers.recipient_id')
                ->where('shipment_id', $id)
                ->select('R.description', 'R.city_id', 'receivers.ref as object_ref', 'receivers.address', 'receivers.mail_type')
                ->get();

            // Crea cartella temporanea locale
            $tempDir = sys_get_temp_dir() . '/extraction_' . $id . '_' . time();
            mkdir($tempDir, 0755, true);

            $filename = 'ricevute-pec_' . $id . '_' . now()->format('Y-m-d_H-i-s');
            $zipFilename = $filename . '.zip';
            $xlsFilename = $filename . '.xlsx';
            $zipPath = $tempDir . '/' . $zipFilename;

            // Elimina vecchia estrazione da Storage
            if ($shipment->extraction_zip_file) {
                $oldZipPath = ltrim($shipment->shipment_path, '/') . '/' . $shipment->extraction_zip_file;
                $oldXlsPath = ltrim($shipment->shipment_path, '/') . '/' . str_replace('.zip', '.xlsx', $shipment->extraction_zip_file);

                Storage::delete($oldZipPath);
                Storage::delete($oldXlsPath);

                // Elimina anche le ricevute precedenti
                $oldReceiptsPath = ltrim($shipment->shipment_path, '/');
                $oldReceipts = Storage::files($oldReceiptsPath);
                foreach ($oldReceipts as $oldReceipt) {
                    if (pathinfo($oldReceipt, PATHINFO_EXTENSION) === 'eml') {
                        Storage::delete($oldReceipt);
                    }
                }
            }

            // Leggi ricevute da Storage
            $folderPath = ltrim($shipment->shipment_path, '/');
            // $allFiles = Storage::files($folderPath);
            $allFiles = Storage::allFiles($folderPath);
            $receipts = array_filter($allFiles, function($file) {
                return pathinfo($file, PATHINFO_EXTENSION) === 'eml';
            });
            $receipts = array_map('basename', $receipts);

            $header = ["Descrizione", "Comune", "Indirizzo Mail", "Tipo", "Accettazione", "File Acc.", "Consegna", "File Cons.", "Anomalia", "File An."];
            $dataExcel = [];
            $toZip = [];

            foreach ($recipients as $row) {
                $input = $row->object_ref;
                $result = preg_grep("/{$input}/i", $receipts);

                $recSend = $recSendFile = $recDeliver = $recDeliverFile = $recAnomaly = $recAnomalyFile = '';

                foreach ($result as $line) {
                    $name = pathinfo($line, PATHINFO_FILENAME);
                    $refs = explode('_', $name);
                    if (count($refs) < 4) continue;

                    $recType = str_replace('-', ' ', $refs[3]);

                    // Scarica file da Storage a temp
                    $s3FilePath = $folderPath . '/' . $line;
                    $localFilePath = $tempDir . '/' . $line;
                    file_put_contents($localFilePath, Storage::get($s3FilePath));
                    $toZip[] = $localFilePath;

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
                    $recSend, $recSendFile,
                    $recDeliver, $recDeliverFile,
                    $recAnomaly, $recAnomalyFile,
                ];
            }

            // Crea Excel locale
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray($header, null, 'A1');
            $sheet->fromArray($dataExcel, null, 'A2');
            $writer = new Xlsx($spreadsheet);
            $xlsPath = $tempDir . '/' . $xlsFilename;
            $writer->save($xlsPath);
            $toZip[] = $xlsPath;

            // Aggiungi allegato originale da Storage
            $attachmentS3Path = ltrim($shipment->shipment_path, '/') . '/' . $shipment->attachment;
            if (Storage::exists($attachmentS3Path)) {
                $attachmentLocalPath = $tempDir . '/' . $shipment->attachment;
                file_put_contents($attachmentLocalPath, Storage::get($attachmentS3Path));
                $toZip[] = $attachmentLocalPath;
            }

            // Crea ZIP locale
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \Exception("Impossibile creare ZIP");
            }
            foreach ($toZip as $file) {
                if (file_exists($file)) {
                    $zip->addFile($file, basename($file));
                }
            }
            $zip->close();

            // Carica ZIP su Storage
            $s3ZipPath = ltrim($shipment->shipment_path, '/') . '/' . $zipFilename;
            Storage::put($s3ZipPath, file_get_contents($zipPath));

            // Aggiorna DB
            $shipment->update([
                'extraction_date' => now()->format('Y-m-d'),
                'extraction_zip_file' => $zipFilename
            ]);

            // Pulisci cartella temporanea
            $this->cleanupTempDir($tempDir);

            DB::commit();

            Notification::make()
                ->success()
                ->title('Estrazione completata')
                ->body("File: {$zipFilename}")
                ->send();

        } catch (\Exception $e) {
            DB::rollBack();

            if ($tempDir && is_dir($tempDir)) {
                $this->cleanupTempDir($tempDir);
            }

            Log::error("Estrazione fallita [ID: {$id}]: " . $e->getMessage());

            Notification::make()
                ->danger()
                ->title('Errore estrazione')
                ->body($e->getMessage())
                ->send();
        }
    }

    private function cleanupTempDir($tempDir)
    {
        if (!is_dir($tempDir)) return;

        $files = glob($tempDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($tempDir);
    }

    private function readZip($id, $filename)
    {
        try {
            $zipRelativePath = ltrim("archive/shipments/{$id}/{$filename}", '/');

            if (!Storage::exists($zipRelativePath)) {
                return [];
            }

            // Scarica ZIP in temp per leggerlo
            $tempZip = tempnam(sys_get_temp_dir(), 'zip_');
            file_put_contents($tempZip, Storage::get($zipRelativePath));

            $zip = new ZipArchive();
            if ($zip->open($tempZip) !== true) {
                @unlink($tempZip);
                return [];
            }

            $fileNames = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $fileNames[] = basename($stat['name']);
            }

            $zip->close();
            @unlink($tempZip);

            return $fileNames;
        } catch (\Exception $e) {
            Log::error("Errore lettura ZIP: " . $e->getMessage());
            return [];
        }
    }

    private function extractZip($zipRelativePath, $destinationPath)
    {
        try {
            if (!Storage::exists($zipRelativePath)) {
                return false;
            }

            // Scarica ZIP in temp
            $tempZip = tempnam(sys_get_temp_dir(), 'zip_');
            file_put_contents($tempZip, Storage::get($zipRelativePath));

            // Crea temp dir per estrazione
            $tempExtractDir = sys_get_temp_dir() . '/extract_' . time();
            mkdir($tempExtractDir, 0755, true);

            $zip = new ZipArchive();
            if ($zip->open($tempZip) !== true) {
                @unlink($tempZip);
                @rmdir($tempExtractDir);
                return false;
            }

            $zip->extractTo($tempExtractDir);
            $zip->close();
            @unlink($tempZip);

            // Carica tutti i file estratti su Storage
            $files = glob($tempExtractDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    $filename = basename($file);
                    $targetPath = ltrim($destinationPath, '/') . '/' . $filename;
                    Storage::put($targetPath, file_get_contents($file));
                    @unlink($file);
                }
            }

            @rmdir($tempExtractDir);
            return true;

        } catch (\Exception $e) {
            Log::error("Errore estrazione ZIP: " . $e->getMessage());
            return false;
        }
    }
}
