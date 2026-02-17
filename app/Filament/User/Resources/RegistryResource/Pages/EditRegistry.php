<?php

namespace App\Filament\User\Resources\RegistryResource\Pages;

use App\Enums\FlowType;
use App\Enums\PecStatus;
use App\Enums\RegistryOriginType;
use App\Filament\User\Resources\RegistryResource;
use App\Models\Account;
use App\Models\Registry;
use App\Models\RegistryReceiver;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EditRegistry extends EditRecord
{
    protected static string $resource = RegistryResource::class;

    public function getTitle(): string | Htmlable
    {
        // return $this->record->subject;
        return "Modifica voce protocollo";
    }

    protected function getHeaderActions(): array
    {
        $currentRegistry = $this->record;
        $previousNRegistry = Registry::where('protocol_number', '<', $currentRegistry->protocol_number)->orderBy('protocol_number', 'desc')->first();
        $nextNRegistry = Registry::where('protocol_number', '>', $currentRegistry->protocol_number)->orderBy('protocol_number', 'asc')->first();
        $previousIRegistry = Registry::where('flow_type', $currentRegistry->flow_type)->where('flow_index', '<', $currentRegistry->flow_index)->orderBy('flow_index', 'desc')->first();
        $nextIRegistry = Registry::where('flow_type', $currentRegistry->flow_type)->where('flow_index', '>', $currentRegistry->flow_index)->orderBy('flow_index', 'asc')->first();
        return [
            // Actions\DeleteAction::make(),
            // Scorrimento protocollo
            Actions\Action::make('previous_n_registry')
                ->label('Protocollo')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousNRegistry) { return $previousNRegistry;})
                ->action(function () use ($previousNRegistry) {
                    $this->redirect(RegistryResource::getUrl('edit', ['record' => $previousNRegistry->id]));
                }),
            Actions\Action::make('next_n_registry')
                ->label('Protocollo')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextNRegistry) { return $nextNRegistry;})
                ->action(function () use ($nextNRegistry) {
                    $this->redirect(RegistryResource::getUrl('edit', ['record' => $nextNRegistry->id]));
                }),
            // Scorrimento tipo flusso
            Actions\Action::make('previous_i_registry')
                ->label('Flusso')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousIRegistry) { return $previousIRegistry;})
                ->action(function () use ($previousIRegistry) {
                    $this->redirect(RegistryResource::getUrl('edit', ['record' => $previousIRegistry->id]));
                }),
            Actions\Action::make('next_i_registry')
                ->label('Flusso')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextIRegistry) { return $nextIRegistry;})
                ->action(function () use ($nextIRegistry) {
                    $this->redirect(RegistryResource::getUrl('edit', ['record' => $nextIRegistry->id]));
                }),
            Actions\ActionGroup::make([
                Action::make('uploadFile')
                    ->label('Carica File')
                    ->icon('heroicon-o-document-arrow-up')
                    ->visible(fn($record) => $record->flow_type == FlowType::INTERNAL )
                    ->color('info')
                    ->modalSubmitActionLabel('Carica')
                    // ->visible(fn($record) => !$record->is_email)
                    ->form([
                        FileUpload::make('attachments')
                            ->label('Seleziona File')
                            ->multiple()
                            ->directory(fn ($record) => $record->attachment_path)
                            // ->preserveFilenames()
                            ->getUploadedFileNameForStorageUsing(function ($file, $record) {
                                $disk = config('filesystems.default');
                                $directory = $record->attachment_path;

                                $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                                $extension = $file->getClientOriginalExtension();

                                // Creiamo la base del nome che NON deve cambiare nel loop
                                $prefix = today()->format('d-m-Y') . '_' . $record->protocol_number . '_INT_';

                                // Nome iniziale
                                $finalName = $prefix . $filename . '.' . $extension;
                                $counter = 1;

                                // Finchè esiste un file con questo nome nella directory di destinazione
                                while (Storage::disk($disk)->exists($directory . '/' . $finalName)) {
                                    // Applichiamo il counter mantenendo il prefisso richiesto
                                    $finalName = $prefix . $filename . '_' . $counter . '.' . $extension;
                                    $counter++;
                                }

                                return $finalName;
                            })
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        // I file vengono caricati automaticamente nella cartella
                        // configurata nel metodo ->directory() sopra.

                        Notification::make()
                            ->title('Caricamento completato')
                            ->success()
                            ->send();
                    }),

                Action::make('deleteFile')
                    ->label('Elimina File')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn($record) => $record && $record->attachment_path && !empty(Storage::files($record->attachment_path)) && $record->flow_type == FlowType::INTERNAL)
                    ->form([
                        Select::make('file_to_delete')
                            ->label('Seleziona il file da eliminare')
                            ->options(function ($record) {
                                if (!$record || !$record->attachment_path) {
                                    return [];
                                }

                                $files = Storage::files($record->attachment_path);

                                return collect($files)->mapWithKeys(function ($file) {
                                    return [$file => basename($file)];
                                })->toArray();
                            })
                            ->required()
                            ->native(false)
                            ->searchable(),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Elimina allegato')
                    ->modalDescription('Questa azione non può essere annullata.')
                    ->modalSubmitActionLabel('Elimina')
                    ->modalCancelActionLabel('Annulla')
                    ->action(function (array $data) {
                        $file = $data['file_to_delete'];

                        if (Storage::exists($file)) {
                            Storage::delete($file);

                            Notification::make()
                                ->title('File eliminato con successo')
                                ->body('Il file ' . basename($file) . ' è stato eliminato.')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('File non trovato')
                                ->warning()
                                ->send();
                        }
                    }),

            Actions\Action::make('send')
                ->label('Invia Email')
                ->icon('hugeicons-mail-send-01')
                ->color('success')
                ->visible(fn($record) =>
                    $record->registry_origin_type == RegistryOriginType::SEND_EMAIL
                    && !$record->send_date
                    && $record->account_id
                    // && !empty($record->recipients)
                    && $record->registryReceivers
                )
                ->requiresConfirmation()
                ->modalHeading('Conferma invio email')
                ->modalDescription(function ($record) {
                    $count = count($record->recipients ?? []);
                    return "L'email sarà inviata in background a {$count} destinatari. Riceverai una notifica al termine.";
                })
                ->modalSubmitActionLabel('Sì, invia')
                ->modalCancelActionLabel('Annulla')
                ->action(function ($record) {
                    try {
                        \App\Jobs\ProcessRegistryEmailJob::dispatch(
                            registryId: $record->id,
                            userId: Auth::id(),
                        );

                        Notification::make()
                            ->title('Invio avviato')
                            ->body("L'email del protocollo {$record->protocol_number} sarà inviata in background.")
                            ->success()
                            ->duration(5000)
                            ->send();

                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Errore avvio invio')
                            ->body('Impossibile avviare l\'invio: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('receipts')
                ->label('Controlla ricevute')
                ->icon('hugeicons-mail-receive-01')
                ->color('primary')
                ->visible(function($record) {
                        $pending = $record->pendingReceipts();
                        return $record->registry_origin_type == RegistryOriginType::SEND_EMAIL                      // è una email in uscita
                                && $record->send_date                                                               // è stata inviata
                                && $record->account_id                                                              // ha un mittente
                                && $record->registryReceivers                                                       // ha dei destinatari
                                && !$pending;                                                                       // non ci sono destinatari senza ricevute
                    }
                )
                ->requiresConfirmation()
                ->modalHeading('Conferma scarico ricevute')
                ->modalDescription(function ($record) {
                    return "Sarà avviato lo scarico delle ricevute per delle email inviate della voce del protocollo " . $record->protocol_number . ".";
                })
                ->modalSubmitActionLabel('Scarica')
                ->modalCancelActionLabel('Annulla')
                ->action(function ($record) {
                    try {
                            $this->downloadReceipts($record);

                            Notification::make()
                                ->title('Ricevute scaricate')
                                ->body('Tutte le ricevute sono state elaborate con successo.')
                                ->success()
                                ->send();

                            $this->dispatch('refreshRelationManager');

                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Errore elaborazione ricevute')
                            ->body('Impossibile avviare l\'elaborazione: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('uploadReceipts')
                ->label('Carica Ricevute')
                ->visible(fn() => $this->getRecord()->registry_origin_type === RegistryOriginType::SEND_EMAIL)
                ->icon('fluentui-receipt-20-o')
                ->color('info')
                ->modalSubmitActionLabel('Carica')
                // ->visible(fn($record) => !$record->is_email)
                ->form([
                    FileUpload::make('receipts')
                        ->label('Seleziona File')
                        ->multiple()
                        ->directory(fn () => $this->getRecord()->attachment_path . '/receipts')
                        ->preserveFilenames()
                        ->required(),
                ])
                ->action(function (array $data) {
                    Notification::make()
                        ->title('Caricamento completato')
                        ->success()
                        ->send();
                }),
            Action::make('uploadRelated')
                ->label('Carica documenti')
                ->visible(fn() => $this->getRecord()->registry_origin_type !== RegistryOriginType::SHIPMENT)
                ->icon('fluentui-document-link-20-o')
                ->color('info')
                ->modalSubmitActionLabel('Carica')
                ->form([
                    FileUpload::make('receipts')
                        ->label('Seleziona File')
                        ->multiple()
                        ->directory(fn () => $this->getRecord()->attachment_path . '/related')
                        ->preserveFilenames()
                        ->required(),
                ])
                ->action(function (array $data) {
                    Notification::make()
                        ->title('Caricamento completato')
                        ->success()
                        ->send();
                }),
            ])
            ->label('Operazioni')
            ->icon('heroicon-m-ellipsis-vertical')
            ->color('info')
            ->button(),

            // Action::make('deleteFile')
            //     ->label('Elimina file')
            //     ->icon('heroicon-o-trash')
            //     ->color('danger')
            //     ->visible(fn($record) => $record && $record->attachment_path && !empty(Storage::files($record->attachment_path)))
            //     ->form([
            //         Select::make('file_to_delete')
            //             ->label('Seleziona il file da eliminare')
            //             ->options(function ($record) {
            //                 if (!$record || !$record->attachment_path) {
            //                     return [];
            //                 }

            //                 $files = Storage::allfiles($record->attachment_path);

            //                 return collect($files)->mapWithKeys(function ($file) {
            //                     return [$file => basename($file)];
            //                 })->toArray();
            //             })
            //             ->required()
            //             ->native(false)
            //             ->searchable(),
            //     ])
            //     ->requiresConfirmation()
            //     ->modalHeading('Elimina allegato')
            //     ->modalDescription('Questa azione non può essere annullata.')
            //     ->modalSubmitActionLabel('Elimina')
            //     ->modalCancelActionLabel('Annulla')
            //     ->action(function (array $data) {
            //         $file = $data['file_to_delete'];

            //         if (Storage::exists($file)) {
            //             Storage::delete($file);

            //             Notification::make()
            //                 ->title('File eliminato con successo')
            //                 ->body('Il file ' . basename($file) . ' è stato eliminato.')
            //                 ->success()
            //                 ->send();
            //         } else {
            //             Notification::make()
            //                 ->title('File non trovato')
            //                 ->warning()
            //                 ->send();
            //         }
            //     }),
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
                ->modalHeading('Conferma eliminazione voce protocollo')
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
                return RegistryResource::getUrl('index');
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

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function downloadReceipts($registry)
    {
        set_time_limit(120);
        ini_set('max_execution_time', 120);

        try {
            DB::beginTransaction();

            $sender = Account::findOrFail($registry->account_id);

            $receivers = RegistryReceiver::where('registry_id', $registry->id)
                ->get();

            $receiptsPath = $this->ensureReceiptsPath($registry->protocol_number);

            Log::info("------------------------------------------------------------------------");
            Log::info("Inizio recupero ricevute PEC per voce protocollo {$registry->protocol_number}");

            $imap = $this->connectToMail($sender);
            if (!$imap) {
                throw new \Exception("Errore IMAP: " . implode(', ', imap_errors()));
            }
            Log::info("IMAP collegata con successo.");

            foreach ($receivers as $receiver) {
                Log::info("Elaborazione: {$registry->subject} → {$receiver->address}");

                $subject = "[{$registry->protocol_number}] " . $registry->subject;
                $this->processPecReceipts($imap, $sender, $receiver, $subject, $receiptsPath);
                $receiver->save();
            }

            Log::info("Ricevute elaborate ------------------------------------------------------");

            DB::commit();

            imap_expunge($imap);
            imap_close($imap);

        } catch (\Exception $e) {
            DB::rollBack();
            imap_close($imap);
            throw $e;
        }
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

        $imap = imap_open($mailbox, $sender->username, decrypt($sender->password), 0, 1);

        if ($imap === false) {
            Log::error("IMAP fallita: " . implode(', ', imap_errors()));
            return false;
        }

        return $imap;
    }

    private function processPecReceipts($imap, $account, &$receiver, $subject, $receiptsPath)
    {
        imap_errors();
        $searchCriteria = 'SUBJECT "' . $subject . '"';

        Log::info("Ricerca: {$searchCriteria} → {$receiver->address}");

        $uids = imap_search($imap, $searchCriteria, SE_UID);
        imap_errors();

        if (!$uids) { return; }

        foreach ($uids as $uid) {
            // $headerInfo = imap_headerinfo($imap, imap_msgno($imap, $uid));
            $overview = imap_fetch_overview($imap, $uid, 0);
            $date = $overview[0]->udate;
            $rawHeaders = imap_fetchheader($imap, $uid, FT_UID);
            $body = imap_body($imap, $uid, FT_UID);

            // Recuperiamo gli header per fare il controllo noi "a mano"
            // $rawHeaders = imap_fetchheader($imap, $uid, FT_UID);

            if (!$this->isOfficialPecReceipt($rawHeaders)) continue;Log::info("Ricevuta");
            if($receiver->message_id){
                if (!$this->isRightReceiptId($rawHeaders, $receiver->message_id)) continue;Log::info("Destinatario corretto (message_id)");
            } else {
                if (!$this->isRightReceipt($body, $receiver->address)) continue;Log::info("Destinatario corretto (address)");
            }

            $type = $this->getReceiptInfo($rawHeaders, imap_headerinfo($imap, imap_msgno($imap, $uid))->subject ?? '');
            if (!$type) continue;Log::info("Tipo: {$type}");

            // Salva sempre il file fisicamente
            $this->saveReceiptFile($receiptsPath, $receiver->address, $type, $body);

            $oldStatus = $receiver->pec_status;

            // --- LOGICA DI AGGIORNAMENTO STATO ---

            // 1. ANOMALIE (Sempre prioritarie)
            if ($type === "ANOMALIA MESSAGGIO") {
                $receiver->pec_status = PecStatus::ANOMALY;
            }

            // 2. ACCETTAZIONE (Solo se siamo ancora in attesa)
            elseif ($type === "ACCETTAZIONE" && $receiver->pec_status === PecStatus::WAITING) {
                $receiver->pec_status = PecStatus::ACCEPTED;
            }
            elseif ($type === "AVVISO DI MANCATA ACCETTAZIONE") {
                $receiver->pec_status = PecStatus::NOT_ACCEPTED;
            }

            // 3. CONSEGNA (Sovrascrive WAITING o ACCEPTED)
            elseif ($type === "CONSEGNA") {
                // La consegna è lo stato finale "positivo", lo impostiamo sempre se non c'è un errore
                $receiver->pec_status = PecStatus::DELIVERED;
            }
            elseif ($type === "AVVISO DI MANCATA CONSEGNA") {
                $receiver->pec_status = PecStatus::NOT_DELIVERED;
            }

            // Salva solo se lo stato è effettivamente cambiato per ottimizzare le query
            if ($oldStatus !== $receiver->pec_status) {
                $receiver->save();
                Log::info("Stato aggiornato per {$receiver->address}: {$oldStatus->getLabel()} -> {$receiver->pec_status->getLabel()}");
            }

            // Eliminazione ricevuta
            if ($account->delete && $date) {                                                            // se è prevista la cancellazione dal server
                if ($account->delete_after_days && $date){
                    $deleteDate = now()->subDays($account->delete_after_days)->startOfDay();
                    if (\Carbon\Carbon::parse($date)->lt($deleteDate)) {                                // se ho indicato i giorni da aspettare per cancellare
                        imap_delete($imap, $uid, FT_UID);
                    }
                }
                else{                                                                                   // se non ho indicato i giorni da aspettare per cancellare
                    imap_delete($imap, $uid, FT_UID);
                }
            }

        }
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

    private function isRightReceiptId($rawHeaders, $message_id)
    {
        $normalizedId = trim($message_id, '<> ');
        $pattern = '/^X-Riferimento-Message-ID:\s*<?' . preg_quote($normalizedId, '/') . '>?/mi';

        return (bool) preg_match($pattern, $rawHeaders);
    }

    private function isRightReceipt($body, $address)
    {
        // 1. Normalizzazione: portiamo tutto in minuscolo
        $address = strtolower(trim($address));
        $loweredBody = strtolower($body);

        // 2. Controllo semplice (funziona per Accettazione e testi piani)
        if (str_contains($loweredBody, $address)) {
            return true;
        }

        // 3. Controllo per codifica Quoted-Printable (molto comune nelle Consegne)
        $quotedAddress = str_replace('@', '=40', $address);
        if (str_contains($loweredBody, $quotedAddress)) {
            return true;
        }

        // 4. Pulizia XML (per daticert.xml se presente nel body raw)
        // Estraiamo il contenuto tra <destinatario> e </destinatario>
        if (preg_match_all('/<destinatario[^>]*>(.*?)<\/destinatario>/s', $loweredBody, $matches)) {
            foreach ($matches[1] as $match) {
                if (trim($match) === $address) {
                    return true;
                }
            }
        }

        // 5. Fallback estremo: cerchiamo solo la prima parte dell'email prima della @
        // Se l'email è molto complessa o spezzata da invio a capo MIME
        $localPart = explode('@', $address)[0];
        if (str_contains($loweredBody, $localPart) && str_contains($loweredBody, 'consegna')) {
            // Loggiamo per sicurezza se usiamo il fallback
            Log::debug("isRightReceipt: Trovata corrispondenza parziale per {$address}");
            return true;
        }

        return false;
    }

    private function saveReceiptFile($receiptsPath, $receiverAddress, $receiptType, $body)
    {
        $filename = "{$receiverAddress}_" . str_replace(" ", "-", $receiptType) . ".eml";
        Storage::put($receiptsPath . '/' . $filename, $body);
    }

    private function getReceiptInfo($rawHeaders, $subjectHeader)
    {
        $type = null;

        // 1. Decodifica il subject (fondamentale per le accentate e i formati MIME)
        $decodedSubject = iconv_mime_decode($subjectHeader ?? '', 0, "UTF-8");

        // 2. Regex per estrarre il tipo dal Subject
        // La rendiamo più flessibile per catturare il tipo anche se seguito subito dalle quadre
        if (preg_match('/^(ACCETTAZIONE|CONSEGNA|AVVISO DI MANCATA (?:ACCETTAZIONE|CONSEGNA)|ANOMALIA MESSAGGIO):/i', $decodedSubject, $matches)) {
            $type = strtoupper($matches[1]);
        }

        // 3. Override specifico per Aruba (più affidabile)
        // Usiamo preg_match (singolo) invece di preg_match_all se ci serve solo la prima occorrenza
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

        return $type; // Restituisce la stringa (es. "ACCETTAZIONE") o null
    }
}
