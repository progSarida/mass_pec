<?php

namespace App\Filament\User\Resources\RegistryResource\Pages;

use App\Enums\FlowType;
use App\Enums\ManageRegistryType;
use App\Enums\PecStatus;
use App\Enums\RegistryOriginType;
use App\Filament\User\Resources\RegistryResource;
use App\Jobs\DownloadReceiptsJob;
use App\Models\Account;
use App\Models\Company;
use App\Models\Recipient;
use App\Models\Registry;
use App\Models\RegistryReceiver;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

class EditRegistry extends EditRecord
{
    protected static string $resource = RegistryResource::class;

    public function getTitle(): string | Htmlable
    {
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
                                $prefix = today()->format('d-m-Y') . '_' . $record->protocol_number . '_INT_';
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
                        // 1. Recuperiamo il disco corretto
                        $disk = config('filesystems.default');
                        $storage = Storage::disk($disk);

                        // 2. Otteniamo l'array dei file.
                        // Attenzione: Filament a seconda della config potrebbe passarti un array o una stringa JSON
                        $attachments = $data['attachments'] ?? [];

                        if (!is_array($attachments)) {
                            $attachments = [$attachments];
                        }

                        foreach ($attachments as $filePath) {
                            // Pulizia percorso (alcuni driver aggiungono slash iniziali)
                            $filePath = ltrim($filePath, '/');

                            if (!$storage->exists($filePath)) {
                                Log::warning("File non trovato per watermark: " . $filePath);
                                continue;
                            }

                            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

                            if ($extension === 'pdf') {
                                try {
                                    // Leggiamo il contenuto
                                    $pdfContent = $storage->get($filePath);

                                    // Usiamo il numero di protocollo dal record
                                    $protocolNumber = $record->protocol_number ?? 'N/A';

                                    // Applichiamo il watermark
                                    $watermarkedPdf = static::addProtocolWatermarkBottom($pdfContent, $protocolNumber, $record);

                                    // Sovrascriviamo
                                    $storage->put($filePath, $watermarkedPdf, [
                                        'visibility' => 'private',
                                        'ContentType' => 'application/pdf',
                                    ]);

                                    Log::info("Watermark applicato con successo a: " . $filePath);

                                } catch (\Exception $e) {
                                    Log::error("Errore watermark su {$filePath}: " . $e->getMessage());
                                }
                            }
                        }

                        // Fondamentale: Se l'azione deve salvare i percorsi nel database,
                        // assicurati che il record sia aggiornato se non lo fa Filament in automatico
                        // $record->attachments = array_merge($record->attachments ?? [], $attachments);
                        // $record->save();

                        Notification::make()
                            ->title('Caricamento completato')
                            ->body('I file PDF sono stati protocollati correttamente.')
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
                                receiptsDelayMinutes: 15,     // Delay scarico ricevute
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
                                    $prefix = today()->format('d-m-Y') . '_' . $record->protocol_number . "_{$record->flow_type->getExt()}_";
                                    $finalName = $prefix . $filename . '.' . $extension;
                                    $counter = 1;

                                    while (Storage::disk($disk)->exists($directory . '/' . $finalName)) {
                                        $finalName = $filename . '_' . $counter . '.' . $extension;
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
                    ->visible(fn($record) => $record->isIngoingEmail())
                    ->icon('fluentui-arrow-reply-20-o')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Crea risposta')
                    ->modalDescription('Creare risposta a questa email?')
                    ->modalSubmitActionLabel('Crea')
                    ->modalCancelActionLabel('Annulla')
                    ->form([
                        Select::make('account_id')
                            ->label('Account')
                            ->required()
                            ->relationship(
                                name: 'account',
                                titleAttribute: 'public_name',
                                modifyQueryUsing: fn ($query) => $query
                                    ->where('send', true)
                                    ->whereHas('users', fn ($q) => $q->where('users.id', Auth::user()->id))
                                    ->orderBy('position', 'asc')
                            )
                            ->preload(),
                    ])
                    ->action(function ($record, array $data) {
                        $protocolNumber = static::newProtocol();
                        $newPath = 'registry/' . $protocolNumber;
                        $account = Account::find($data['account_id']);

                        $newRegistry = Registry::create([
                            'protocol_number' => $protocolNumber,
                            'flow_type' => 'issued',
                            'flow_index' => static::newIndex('issued'),
                            'registry_origin_type' => 'reply',
                            'parent_id' => $record->id,
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

                        RegistryReceiver::create([
                            'registry_id' => $newRegistry->id,
                            'protocol_number' => $protocolNumber,
                            'recipient_id' => static::getRecipientId($record->from),
                            'address' => $record->from,
                            'pec_status' => PecStatus::WAITING,
                        ]);

                        $this->redirect(RegistryResource::getUrl('edit', ['record' => $newRegistry->id]));
                    }),

                Action::make('forward')
                    ->label('Inoltra')
                    ->visible(fn($record) => $record->isIngoingEmail())
                    ->icon('fluentui-arrow-forward-20-o')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Inoltra email')
                    ->modalDescription('Creare copia in uscita di questa email?')
                    ->modalSubmitActionLabel('Crea')
                    ->modalCancelActionLabel('Annulla')
                    ->form([
                        Select::make('account_id')
                            ->label('Account')
                            ->required()
                            ->relationship(
                                name: 'account',
                                titleAttribute: 'public_name',
                                modifyQueryUsing: fn ($query) => $query
                                    ->where('send', true)
                                    ->whereHas('users', fn ($q) => $q->where('users.id', Auth::user()->id))
                                    ->orderBy('position', 'asc')
                            )
                            ->preload(),
                    ])
                    ->action(function ($record, array $data) {
                        $protocolNumber = static::newProtocol();
                        $newPath = 'registry/' . $protocolNumber;
                        $account = Account::find($data['account_id']);

                        $newRegistry = Registry::create([
                            'protocol_number' => $protocolNumber,
                            'flow_type' => 'issued',
                            'flow_index' => static::newIndex('issued'),
                            'registry_origin_type' => 'forward',
                            'parent_id' => $record->id,
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
                        $this->redirect(RegistryResource::getUrl('edit', ['record' => $newRegistry->id]));
                    }),

                Actions\Action::make('manage')
                    ->label('Gestisci')
                    ->icon('heroicon-o-cog-8-tooth')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn($record) => $record?->manage_registry_type?->showManage())
                    ->fillForm(fn (Registry $record): array => [
                        'manage_registry_type' => $record?->manage_registry_type?->value,
                        'manage_registry_date' => now(),
                    ])
                    ->form([
                        Select::make('manage_registry_type')
                            ->label('Gestione')
                            ->options(
                                collect(ManageRegistryType::cases())
                                    ->filter(fn (ManageRegistryType $enum) => $enum->showToUpdate())
                                    ->mapWithKeys(fn (ManageRegistryType $enum) => [
                                        $enum->value => $enum->getLabel()
                                    ])
                            )
                            ->live(),
                        DatePicker::make('manage_registry_date')
                            ->label('Data gestione')
                            ->required()
                            ->visible(fn (Get $get) =>$get('manage_registry_type') == ManageRegistryType::DONE->value ),
                    ])
                    ->action(function (Registry $record, $data) {
                        $manageRegistryDate = $data['manage_registry_date'] ?? null;
                        $record->update([
                            'manage_registry_type' => $data['manage_registry_type'],
                            'manage_registry_date' => $manageRegistryDate,
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
}
