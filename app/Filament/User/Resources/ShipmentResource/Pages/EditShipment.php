<?php

namespace App\Filament\User\Resources\ShipmentResource\Pages;

use App\Enums\MailType;
use App\Enums\ShipmentErrorType;
use App\Filament\User\Resources\ShipmentResource;
use App\Jobs\ProcessShipmentEmailJob;
use App\Models\City;
use App\Models\Receiver;
use App\Models\Registry;
use App\Models\ScopeType;
use App\Models\Sender;
use App\Models\Shipment;
use App\Models\ShipmentError;
use Carbon\Carbon;
use Exception;
use Filament\Actions;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPMailer\PHPMailer\PHPMailer;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ZipArchive;

class EditShipment extends EditRecord
{
    protected static string $resource = ShipmentResource::class;

    public function getTitle(): string | Htmlable
    {
        // return $this->record->description;
        return "Modifica spedizione";
    }

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

    // protected $listeners = [
    //     'start-shipment-send' => 'sendShipmentBackground',
    //     'shipment-sent-success' => 'onShipmentSuccess',
    //     'shipment-sent-error' => 'onShipmentError',
    // ];

    protected function getHeaderActions(): array
    {
        $currentShipment = $this->record;
        $previousCShipment = Shipment::where('created_at', '<=', $currentShipment->created_at)->where('id', '!=', $currentShipment->id)
                                ->orderBy('created_at', 'desc')->orderBy('id', 'desc')->first();
        $nextCShipment = Shipment::where('created_at', '>=', $currentShipment->created_at)->where('id', '!=', $currentShipment->id)
                                ->orderBy('created_at', 'asc')->orderBy('id', 'asc')->first();
        $previousIRegistry = Shipment::where('id', $currentShipment->flow_type)->orderBy('id', 'desc')->first();
        $nextIRegistry = Shipment::where('id', $currentShipment->flow_type)->orderBy('id', 'asc')->first();
        return [
            // Scorrimento cronologico
            Actions\Action::make('previous_c_shipment')
                ->label('Precedente')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousCShipment) { return $previousCShipment;})
                ->action(function () use ($previousCShipment) {
                    $this->redirect(ShipmentResource::getUrl('edit', ['record' => $previousCShipment->id]));
                }),
            Actions\Action::make('next_c_shipment')
                ->label('Successivo')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextCShipment) { return $nextCShipment;})
                ->action(function () use ($nextCShipment) {
                    $this->redirect(ShipmentResource::getUrl('edit', ['record' => $nextCShipment->id]));
                }),
            Actions\ActionGroup::make([
                // Actions\Action::make('send')
                //     ->label('Invio PEC')
                //     ->icon('hugeicons-mail-send-01')
                //     ->requiresConfirmation()
                //     ->modalHeading('Conferma invio PEC')
                //     ->modalDescription('L\'invio partirà immediatamente. Continuare?')
                //     ->modalSubmitActionLabel('Sì, invia')
                //     ->action(function () {
                //         $shipmentId = $this->record->id;
                //         try {
                //             $this->dispatch('start-shipment-send', shipmentId: $shipmentId);

                //             Notification::make()
                //                 ->title('Invio PEC avviato')
                //                 ->body('L\'invio è in corso in background...')
                //                 ->success()
                //                 ->send();
                //         } catch (\Exception $e) {
                //             Notification::make()
                //                 ->title('Errore')
                //                 ->body('Impossibile avviare l\'invio: ' . $e->getMessage())
                //                 ->danger()
                //                 ->send();
                //         }
                //     }),
                Actions\Action::make('receivers')
                    ->label('Pec destinatari')
                    ->icon('fluentui-people-team-toolbox-20-o')
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
                            ->extraAttributes([
                                'style' => 'min-height: 10vh; max-height: 67vh; overflow-y: auto;'
                            ])
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false),

                Actions\Action::make('sendShipment')
                    ->label('Invio PEC')
                    ->icon('hugeicons-mail-send-01')
                    // ->label('Invia Spedizione')
                    // ->icon('heroicon-o-paper-airplane')
                    // ->color('success')
                    ->requiresConfirmation() // Chiede conferma prima di partire
                    ->modalHeading('Conferma Invio')
                    ->modalDescription('Sei sicuro di voler avviare l\'invio massivo per questa spedizione?')
                    ->action(function ($record) {
                        // Lanciamo il Job Padre (Orchestratore)
                        ProcessShipmentEmailJob::dispatch(
                            $record->id,
                            Auth::id()
                        );

                        // Feedback immediato all'interfaccia
                        Notification::make()
                            ->title('Invio avviato')
                            ->body('Il processo di invio è stato preso in carico dal sistema.')
                            ->info()
                            ->send();
                    })
                    // Opzionale: nascondi il tasto se la spedizione è già stata inviata
                    ->hidden(fn ($record) => $record->send_date !== null),

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
                Actions\Action::make('register')
                    ->label('Protocolla')
                    ->icon('fluentui-pen-20-o')
                    ->color('warning')
                    ->visible(fn($record) => (Auth::user()->hasRole('super_admin') || Auth::user()->hasRole('manager'))
                                                && $record->extraction_zip_file
                                                && !Registry::where('uid', '#shipment' . $record->id)->exists()
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Protocolla spedizione')
                    ->modalDescription('La spedizione verrà inserita nel protocollo')
                    ->modalSubmitActionLabel('Protocolla')
                    ->form([
                        Select::make('scope_type_id')
                            ->label('Settore interno')
                            ->options(ScopeType::pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('Seleziona il settore interno della registrazione')
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            static::registerShipment($record, $data['scope_type_id']);
                            Notification::make()
                                ->title('Mail protocollata')
                                ->body('La spedizione e i suoi allegati sono stati protocollati con successo.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Errore registrazione')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->label('Operazioni')
            ->icon('heroicon-m-ellipsis-vertical')
            ->color('info')
            ->button(),
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
                ->modalDescription('Questa azione non può essere annullata.')
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
        $path = "shipments/{$shipmentId}/receipts";
        if (!Storage::exists($path)) {
            Storage::makeDirectory($path);
        }
        return $path;
    }

    private function isOfficialPecReceipt($rawHeaders)
    {
        // Log::info("Header: {$rawHeaders}");
        // Aruba: X-Ricevuta
        if (preg_match('/^X-Ricevuta:\s*(accettazione|avvenuta-consegna|non-accettazione|anomalia|errore-consegna)/mi', $rawHeaders)) {
            return true;
        }

        // Poste, LegalMail, Namirial, Register, ecc.: X-TipoRicevuta
        if (preg_match('/^X-TipoRicevuta:\s*(accettazione|consegna|mancata-accettazione|mancata-consegna|anomalia|errore-consegna)/mi', $rawHeaders)) {
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
                'accettazione'      => 'ACCETTAZIONE',
                'avvenuta-consegna' => 'CONSEGNA',
                'non-accettazione'  => 'AVVISO DI MANCATA ACCETTAZIONE',
                'errore-consegna'   => 'AVVISO DI MANCATA CONSEGNA',
            ];
            $type = $arubaMap[$arubaType] ?? $type;
        }

        return [$type, $ref];
    }

    private function processPecReceipts($imap, &$recipient, $subject, $receiptsPath, &$count)
    {
        // dd($recipient->send_date, 'STOP');
        $searchCriteria = 'SUBJECT "' . $subject . '"';
        $refs = $recipient->recipientRefs();
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
                ShipmentError::create([
                    'shipment_id' => $refs['shipment']->id,
                    'recipient_id' => $refs['recipient']->id,
                    'address' => $refs['address'],
                    'send_date' => $recipient->send_date,
                    'shipment_error_type' => ShipmentErrorType::ANOMALY,
                ]);
            }

            // Accettazione
            if (empty($recipient->send_receipt)) {
                if ($type === "ACCETTAZIONE") {
                    $recipient->send_receipt = "received";
                    $count["send"]++;
                }
                else if ($type === "AVVISO DI MANCATA ACCETTAZIONE") {
                    $recipient->send_receipt = "missed";
                    $count["missedSend"]++;
                    ShipmentError::create([
                        'shipment_id' => $refs['shipment']->id,
                        'recipient_id' => $refs['recipient']->id,
                        'address' => $refs['address'],
                        'send_date' => $recipient->send_date,
                        'shipment_error_type' => ShipmentErrorType::NOT_ACCEPTED,
                    ]);
                }
            }

            // Consegna (solo PEC)
            if (empty($recipient->delivery_receipt) && $recipient->mail_type === "pec") {
                if ($type === "CONSEGNA") {
                    $recipient->delivery_receipt = "received";
                    $count["delivery"]++;
                }
                else if ($type === "AVVISO DI MANCATA CONSEGNA") {
                    $recipient->delivery_receipt = "missed";
                    $count["missedDelivery"]++;
                    ShipmentError::create([
                        'shipment_id' => $refs['shipment']->id,
                        'recipient_id' => $refs['recipient']->id,
                        'address' => $refs['address'],
                        'send_date' => $recipient->send_date,
                        'shipment_error_type' => ShipmentErrorType::NOT_DELIVERED,
                    ]);
                }
            }

            // Eliminazione ricevuta
            imap_delete($imap, $uid, FT_UID);
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


            $shipment->update([
                'no_send_receipt' => $count["send"],
                'no_missed_send_receipt' => $count["missedSend"],
                'no_delivery_receipt' => $count["delivery"],
                'no_missed_delivery_receipt' => $count["missedDelivery"],
                'no_anomaly_receipt' => $count["anomaly"]
            ]);

            DB::commit();

            imap_expunge($imap);
            imap_close($imap);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function extractShipmentOld($id)
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

            $filename = 'estrazione_' . $id . '_' . now()->format('Y-m-d_H-i-s');
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

    private function extractShipment($id)
    {
        set_time_limit(300);                                                            // Estende il timeout a 5 minuti
        ini_set('memory_limit', '512M');                                                // Aumenta la RAM disponibile per questa operazione
        $tempDir = null;

        try {
            DB::beginTransaction();

            $shipment = Shipment::findOrFail($id);
            $recipients = Receiver::join('recipients as R', 'R.id', '=', 'receivers.recipient_id')
                ->where('shipment_id', $id)
                ->select('R.description', 'R.city_id', 'receivers.ref as object_ref', 'receivers.address', 'receivers.mail_type')
                ->get();

            // 1. Crea cartella temporanea locale unica
            $tempDir = sys_get_temp_dir() . '/extraction_' . $id . '_' . microtime(true);
            if (!mkdir($tempDir, 0755, true)) {
                throw new \Exception("Impossibile creare la cartella temporanea locale.");
            }

            $filename = 'estrazione_' . $id . '_' . now()->format('Y-m-d_H-i-s');
            $zipFilename = $filename . '.zip';
            $xlsFilename = $filename . '.xlsx';
            $zipPath = $tempDir . '/' . $zipFilename;

            // 2. Pulizia vecchie estrazioni su Storage (S3)
            $folderPath = ltrim((string)$shipment->shipment_path, '/');
            if ($shipment->extraction_zip_file) {
                Storage::delete($folderPath . '/' . $shipment->extraction_zip_file);
                Storage::delete($folderPath . '/' . str_replace('.zip', '.xlsx', $shipment->extraction_zip_file));
            }

            // 3. Recupero lista file ricorsiva (allFiles recupera anche sottocartelle)
            $allFiles = Storage::allFiles($folderPath);

            // Filtriamo solo i file .eml e normalizziamo i percorsi rispetto alla cartella base
            $receipts = [];
            foreach ($allFiles as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'eml') {
                    // S3 allFiles restituisce il path intero, calcoliamo il relativo rispetto a $folderPath
                    $relativeToFolder = ltrim(str_replace($folderPath, '', $file), '/');
                    $receipts[] = $relativeToFolder;
                }
            }

            $header = ["Descrizione", "Comune", "Indirizzo Mail", "Tipo", "Accettazione", "File Acc.", "Consegna", "File Cons.", "Anomalia", "File An."];
            $dataExcel = [];
            $toZip = []; // Array associativo: [ 'percorso/nello/zip' => 'percorso/fisico/locale' ]

            // 4. Elaborazione destinatari e download file
            foreach ($recipients as $row) {
                $input = (string)$row->object_ref;
                if (empty($input)) continue;

                // Cerchiamo i file che contengono il REF nel nome (anche nelle sottocartelle)
                $result = array_filter($receipts, fn($r) => stripos($r, $input) !== false);

                $recSend = $recSendFile = $recDeliver = $recDeliverFile = $recAnomaly = $recAnomalyFile = '';

                foreach ($result as $relativePath) {
                    $s3FilePath = $folderPath . '/' . $relativePath;
                    $localFilePath = $tempDir . '/' . $relativePath;

                    // Crea sottocartelle locali se il file è in una sottocartella S3
                    $localSubDir = dirname($localFilePath);
                    if (!is_dir($localSubDir)) {
                        mkdir($localSubDir, 0755, true);
                    }

                    // Download da S3 a locale
                    if (Storage::exists($s3FilePath)) {
                        file_put_contents($localFilePath, Storage::get($s3FilePath));
                        $toZip[$relativePath] = $localFilePath;
                    }

                    // Parsing del tipo ricevuta dal nome file
                    $nameOnly = pathinfo($relativePath, PATHINFO_FILENAME);
                    $refs = explode('_', $nameOnly);
                    if (count($refs) >= 4) {
                        $recType = str_replace('-', ' ', $refs[3]);
                        match (strtoupper($recType)) {
                            'ACCETTAZIONE', 'AVVISO DI MANCATA ACCETTAZIONE' => [$recSend, $recSendFile] = [$recType, $relativePath],
                            'CONSEGNA', 'AVVISO DI MANCATA CONSEGNA' => [$recDeliver, $recDeliverFile] = [$recType, $relativePath],
                            'ANOMALIA MESSAGGIO' => [$recAnomaly, $recAnomalyFile] = [$recType, $relativePath],
                            default => null,
                        };
                    }
                }

                $dataExcel[] = [
                    $row->description,
                    City::find($row->city_id)->name ?? '',
                    $row->address,
                    MailType::tryFrom($row->mail_type)?->getLabel() ?? $row->mail_type,
                    $recSend, $recSendFile,
                    $recDeliver, $recDeliverFile,
                    $recAnomaly, $recAnomalyFile,
                ];
            }

            // 5. Generazione Excel
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray($header, null, 'A1');
            $sheet->fromArray($dataExcel, null, 'A2');
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $xlsLocalPath = $tempDir . '/' . $xlsFilename;
            $writer->save($xlsLocalPath);

            // 6. Generazione CSV
            $csvFilename = str_replace('.xlsx', '.csv', $xlsFilename);
            $csvLocalPath = $tempDir . '/' . $csvFilename;
            $csvFile = fopen($csvLocalPath, 'w');

            // Scrittura BOM per compatibilità Excel (UTF-8)
            fprintf($csvFile, chr(0xEF).chr(0xBB).chr(0xBF));

            // Scrittura Header
            fputcsv($csvFile, $header, ';'); // Usiamo il punto e virgola, standard italiano per Excel

            // Scrittura Dati
            foreach ($dataExcel as $row) {
                fputcsv($csvFile, $row, ';');
            }
            fclose($csvFile);

            $toZip[$xlsFilename] = $xlsLocalPath;
            $toZip[$csvFilename] = $csvLocalPath;

            // 6. Aggiunta allegato originale della spedizione
            if ($shipment->attachment) {
                $attachmentS3Path = $folderPath . '/' . $shipment->attachment;
                if (Storage::exists($attachmentS3Path)) {
                    $attachmentLocalPath = $tempDir . '/' . $shipment->attachment;
                    file_put_contents($attachmentLocalPath, Storage::get($attachmentS3Path));
                    $toZip[$shipment->attachment] = $attachmentLocalPath;
                }
            }

            // 7. Creazione ZIP preservando la struttura
            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \Exception("Impossibile creare lo ZIP locale.");
            }

            foreach ($toZip as $zipInternalPath => $fullLocalPath) {
                if (file_exists($fullLocalPath)) {
                    $zip->addFile($fullLocalPath, $zipInternalPath);
                }
            }
            $zip->close();

            // 8. Upload ZIP finale su S3
            $s3ZipPath = $folderPath . '/' . $zipFilename;
            Storage::put($s3ZipPath, fopen($zipPath, 'r+'));

            // 9. Aggiornamento Database
            $shipment->update([
                'extraction_date' => now(),
                'extraction_zip_file' => $zipFilename
            ]);

            DB::commit();

            // Pulizia cartella temporanea locale
            $this->cleanupTempDir($tempDir);

            \Filament\Notifications\Notification::make()
                ->success()
                ->title('Estrazione completata')
                ->body("File generato: {$zipFilename}")
                ->send();

        } catch (\Exception $e) {
            DB::rollBack();
            if ($tempDir) $this->cleanupTempDir($tempDir);

            Log::error("Estrazione fallita [ID: {$id}]: " . $e->getMessage());
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('Errore estrazione')
                ->body($e->getMessage())
                ->send();
        }
    }

    private function cleanupTempDirOld($tempDir)
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

    private function cleanupTempDir($tempDir)
    {
        if (!is_dir($tempDir)) return;

        // Usiamo un iteratore ricorsivo per assicurarci di eliminare tutto
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tempDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            @$todo($fileinfo->getRealPath());
        }

        @rmdir($tempDir);
    }

    private function readZip($id, $filename)
    {
        try {
            $zipRelativePath = ltrim("shipments/{$id}/{$filename}", '/');

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

    private static function registerShipment($record, $scopeTypeId)
    {
        try {
            DB::beginTransaction();

            $oldPath = $record->shipment_path;
            $protocolNumber = static::newProtocol();

            $newPath = 'registry/' . $protocolNumber;

            $registry = Registry::create([
                'protocol_number' => $protocolNumber,
                'flow_type' => 'issued',
                'flow_index' => static::newIndex('issued'),
                'registry_origin_type' => 'shipment',
                'is_email' => true,
                'scope_type_id' => $scopeTypeId,
                'uid' => '#shipment' . $record->id,
                'message_id' => now()->format('Y-m-d_H-i-s') . '_' . $record->id,
                'from' => $record->sender->public_name,
                'subject' => $record->mail_object,
                'body' => $record->mail_body,
                'receive_date' => null,
                'account_id' => null,
                'send_date' => $record->send_date,
                'send_user_id' => $record->send_user_id,
                'shipment_id' => $record->id,
                'attachment_path' => $newPath,
                'download_date' => null,
                'download_user_id' => null,
                'register_user_id' => Auth::user()->id,
            ]);

            // Elimino la spedizione
            // Model::withoutEvents(function () use ($record) {
            //     $record->delete();
            // });

            $disk = config('filesystems.default');
            $storage = Storage::disk($disk);

            // copio cartella allegati
            if ($oldPath && $storage->exists($oldPath)) {
                // Storage::disk($disk)->makeDirectory($newPath);

                if (!$storage->exists($newPath)) {
                    $storage->makeDirectory($newPath);
                }
                $files = collect($storage->files($oldPath))
                    ->filter(function ($path) {
                        return Str::contains($path, 'estrazione');
                    })
                    ->all();

                // foreach ($files as $file) {
                //     $relativePath = str_replace($oldPath . '/', '', $file);
                //     $newFilePath = $newPath . '/' . today()->format('d-m-Y') . '_' . $registry->protocol_number . '_INV_' . $relativePath;

                //     $directory = dirname($newFilePath);
                //     if (!Storage::disk($disk)->exists($directory)) {
                //         Storage::disk($disk)->makeDirectory($directory);
                //     }

                //     Storage::disk($disk)->put($newFilePath, Storage::disk($disk)->get($file));
                // }

                foreach ($files as $file) {
                    $fileName = basename($file);
                    $newFileName = today()->format('d-m-Y') . '_' . $registry->protocol_number . '_INV_' . $fileName;
                    $finalPath = $newPath . '/' . $newFileName;

                    try {
                        // Usiamo lo Stream per bypassare i limiti del comando COPY di S3
                        $stream = $storage->readStream($file);

                        if ($stream === null) {
                            throw new \Exception("Impossibile leggere il file sorgente: $file");
                        }

                        // Scriviamo il file nella nuova posizione
                        // Il terzo parametro 'visibility' assicura che il nuovo file sia scrivibile
                        $result = $storage->writeStream($finalPath, $stream, [
                            'visibility' => 'private'
                        ]);

                        if (is_resource($stream)) {
                            fclose($stream);
                        }

                        if (!$result) {
                            throw new \Exception("Scrittura fallita per: $finalPath");
                        }

                        Log::info("File copiato con successo: $finalPath");

                    } catch (\Exception $e) {
                        Log::error("Errore durante la copia stream: " . $e->getMessage());
                        // Fallback estremo se lo stream fallisce (usa più RAM)
                        $storage->put($finalPath, $storage->get($file));
                    }
                }
            } else {
                Log::warning("Percorso non trovato o vuoto: " . ($oldPath ?? 'NULL'));
            }

            // Elimino la mail in uscita
            // Model::withoutEvents(function () use ($record) {
                // $record->delete();
            // });
            // Elimina solo se hai effettivamente trovato dei file o se vuoi pulire comunque
            // $storage->deleteDirectory($oldPath);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Errore scarico email: " . $e->getMessage() . ' - ' . $e->getLine());
            throw $e;
        }
    }

    private static function newProtocol(): string
    {
        $lastRegistry = Registry::orderBy('created_at', 'desc')->first();

        if ($lastRegistry) {
            $parts = explode('-', $lastRegistry->protocol_number);

            if (count($parts) !== 3 || $parts[0] !== 'P') {
                return 'P-' . today()->year . '-00001';
            }

            $lastYear = (int) $parts[1];
            $lastNumber = (int) $parts[2];
            $currentYear = today()->year;

            if ($lastYear === $currentYear) {
                $newNumber = $lastNumber + 1;
                return 'P-' . $currentYear . '-' . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
            } else {
                return 'P-' . $currentYear . '-00001';
            }
        }
        return 'P-' . today()->year . '-00001';
    }

    private static function newIndex($flow_type): int
    {
        $lastIndex = Registry::where('flow_type', $flow_type)->max('flow_index');
        if ($lastIndex) {
            $newIndex = $lastIndex+1;
            return $newIndex;
        }
        return 1;
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
