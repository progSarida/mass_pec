<?php

namespace App\Filament\User\Resources\RegistryResource\Pages;

use App\Enums\FlowType;
use App\Enums\MailType;
use App\Enums\ManageRegistryType;
use App\Enums\PecStatus;
use App\Enums\RegistryOriginType;
use App\Enums\RelationshipType;
use App\Filament\User\Resources\ManualInsertResource;
use App\Filament\User\Resources\RegistryResource;
use App\Filament\User\Resources\SendEmailResource;
use App\Jobs\DownloadReceiptsJob;
use App\Models\Account;
use App\Models\Company;
use App\Models\ManualInsert;
use App\Models\Recipient;
use App\Models\RecipientEmail;
use App\Models\Registry;
use App\Models\RegistryReceiver;
use App\Models\ScopeType;
use App\Models\SendEmail;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;

class EditRegistry extends EditRecord
{
    protected static string $resource = RegistryResource::class;

    protected ?array $pendingReceiverKeys = null;

    public function getTitle(): string | Htmlable
    {
        return "Gestisci {$this->record->protocol_number}";
    }

    protected function getHeaderActions(): array
    {
        $currentRegistry = $this->record;
        $previousNRegistry = Registry::where('protocol_number', '<', $currentRegistry->protocol_number)->orderBy('protocol_number', 'desc')->first();
        $nextNRegistry = Registry::where('protocol_number', '>', $currentRegistry->protocol_number)->orderBy('protocol_number', 'asc')->first();
        $previousIRegistry = Registry::where('flow_type', $currentRegistry->flow_type)->where('flow_index', '<', $currentRegistry->flow_index)->orderBy('flow_index', 'desc')->first();
        $nextIRegistry = Registry::where('flow_type', $currentRegistry->flow_type)->where('flow_index', '>', $currentRegistry->flow_index)->orderBy('flow_index', 'asc')->first();

        return [
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
                ->label('Corrispondenza')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousIRegistry) { return $previousIRegistry;})
                ->action(function () use ($previousIRegistry) {
                    $this->redirect(RegistryResource::getUrl('edit', ['record' => $previousIRegistry->id]));
                }),
            Actions\Action::make('next_i_registry')
                ->label('Corrispondenza')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextIRegistry) { return $nextIRegistry;})
                ->action(function () use ($nextIRegistry) {
                    $this->redirect(RegistryResource::getUrl('edit', ['record' => $nextIRegistry->id]));
                }),
            Actions\ActionGroup::make([
                Actions\Action::make('changeScopeType')
                    ->label('Modifica settore interno')
                    ->icon('heroicon-o-building-office-2')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalWidth('xl')
                    ->modalHeading('Modifica settore interno')
                    ->modalSubmitActionLabel('Salva')
                    ->fillForm(fn (Registry $record): array => [
                        'scope_type_id' => $record->scope_type_id,
                    ])
                    ->form([
                        Select::make('scope_type_id')
                            ->label('Settore interno')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->options(
                                fn () => ScopeType::query()
                                    ->orderBy('position', 'asc')
                                    ->pluck('name', 'id')
                                    ->toArray()
                            ),
                    ])
                    ->action(function (Registry $record, array $data) {
                        $record->update([
                            'scope_type_id' => $data['scope_type_id'],
                        ]);

                        Notification::make()
                            ->title('Settore interno aggiornato')
                            ->success()
                            ->send();
                    }),
                Action::make('addFile')
                    ->label('Carica File')
                    ->icon('heroicon-o-document-arrow-up')
                    ->visible(fn($record) => $record->flow_type == FlowType::INTERNAL)
                    ->color('info')
                    ->modalSubmitActionLabel('Carica')
                    ->form([
                        FileUpload::make('attachments')
                            ->label('Seleziona File')
                            ->multiple()
                            // Manteniamo la tua logica di directory e nomi
                            ->directory(fn ($record) => $record->attachment_path)
                            ->getUploadedFileNameForStorageUsing(function ($file, $record) {
                                $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                                $extension = $file->getClientOriginalExtension();
                                // $prefix = today()->format('d-m-Y') . '_' . $record->protocol_number . '_INT_';
                                $protocol = explode('-', $record->protocol_number);
                                $protocolYear = $protocol[1] ?? 'XXXX';
                                $protocolCode = $protocol[2] ?? 'XXXXX';
                                $prefix = $protocolYear . '_' . $protocolCode . '_INT_';
                                $finalName = $prefix . $filename . '.' . $extension;

                                $disk = config('filesystems.default');
                                $counter = 1;
                                while (Storage::disk($disk)->exists($record->attachment_path . '/' . $finalName)) {
                                    $finalName = $prefix . $filename . '_' . $counter . '.' . $extension;
                                    $counter++;
                                }
                                return $finalName;
                            })
                            ->required(),
                    ])
                    ->action(function (array $data, $record) {
                        // TODO: disabilitata apposizione watermark 
                        // => studiare un modo per applicarlo senza perdere firma digitale
                        // => nella condizione usare il flag 'add_watermark' di Company per gestire da parametri il watermark una volta trovato il modo

                        // // 1. Recuperiamo il disco corretto
                        // $disk = config('filesystems.default');
                        // $storage = Storage::disk($disk);

                        // // 2. Otteniamo l'array dei file.
                        // // Attenzione: Filament a seconda della config potrebbe passarti un array o una stringa JSON
                        // $attachments = $data['attachments'] ?? [];

                        // if (!is_array($attachments)) {
                        //     $attachments = [$attachments];
                        // }

                        // foreach ($attachments as $filePath) {
                        //     // Pulizia percorso (alcuni driver aggiungono slash iniziali)
                        //     $filePath = ltrim($filePath, '/');

                        //     if (!$storage->exists($filePath)) {
                        //         Log::warning("File non trovato per watermark: " . $filePath);
                        //         continue;
                        //     }

                        //     $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

                        //     if ($extension === 'pdf') {
                        //         try {
                        //             // Leggiamo il contenuto
                        //             $pdfContent = $storage->get($filePath);

                        //             // Usiamo il numero di protocollo dal record
                        //             $protocolNumber = $record->protocol_number ?? 'N/A';

                        //             // Applichiamo il watermark
                        //             $watermarkedPdf = static::addProtocolWatermarkBottom($pdfContent, $protocolNumber, $record);

                        //             // Sovrascriviamo
                        //             $storage->put($filePath, $watermarkedPdf, [
                        //                 'visibility' => 'private',
                        //                 'ContentType' => 'application/pdf',
                        //             ]);

                        //             Log::info("Watermark applicato con successo a: " . $filePath);

                        //         } catch (\Exception $e) {
                        //             Log::error("Errore watermark su {$filePath}: " . $e->getMessage());
                        //         }
                        //     }
                        // }

                        // Fondamentale: Se l'azione deve salvare i percorsi nel database,
                        // assicurati che il record sia aggiornato se non lo fa Filament in automatico
                        // $record->attachments = array_merge($record->attachments ?? [], $attachments);
                        // $record->save();

                        Notification::make()
                            ->title('Caricamento completato')
                            ->body('I file sono stati caricati correttamente.')
                            ->success()
                            ->send();
                    }),

                Action::make('subFile')
                    ->label('Elimina file')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn($record) => $record->flow_type == FlowType::INTERNAL)
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
                        $record->isOutgoingEmail()
                        && !$record->send_date
                        && $record->account_id
                        && $record->registryReceivers->count() > 0
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Conferma invio email')
                    ->modalDescription(function ($record) {
                        $count = count($record->registryReceivers ?? []);
                        return "L'email sarà inviata in background a {$count} destinatari. Riceverai una notifica al termine dell'invio.";
                    })
                    ->modalSubmitActionLabel('Sì, invia')
                    ->modalCancelActionLabel('Annulla')
                    ->action(function ($record) {
                        try {
                            \App\Jobs\ProcessRegistryEmailJob::dispatch(
                                registryId: $record->id,
                                userId: Auth::id(),
                                autoDownloadReceipts: true,  // Download automatico ricevute
                                receiptsDelayMinutes: 5,     // Delay scarico ricevute
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
                            $allDone = $record->checkReceipts();
                            return $record->isOutgoingEmail()
                                    && $record->send_date
                                    && $record->account_id
                                    && $record->registryReceivers
                                    && !$allDone;
                        }
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Conferma scarico ricevute')
                    ->modalDescription(function ($record) {
                        return "Sarà avviato lo scarico in background delle ricevute per il protocollo " . $record->protocol_number . ".";
                    })
                    ->modalSubmitActionLabel('Scarica')
                    ->modalCancelActionLabel('Annulla')
                    ->action(function ($record) {
                        try {
                            // Dispatch job in background
                            DownloadReceiptsJob::dispatch($record->id, Auth::id());

                            Notification::make()
                                ->title('Download ricevute avviato')
                                ->body('Lo scarico delle ricevute è stato avviato in background. Riceverai una notifica al termine.')
                                ->success()
                                ->send();

                            $this->dispatch('refreshRelationManager');

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Errore avvio download ricevute')
                                ->body('Impossibile avviare il download: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // Per PEC inviate tramite webmail
                Action::make('uploadReceipts')
                    ->label('Carica Ricevute')
                    ->visible(function($record) {
                            $allDone = $record->checkReceipts();
                            return $record->isOutgoingEmail()
                                    && $record->send_date
                                    && $record->account_id
                                    && $record->registryReceivers
                                    && !$allDone;
                        }
                    )
                    ->icon('fluentui-receipt-20-o')
                    ->color('info')
                    ->modalSubmitActionLabel('Carica')
                    ->form([
                        FileUpload::make('receipts')
                            ->label('Seleziona File')
                            ->helperText('Formato nome file richiesto: <indirizzo>_<ACCETTAZIONE/AVVISO_DI_MANCATA_ACCETTAZIONE/CONSEGNA/AVVISO_DI_MANCATA_CONSEGNA>.eml (es. test@pec.it_ACCETTAZIONE.eml)')
                            ->multiple()
                            ->directory(fn () => $this->getRecord()->attachment_path . '/receipts')
                            ->preserveFilenames()
                            ->required(),
                    ])
                    ->action(function (array $data, $record, $livewire) {
                        $fileNames = collect($data['receipts'])->map(fn ($path) => basename($path))->toArray();

                        foreach ($record->registryReceivers as $receiver) {
                            if ($receiver->pec_status == PecStatus::DELIVERED) {
                                continue;
                            }

                            // Isolo solo i file che appartengono a questo receiver (match sull'indirizzo)
                            $receiverFiles = collect($fileNames)->filter(function ($name) use ($receiver) {
                                $addressPart = Str::beforeLast($name, '_');
                                return strcasecmp($addressPart, $receiver->address) === 0;
                            });

                            if ($receiverFiles->isEmpty()) {
                                continue;
                            }

                            foreach ($receiverFiles as $fileName) {
                                $fullPath = $record->attachment_path . '/receipts/' . $fileName;
                                $content = Storage::get($fullPath);
                                $decodedContent = static::decodeBody($content);

                                $referencedMessageId = static::extractReferencedMessageId($content);

                                if (!$receiver->message_id && $referencedMessageId && $referencedMessageId !== $record->message_id) {
                                    $receiver->update(['message_id' => $referencedMessageId]);
                                }

                                $hasConsegna = Str::contains($fileName, 'CONSEGNA', ignoreCase: true) 
                                    && !Str::contains($fileName, 'MANCATA', ignoreCase: true);
                                $hasAccettazione = Str::contains($fileName, 'ACCETTAZIONE', ignoreCase: true)
                                    && !Str::contains($fileName, 'MANCATA', ignoreCase: true);
                                $hasMancataConsegna = Str::contains($fileName, 'MANCATA') && Str::contains($fileName, 'CONSEGNA', ignoreCase: true);
                                $hasMancataAccettazione = Str::contains($fileName, 'MANCATA') && Str::contains($fileName, 'ACCETTAZIONE', ignoreCase: true);
                                $hasAnomalia = Str::contains($fileName, 'ANOMALIA', ignoreCase: true);

                                if ($hasConsegna) {
                                    $receiver->update(['pec_status' => PecStatus::DELIVERED]);
                                } elseif ($hasAccettazione) {
                                    $receiver->update(['pec_status' => PecStatus::ACCEPTED]);
                                } elseif ($hasMancataConsegna) {
                                    $receiver->update([
                                        'pec_status' => PecStatus::NOT_DELIVERED,
                                        'anomaly_description' => static::extractMotivazione($decodedContent),
                                    ]);
                                } elseif ($hasMancataAccettazione) {
                                    $receiver->update([
                                        'pec_status' => PecStatus::NOT_ACCEPTED,
                                        'anomaly_description' => static::extractMotivazione($decodedContent),
                                    ]);
                                } elseif ($hasAnomalia) {
                                    $receiver->update([
                                        'pec_status' => PecStatus::ANOMALY,
                                        'anomaly_description' => static::extractMotivazione($decodedContent),
                                    ]);
                                }
                            }
                        }                    

                        Notification::make()
                            ->title('Caricamento completato')
                            ->success()
                            ->send();

                        return redirect(request()->header('Referer'));
                    }),

                // Per posta ordinaria fisica
                Action::make('uploadPostaReceipts')
                    ->label('Carica Ricevute')
                    ->visible(function($record) {
                            $allDone = $record->checkReceipts();
                            return $record->isOutgoingPosta()
                                    && $record->registryReceivers
                                    && !$allDone;
                        }
                    )
                    ->icon('fluentui-receipt-20-o')
                    ->color('info')
                    ->modalSubmitActionLabel('Carica')
                    ->form([
                        FileUpload::make('receipts')
                            ->label('Seleziona File')
                            ->helperText('Prima di caricare la ricevuta aggiungere il destinatario indicato nell\'elenco al nome del file')
                            ->multiple()
                            ->directory(fn () => $this->getRecord()->attachment_path . '/receipts')
                            ->preserveFilenames()
                            ->required(),
                        Repeater::make('check_receiver')
                            ->label('Destinatari')
                            ->helperText('Seleziona a quali destinatari fanno riferimento le ricevute caricate')
                            ->schema([
                                Hidden::make('registry_receiver_id'),
                                Placeholder::make('recipient_label')
                                    ->label('')
                                    ->content(fn ($get) => $get('recipient_label')),
                                Checkbox::make('received')
                                    ->label('Ricevuta caricata / consegnato'),
                            ])
                            ->default(function ($record) {
                                return $record->registryReceivers
                                    ->map(fn ($receiver) => [
                                        'registry_receiver_id' => $receiver->id,
                                        'recipient_label' => $receiver->recipient->description,
                                        'received' => $receiver->pec_status === PecStatus::DELIVERED,
                                    ])
                                    ->toArray();
                            })
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columns(2),
                    ])
                    ->action(function (array $data, $record) {
                        // I file sono già stati salvati in {attachment_path}/receipts da FileUpload,
                        // qui aggiorniamo solo lo stato dei registryReceivers spuntati come consegnati

                        collect($data['check_receiver'] ?? [])
                            ->filter(fn ($row) => $row['received'] ?? false)
                            ->each(function ($row) {
                                RegistryReceiver::where('id', $row['registry_receiver_id'])
                                    ->where('pec_status', '!=', PecStatus::DELIVERED)
                                    ->update(['pec_status' => PecStatus::DELIVERED]);
                            });

                        Notification::make()
                            ->title('Ricevute caricate con successo')
                            ->success()
                            ->send();
                    }),

                Action::make('uploadFile')
                        ->label('Carica allegati')
                        ->icon('heroicon-o-document-arrow-up')
                        ->color('info')
                        ->modalSubmitActionLabel('Carica')
                        ->visible(function($record) {
                                return $record->isOutgoingEmail()
                                        && $record->attachment_path
                                        && Storage::exists($record->attachment_path)
                                        && !$record->send_date
                                        && $record->account_id
                                        && $record->registryReceivers->count() > 0;
                            }
                        )
                        ->form([
                            FileUpload::make('attachments')
                                ->label('Seleziona File')
                                ->multiple()
                                ->directory(fn ($record) => $record->attachment_path)
                                ->preserveFilenames()
                                ->getUploadedFileNameForStorageUsing(function ($file, $record) {
                                    $disk = config('filesystems.default');
                                    $directory = $record->attachment_path;

                                    $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                                    $extension = $file->getClientOriginalExtension();
                                    // $prefix = today()->format('d-m-Y') . '_' . $record->protocol_number . "_{$record->flow_type->getExt()}_";

                                    $protocol = explode('-', $record->protocol_number);
                                    $protocolYear = $protocol[1] ?? 'XXXX';
                                    $protocolCode = $protocol[2] ?? 'XXXXX';
                                    $prefix = $protocolYear . '_' . $protocolCode . "_{$record->flow_type->getExt()}_";
                                    $finalName = $prefix . $filename . '.' . $extension;
                                    $counter = 1;

                                    while (Storage::disk($disk)->exists($directory . '/' . $finalName)) {
                                        $finalName = $prefix . $filename . '_' . $counter . '.' . $extension;
                                        $counter++;
                                    }

                                    return $finalName;
                                })
                                ->required(),
                        ])
                        ->action(function (array $data) {
                            Notification::make()
                                ->title('Caricamento completato')
                                ->success()
                                ->send();
                        }),

                Action::make('deleteFile')
                    ->label('Elimina file')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(function($record) {
                            return $record->isOutgoingEmail()
                                    && $record->attachment_path
                                    && Storage::exists($record->attachment_path)
                                    && !empty(Storage::files($record->attachment_path))
                                    && !$record->send_date
                                    && $record->account_id
                                    && $record->registryReceivers;
                        }
                    )
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

                Action::make('uploadRelated')
                    ->label('Carica integrazioni')
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

                Action::make('deleteRelated')
                    ->label('Elimina integrazioni')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(function($record) {
                            $folder = $record->attachment_path . '/related';
                            return $record->isOutgoingEmail()
                                && $record->attachment_path
                                && Storage::exists($folder)
                                && !empty(Storage::files($folder))
                                && $record->send_date
                                && $record->account_id
                                && $record->registryReceivers;
                        }
                    )
                    ->form([
                        Select::make('file_to_delete')
                            ->label('Seleziona il file da eliminare')
                            ->options(function ($record) {
                                $folder = $record->attachment_path . '/related';
                                if (!$record || !$folder) {
                                    return [];
                                }

                                $files = Storage::files($folder);

                                return collect($files)->mapWithKeys(function ($file) {
                                    return [$file => basename($file)];
                                })->toArray();
                            })
                            ->required()
                            ->native(false)
                            ->searchable(),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Elimina file')
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

                Action::make('reply')
                    ->label('Rispondi')
                    ->visible(fn($record) => $record->isIngoingEmail() || ($record->flow_type == FlowType::RECEIVED && $record->registry_origin_type == RegistryOriginType::MANUAL))
                    ->icon('fluentui-arrow-reply-20-o')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Crea risposta')
                    ->modalDescription('Creare risposta a questa email?')
                    ->modalSubmitActionLabel('Crea')
                    ->modalCancelActionLabel('Annulla')
                    ->form(function($record){
                        $form[] =
                            Select::make('account_id')
                                ->label('Account')
                                ->required(fn(Get $get) => $get('manual') === false)
                                ->relationship(
                                    name: 'account',
                                    titleAttribute: 'public_name',
                                    modifyQueryUsing: fn ($query) => $query
                                        ->where('send', true)
                                        ->whereHas('users', fn ($q) => $q->where('users.id', Auth::user()->id))
                                        ->orderBy('position', 'asc')
                                )
                                ->preload();
                        if($record->flow_type == FlowType::RECEIVED && $record->registry_origin_type == RegistryOriginType::MANUAL){
                            $form[] = Checkbox::make('manual')
                                        ->label('Risposta con posta fisica')
                                        ->live()
                                        ->helperText('Se deselezionato, verrà creata una bozza di email; se selezionato, verrà creato un inserimento manuale. In entrambi i casi poi l\'elemento creato dovrà essere protocollato');
                        }
                        return $form;
                    })
                    ->action(function ($record, array $data) {
                        if($data['manual'] ?? false){
                            $this->replyManualInsert($record);                                                                                  // creo inserimento manuale di risposta, poi da protocollare
                        } else {
                            $this->replySendEmail($record, $data);                                                                              // creo bozza email di risposta, poi da protocollare
                        }
                        // $this->replyRegistry($record, $data);                                                                               // creo voce protocollo di risposta
                    }),

                Action::make('forward')
                    ->label('Inoltra')
                    ->visible(fn($record) => (($record->isOutgoingEmail() && $record->send_date) || $record->isIngoingEmail() ||
                                                ($record->registry_origin_type == RegistryOriginType::MANUAL && ($record->flow_type == FlowType::RECEIVED || $record->flow_type == FlowType::ISSUED))))
                    ->icon('fluentui-arrow-forward-20-o')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Inoltra email')
                    ->modalDescription('Creare copia in uscita di questa email?')
                    ->modalSubmitActionLabel('Crea')
                    ->modalCancelActionLabel('Annulla')
                    ->form(function($record){
                        $form[] =
                            Select::make('account_id')
                                ->label('Account')
                                ->required(fn(Get $get) => $get('manual') === false)
                                ->relationship(
                                    name: 'account',
                                    titleAttribute: 'public_name',
                                    modifyQueryUsing: fn ($query) => $query
                                        ->where('send', true)
                                        ->whereHas('users', fn ($q) => $q->where('users.id', Auth::user()->id))
                                        ->orderBy('position', 'asc')
                                )
                                ->preload();
                        if($record->flow_type == FlowType::RECEIVED && $record->registry_origin_type == RegistryOriginType::MANUAL){
                            $form[] = Checkbox::make('manual')
                                        ->label('Inoltro con posta fisica')
                                        ->live()
                                        ->helperText('Se deselezionato, verrà creata una bozza di email; se selezionato, verrà creato un inserimento manuale. In entrambi i casi poi l\'elemento creato dovrà essere protocollato');
                        }
                        return $form;
                    })
                    ->action(function ($record, array $data) {
                        if($data['manual'] ?? false){
                            $this->forwardManualInsert($record);                                                                                // creo inserimento manuale di inoltro, poi da protocollare
                        } else {
                            $this->forwardSendEmail($record, $data);                                                                            // creo bozza email di inoltro, poi da protocollare
                        }
                        // $this->forwardRegistry($record, $data);                                                                               // creo voce protocollo di risposta
                    }),

                Actions\Action::make('manage')
                    ->label('Aggiorna stato gestione')
                    ->icon('heroicon-o-cog-8-tooth')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalWidth('xl')
                    ->visible(fn($record) => $record?->manage_registry_type?->showManage())
                    ->fillForm(fn (Registry $record): array => [
                        'manage_registry_type' => $record?->manage_registry_type?->value,
                        'manage_registry_date' => now(),
                    ])
                    ->form([
                        Select::make('manage_registry_type')
                            ->label('Modifica stato gestione')
                            ->required()
                            // ->options(
                            //     collect(ManageRegistryType::cases())
                            //         ->filter(fn (ManageRegistryType $enum) => $enum->showToUpdate())
                            //         ->mapWithKeys(fn (ManageRegistryType $enum) => [
                            //             $enum->value => $enum->getLabel()
                            //         ])
                            // )
                            ->options(function (?Registry $record): array {
                                // Se c'è un record e ha un valore enum impostato, usa showOptions()
                                // Altrimenti, mostra tutti i casi come fallback
                                $allowedCases = $record?->manage_registry_type 
                                    ? $record->manage_registry_type->showOptions() 
                                    : ManageRegistryType::cases();

                                return collect($allowedCases)
                                    ->mapWithKeys(fn (ManageRegistryType $enum) => [
                                        $enum->value => $enum->getLabel(),
                                    ])
                                    ->toArray();
                            })
                            ->live(),
                        DatePicker::make('manage_registry_date')
                            ->label('Data evasione')
                            ->required()
                            ->visible(fn (Get $get) =>$get('manage_registry_type') == ManageRegistryType::DONE->value ),
                        Textarea::make('manage_registry_mode')
                            ->label('Descrizione delle modalità di evasione')
                            ->rows(3)
                            ->maxLength(65535)
                            ->visible(fn (Get $get) =>$get('manage_registry_type') == ManageRegistryType::DONE->value ),
                    ])
                    ->action(function (Registry $record, $data) {
                        $manageRegistryType = $data['manage_registry_type'] ?? null;
                        $manageRegistryDate = $data['manage_registry_date'] ?? null;
                        $registryManage = $record->registryManages()->create([
                            'manage_registry_type' => $manageRegistryType,
                            'manage_registry_date' => $manageRegistryDate,
                            'manage_registry_mode' => $data['manage_registry_mode'] ?? null,
                            'manage_registration_datetime' => now(),
                            'manage_registration_user_id' => Auth::id(),
                        ]);
                        $record->update([
                            'manage_registry_type' => $manageRegistryType,
                            'manage_registry_date' => $manageRegistryDate,
                        ]);
                    }),
                Actions\Action::make('print')
                    ->icon('heroicon-o-printer')
                    ->label('Stampa')
                    ->tooltip('Stampa')
                    ->color('print')
                    ->action(function ($record) {
                        Notification::make()
                            ->title('Stampa avviata')
                            ->success()
                            ->send();

                        return response()
                            ->streamDownload(function () use ($record) {
                                echo Pdf::loadHTML(
                                    Blade::render('print.registry', [
                                        'company' => Company::first(),
                                        'registry' => $record,
                                    ])
                                )
                                ->setPaper('A4', 'portrait')
                                ->stream();
                            }, "{$record->protocol_number}_contenuto_protocollo.pdf");
                    }),
                // Actions\Action::make('link')
                //     ->label('Collega a voce')
                //     ->icon('fluentui-document-text-link-20-o')
                //     ->color('primary')
                //     ->visible(fn($record) => (Auth::user()->hasRole('super_admin') || Auth::user()->hasRole('manager')))
                //     ->requiresConfirmation()
                //     ->modalHeading('Collega')
                //     // ->modalDescription('')
                //     ->modalSubmitActionLabel('Protocolla')
                //     ->form([
                //         Select::make('parent_id')
                //             ->label('Voce da collegare')
                //             ->searchable()
                //             ->placeholder('Seleziona la voce da collegare')
                //             ->getSearchResultsUsing(function (string $search) {
                //                 $query = Registry::whereNotNull('sender_id');
                //                 $query = Registry::whereRaw('1 = 1');

                //                 if (is_numeric($search)) {
                //                     // Trasforma "125" in "00125"
                //                     $searchPadded = str_pad($search, 5, '0', STR_PAD_LEFT);

                //                     $query->where(function ($q) use ($searchPadded, $search) {
                //                         // Cerca esattamente il finale "-00125"
                //                         // $q->where('protocol_number', 'like', "%-{$searchPadded}");
                //                         $q->where('protocol_number', 'like', "%{$search}%");
                //                     });
                //                 } else {
                //                     $query->where('protocol_number', 'like', "%{$search}%");
                //                 }

                //                 return $query->limit(50)
                //                     ->get()
                //                     ->mapWithKeys(fn ($registry) => [
                //                         $registry->id => $registry->protocol_number . ' - ' . $registry->flow_type->getLabel()
                //                     ]);
                //             })
                //             ->getOptionLabelUsing(function ($value): ?string {
                //                 $registry = Registry::find($value);
                //                 return $registry ? $registry->protocol_number . ' - ' . $registry->flow_type->getLabel() : null;
                //             }),
                //     ])
                //     ->action(function ($record, $data) {
                //         try {

                //             static::registerEmail($record, $data);
                //             Notification::make()
                //                 ->title('Voce collegata')
                //                 // ->body('')
                //                 ->success()
                //                 ->send();
                //             $resource = $this->getResource();
                //             return $this->redirect($resource::getUrl('index'));
                //         } catch (\Exception $e) {
                //             Notification::make()
                //                 ->title('Errore registrazione')
                //                 ->body($e->getMessage())
                //                 ->danger()
                //                 ->send();
                //         }
                //     }),

                Actions\Action::make('void')
                    ->label('Annulla voce')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Annulla voce')
                    ->modalDescription(function ($record) {
                        return "Annullare in data di oggi " . today()->format('d/m/Y') . " la voce {$record->protocol_number}?";
                    })
                    ->modalSubmitActionLabel('Conferma')
                    ->modalCancelActionLabel('Annulla')
                    ->visible(fn($record) => !$record?->void)
                    ->form([
                        TextInput::make('void_reason')
                            ->label('Motivo annullamento'),
                    ])
                    ->action(function (Registry $record, $data) {
                        $voidReason = $data['void_reason'] ?? null;
                        $record->update([
                            'void' => true,
                            'void_date' => today(),
                            'void_reason' => $voidReason,
                        ]);
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
            $this->getSaveFormAction()
                ->visible(function ($record, $livewire) {
                    // Mostro se comunicazione in uscita non ancora inviata
                    if($record->isOutgoingEmail() && !$record->send_date) {
                        return true;
                    }
                    return false;
                })
                ->color('success'),
            $this->getCancelFormAction(),
            $this->getResetFormAction(),
            // $this->getDeleteFormAction()
            //     ->extraAttributes([
            //         'class' => ' md:ml-auto md:w-auto ',
            //     ]),
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

    private static function getRecipientId($from)
    {
        $recipient = Recipient::findByEmail($from);
        return $recipient?->id;
    }

    private static function addProtocolWatermarkBottom(string $pdfContent, string $protocolNumber, $record): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'pdf_wm');
        file_put_contents($tempFile, $pdfContent);

        // Utilizziamo il namespace corretto per FPDI
        $pdf = new Fpdi();

        try {
            $pageCount = $pdf->setSourceFile($tempFile);

            $pdf->SetAutoPageBreak(false);

            for ($n = 1; $n <= $pageCount; $n++) {
                // 1. Importiamo la pagina n
                $tplIdx = $pdf->importPage($n);
                $specs = $pdf->getImportedPageSize($tplIdx);

                // 2. Aggiungiamo la pagina mantenendo orientamento e dimensioni originali
                // Questo crea la pagina n nel nuovo PDF
                $pdf->AddPage($specs['orientation'], [$specs['width'], $specs['height']]);

                // 3. "Stampiamo" il contenuto originale sulla pagina appena creata
                $pdf->useTemplate($tplIdx);

                // 4. Ora scriviamo il watermark SOPRA il contenuto appena inserito
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->SetTextColor(80, 80, 80);

                $name = Company::first()->name;
                $text = "Protocollo N. " . $protocolNumber . " del " . $record->created_at->format('d/m/Y');
                $flow = $record->flow_type->getLetter();

                // Calcolo posizione basso a destra
                $cellWidth = 65;
                $x = $specs['width'] - $cellWidth - 10; // 10mm dal bordo destro
                $y = $specs['height'] - 12;            // 10mm dal bordo inferiore

                $pdf->SetXY($x, $y);
                $pdf->Cell($cellWidth-5, 5, $name, 1, 0, 'L');
                $pdf->Cell(5, 5, $flow, 1, 0, 'C');
                $pdf->SetXY($x, $y+5);
                $pdf->Cell($cellWidth, 5, $text, 1, 0, 'R');
            }

            $output = $pdf->Output('S');

            if (file_exists($tempFile)) unlink($tempFile);

            return $output;
        } catch (\Exception $e) {
            if (file_exists($tempFile)) unlink($tempFile);
            throw $e;
        }
    }

    private function replyRegistry($record, $data) {

        $protocolNumber = static::newProtocol();
        $newPath = 'registry/' . $protocolNumber;
        $account = Account::find($data['account_id']);

        $newRegistry = Registry::create([
            'protocol_number' => $protocolNumber,
            'flow_type' => 'issued',
            'flow_index' => static::newIndex('issued'),
            'registry_origin_type' => 'reply',
            // 'parent_id' => $record->id,
            'is_email' => true,
            'scope_type_id' => $record->scope_type_id,
            'uid' => '#reply' . $protocolNumber,
            'message_id' => now()->format('Y-m-d_H-i-s') . '_' . $protocolNumber,
            'sender_id' => null,
            'from' => $account->public_name,
            'subject' => "Re: " . $record->subject,
            'body' => null,
            'receive_date' => null,
            'account_id' => $data['account_id'],
            'send_date' => null,
            'send_user_id' => null,
            'shipment_id' => null,
            'attachment_path' => $newPath,
            'download_date' => null,
            'download_user_id' => null,
            'register_user_id' => Auth::user()->id,
            'manage_registry_type' => ManageRegistryType::NONE,
        ]);

        // Creazione collegamento
        $newRegistry->parentRegistries()->attach($record->id, [
            'relationship_type' => RelationshipType::REPLY->value
        ]);

        // Creazione destinatario
        RegistryReceiver::create([
            'registry_id' => $newRegistry->id,
            'protocol_number' => $protocolNumber,
            'recipient_id' => static::getRecipientId($record->from),
            'address' => $record->from,
            'pec_status' => PecStatus::WAITING,
        ]);

        $this->redirect(RegistryResource::getUrl('edit', ['record' => $newRegistry->id]));
    }

    private function replyManualInsert(Registry $record) {
        $emails = RecipientEmail::whereIn('recipient_id', $record->other_senders)->where('mail_type', MailType::PEC)->pluck('email')->toArray();

        $newManualInsert = ManualInsert::create([
            'flow_type' => FlowType::ISSUED,
            'receivers' => $emails,
            'subject' => "Re: " . $record->subject,
            'body' => null,
            'is_reply' => true,
            'linked_registry_id' => $record->id,
            'create_user_id' => Auth::user()->id,
        ]);

        $newManualInsert->update(['attachment_path' => 'manual_insert/' . $newManualInsert->id,]);

        $this->redirect(ManualInsertResource::getUrl('edit', ['record' => $newManualInsert->id]));
    }

    private function replySendEmail(Registry $record, array $data) {
        $newSendEmail = SendEmail::create([
            'account_id' => $data['account_id'],
            'signature_id' => null,
            'mail_type' => MailType::PEC,
            'office_type_id' => null,
            'recipients' => !empty($record->other_senders) ? array_merge([$record->from], $record->other_senders) : [$record->from],
            'subject' => "Re: " . $record->subject,
            'body' => '',
            'attachment_path' => null,
            'create_date' => today(),
            'create_user_id' => Auth::user()->id,
            'is_reply' => true,
            'linked_registry_id' => $record->id,
        ]);

        $newSendEmail->update(['attachment_path' => 'send_email/' . $newSendEmail->id,]);

        $this->redirect(SendEmailResource::getUrl('edit', ['record' => $newSendEmail->id]));
    }

    private function forwardRegistry($record, $data) {
        $protocolNumber = static::newProtocol();
        $newPath = 'registry/' . $protocolNumber;
        $account = Account::find($data['account_id']);

        $newRegistry = Registry::create([
            'protocol_number' => $protocolNumber,
            'flow_type' => 'issued',
            'flow_index' => static::newIndex('issued'),
            'registry_origin_type' => 'forward',
            // 'parent_id' => $record->id,
            'is_email' => true,
            'scope_type_id' => $record->scope_type_id,
            'uid' => '#forward' . $protocolNumber,
            'message_id' => now()->format('Y-m-d_H-i-s') . '_' . $protocolNumber,
            'sender_id' => null,
            'from' => $account->public_name,
            'subject' => $record->subject,
            'body' => $record->body,
            'receive_date' => null,
            'account_id' => $record->account_id,
            'send_date' => $record->send_date,
            'send_user_id' => $record->send_user_id,
            'shipment_id' => null,
            'attachment_path' => $newPath,
            'download_date' => null,
            'download_user_id' => null,
            'register_user_id' => Auth::user()->id,
            'manage_registry_type' => ManageRegistryType::NONE,
        ]);

        // Creazione collegamento
        $newRegistry->parentRegistries()->attach($record->id, [
            'relationship_type' => RelationshipType::FORWARD->value
        ]);

        $this->redirect(RegistryResource::getUrl('edit', ['record' => $newRegistry->id]));
    }

    private function forwardManualInsert(Registry $record) {
        // $emails = RecipientEmail::whereIn('recipient_id', $record->other_senders)->where('mail_type', MailType::PEC)->pluck('email')->toArray();

        $divider = "<br><br>------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------<br><br>";

        $newManualInsert = ManualInsert::create([
            'flow_type' => FlowType::ISSUED,
            'receivers' => null,
            'subject' => $record->subject,
            'body' => $divider . ($record->eml_body ?? $record->body),
            'is_forward' => true,
            'linked_registry_id' => $record->id,
            'create_user_id' => Auth::user()->id,
        ]);

        logger('BODY SALVATO: ' . $newManualInsert->body);

        $newPath = 'manual_insert/' . $newManualInsert->id;

        $newManualInsert->update(['attachment_path' => $newPath,]);

        $this->copyAttachments($record->attachment_path, $newPath);

        $this->redirect(ManualInsertResource::getUrl('edit', ['record' => $newManualInsert->id]));
    }

    private function forwardSendEmail(Registry $record, array $data) {
        
        $divider = "<br><br>------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------<br><br>";

        $newSendEmail = SendEmail::create([
            'account_id' => $data['account_id'],
            'signature_id' => null,
            'mail_type' => MailType::PEC,
            'office_type_id' => null,
            'recipients' => null,
            'subject' => $record->subject,
            'body' => $divider . ($record->eml_body ?? $record->body),
            'attachment_path' => null,
            'create_date' => today(),
            'create_user_id' => Auth::user()->id,
            'is_forward' => true,
            'linked_registry_id' => $record->id,
        ]);

        $newPath = 'send_email/' . $newSendEmail->id;

        $newSendEmail->update(['attachment_path' => $newPath,]);

        $this->copyAttachments($record->attachment_path, $newPath);

        $this->redirect(SendEmailResource::getUrl('edit', ['record' => $newSendEmail->id]));
    }

    private function copyAttachments(?string $sourcePath, string $destinationPath): void
    {
        if (empty($sourcePath)) {
            return;
        }

        Log::info('Copia allegati per inoltro da ' . $sourcePath);
        Log::info('Copia allegati per inoltro in ' . $destinationPath);

        $config = config('filesystems.default');
        Log::info('Disk: ' . $config);
        $disk = Storage::disk($config);

        if (!$disk->exists($sourcePath)) {
            Log::warning("Path sorgente non trovato: {$sourcePath}");
            return;
        }

        try {
            $files = $disk->files($sourcePath);
            Log::info('Files da copiare:', $files);

            foreach ($files as $file) {
                Log::info('File in copia: ' . $file);

                $fileName = basename($file);
                $newFilePath = $destinationPath . '/' . $fileName;

                $stream = $disk->readStream($file);
                if ($stream === false || $stream === null) {
                    Log::error("Impossibile aprire stream per: {$file}");
                    continue;
                }

                $success = $disk->writeStream($newFilePath, $stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }

                if ($success) {
                    Log::info("Verifica riuscita: Il nuovo file esiste in {$newFilePath}");
                } else {
                    Log::error("Verifica fallita: Il file doveva essere in {$newFilePath} ma non è stato trovato!");
                }
            }

            Log::info("Copiati " . count($files) . " file da {$sourcePath} a {$destinationPath}");

        } catch (\Exception $e) {
            Log::error("Errore nella copia degli allegati", [
                'source'      => $sourcePath,
                'destination' => $destinationPath,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);
        }
    }

    private static function extractReferencedMessageId(string $emlContent): ?string
    {
        if (preg_match('/^X-Riferimento-Message-ID:\s*(.+)$/mi', $emlContent, $matches)) {
            return trim($matches[1], "<> \t\n\r\0\x0B");
        }

        if (preg_match('/^Message-ID:\s*(.+)$/mi', $emlContent, $matches)) {
            return trim($matches[1], "<> \t\n\r\0\x0B");
        }

        return null;
    }

    // private function copyAttachmentsOld(?string $sourcePath, string $destinationPath): void
    // {
    //     if (empty($sourcePath)) {
    //         return;
    //     }
    //     Log::info('Copia allegati per inoltro da ' . $sourcePath);
    //     Log::info('Copia allegati per inoltro in ' . $destinationPath);

    //     $config = config('filesystems.default');
    //     Log::info('Disk: '. $config);
    //     $disk = Storage::disk($config);

    //     if (!$disk->exists($sourcePath)) {
    //         Log::warning("Path sorgente non trovato: {$sourcePath}");
    //         return;
    //     }

    //     try {
    //         $files = $disk->allFiles($sourcePath);
    //         Log::info('Files da copiare:', json_decode(json_encode($files), true));
    //         foreach ($files as $file) {
    //             Log::info('File in copia: ' . $file);
    //             $fileName = basename($file);
    //             $newFilePath = $destinationPath . '/' . $fileName;
    //             $disk->copy($file, $newFilePath);
    //             if ($disk->exists($newFilePath)) {
    //                 Log::info("Verifica riuscita: Il nuovo file esiste in {$newFilePath}");
    //             } else {
    //                 Log::error("Verifica fallita: Il file doveva essere in {$newFilePath} ma non è stato trovato!");
    //             }
    //         }

    //         Log::info("Copiati " . count($files) . " file da {$sourcePath} a {$destinationPath}");

    //     } catch (\Exception $e) {
    //         Log::error("Errore nella copia degli allegati", [
    //             'source' => $sourcePath,
    //             'destination' => $destinationPath,
    //             'error' => $e->getMessage()
    //         ]);
    //     }
    // }

    // private function copyAttachmentsRecursive(?string $sourcePath, string $destinationPath): void
    // {
    //     if (empty($sourcePath)) {
    //         return;
    //     }

    //     $disk = Storage::disk(config('filesystems.default'));

    //     if (!$disk->exists($sourcePath)) {
    //         return;
    //     }

    //     // Ottieni tutti i file ricorsivamente
    //     $files = $disk->allFiles($sourcePath);

    //     foreach ($files as $file) {
    //         // Mantieni la struttura delle sottocartelle
    //         $relativePath = str_replace($sourcePath . '/', '', $file);
    //         $newFilePath = $destinationPath . '/' . $relativePath;

    //         $disk->copy($file, $newFilePath);
    //     }
    // }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('registryReceivers', $data)) {
            $this->pendingReceiverKeys = $data['registryReceivers'] ?? [];
            unset($data['registryReceivers']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->pendingReceiverKeys !== null) {
            $this->record->syncReceiversFromKeys($this->pendingReceiverKeys);
        }
    }

    private static function extractMotivazione(string $body): ?string
    {
        // Aruba: "è stato rilevato un errore 5.2.1 - ARUBA PEC S.p.A. - <descrizione>"
        if (preg_match('/rilevato un errore\s+[\d.]+\s*-\s*(.+?)(?:\r?\n|$)/i', $body, $matches)) {
            $reason = trim(preg_replace('/\s+/', ' ', $matches[1]));
            return mb_substr($reason, 0, 500);
        }

        $patterns = [
            '/a causa di\s*:?\s*(.+?)(?:\r?\n\r?\n|$)/is',
            '/in quanto\s*:?\s*(.+?)(?:\r?\n\r?\n|$)/is',
            '/motivo\s*:?\s*(.+?)(?:\r?\n\r?\n|$)/is',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $body, $matches)) {
                $reason = trim(preg_replace('/\s+/', ' ', $matches[1]));
                return mb_substr($reason, 0, 500);
            }
        }

        return null;
    }

    private static function decodeBody(string $rawHeadersOrFullContent, ?string $body = null): string
    {
        // Se viene passato un solo argomento = contenuto completo del .eml
        if ($body === null) {
            $full = $rawHeadersOrFullContent;
            // Separiamo header e body nel modo classico (prima riga vuota)
            $parts = preg_split("/\r?\n\r?\n/", $full, 2);
            $rawHeaders = $parts[0] ?? '';
            $body = $parts[1] ?? $full;
        } else {
            $rawHeaders = $rawHeadersOrFullContent;
        }

        // Il Content-Transfer-Encoding della parte testuale può essere annidato
        // in una sotto-parte MIME (multipart/signed, multipart/mixed...) e non
        // comparire negli header restituiti da imap_fetchheader(): cerchiamo
        // in tutto il messaggio (header di primo livello + corpo).
        $haystack = $rawHeaders . "\n" . $body;

        if (preg_match('/Content-Transfer-Encoding:\s*quoted-printable/i', $haystack)) {
            return quoted_printable_decode($body);
        }

        if (preg_match('/Content-Transfer-Encoding:\s*base64/i', $haystack)) {
            $decoded = base64_decode(trim($body), true);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $body;
    }
}
