<?php

namespace App\Filament\User\Resources\SendEmailResource\Pages;

use App\Enums\PecStatus;
use App\Filament\User\Resources\SendEmailResource;
use App\Models\Recipient;
use App\Models\Registry;
use App\Models\RegistryReceiver;
use App\Models\ScopeType;
use App\Models\SendEmail;
use Exception;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EditSendEmail extends EditRecord
{
    protected static string $resource = SendEmailResource::class;

    public function getTitle(): string
    {
        return "Modifica email in uscita";
    }

    protected function getHeaderActions(): array
    {
        $currentSendEmail = $this->record;
        // --- Navigazione per Data Creazione (solitamente non è null, ma per sicurezza...) ---
        $previousCSendEmail = SendEmail::where('create_date', '<', $currentSendEmail->create_date)
            ->orWhere(function ($query) use ($currentSendEmail) {
                $query->where('create_date', $currentSendEmail->create_date)
                    ->where('id', '<', $currentSendEmail->id);
            })
            ->orderBy('create_date', 'desc')->orderBy('id', 'desc')->first();
        $nextCSendEmail = SendEmail::where('create_date', '>', $currentSendEmail->create_date)
            ->orWhere(function ($query) use ($currentSendEmail) {
                $query->where('create_date', $currentSendEmail->create_date)
                    ->where('id', '>', $currentSendEmail->id);
            })
            ->orderBy('create_date', 'asc')->orderBy('id', 'asc')->first();
        // --- Navigazione per Data Invio (Gestione NULL) ---
        $previousSSendEmail = null;
        $nextSSendEmail = null;
        if ($currentSendEmail->send_date !== null) {
            $previousSSendEmail = SendEmail::whereNotNull('send_date')
                ->where(function ($query) use ($currentSendEmail) {
                    $query->where('send_date', '<', $currentSendEmail->send_date)
                        ->orWhere(function ($q) use ($currentSendEmail) {
                            $q->where('send_date', $currentSendEmail->send_date)
                                ->where('id', '<', $currentSendEmail->id);
                        });
                })
                ->orderBy('send_date', 'desc')->orderBy('id', 'desc')->first();
            $nextSSendEmail = SendEmail::whereNotNull('send_date')
                ->where(function ($query) use ($currentSendEmail) {
                    $query->where('send_date', '>', $currentSendEmail->send_date)
                        ->orWhere(function ($q) use ($currentSendEmail) {
                            $q->where('send_date', $currentSendEmail->send_date)
                                ->where('id', '>', $currentSendEmail->id);
                        });
                })
                ->orderBy('send_date', 'asc')->orderBy('id', 'asc')->first();
        }
        return [
            // Actions\ViewAction::make(),
            // Actions\DeleteAction::make(),
            // Scorrimento cronologico
            Actions\Action::make('previous_c_in_mail')
                ->label('Creazione')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousCSendEmail) { return $previousCSendEmail;})
                ->action(function () use ($previousCSendEmail) {
                    $this->redirect(SendEmailResource::getUrl('edit', ['record' => $previousCSendEmail->id]));
                }),
            Actions\Action::make('next_c_in_mail')
                ->label('Creazione')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextCSendEmail) { return $nextCSendEmail;})
                ->action(function () use ($nextCSendEmail) {
                    $this->redirect(SendEmailResource::getUrl('edit', ['record' => $nextCSendEmail->id]));
                }),
            // Scorrimento invio
            Actions\Action::make('previous_r_in_mail')
                ->label('Invio')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousSSendEmail) { return $previousSSendEmail;})
                ->action(function () use ($previousSSendEmail) {
                    $this->redirect(SendEmailResource::getUrl('edit', ['record' => $previousSSendEmail->id]));
                }),
            Actions\Action::make('next_r_in_mail')
                ->label('Invio')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextSSendEmail) { return $nextSSendEmail;})
                ->action(function () use ($nextSSendEmail) {
                    $this->redirect(SendEmailResource::getUrl('edit', ['record' => $nextSSendEmail->id]));
                }),
            Actions\ActionGroup::make([
                Action::make('uploadFile')
                    ->label('Carica allegati')
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('info')
                    ->modalSubmitActionLabel('Carica')
                    // ->visible(fn($record) => !$record->is_email)
                    ->form([
                        FileUpload::make('attachments')
                            ->label('Seleziona File')
                            ->multiple()
                            ->directory(fn ($record) => $record->attachment_path)
                            ->preserveFilenames()
                            ->getUploadedFileNameForStorageUsing(function ($file, $record) {
                                $disk = config('filesystems.default');
                                $directory = $record->attachment_path;

                                // Estraiamo nome e estensione originali
                                $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                                $extension = $file->getClientOriginalExtension();

                                $finalName = $filename . '.' . $extension;
                                $counter = 1;

                                // Finché esiste un file con questo nome, incrementiamo il suffisso
                                while (Storage::disk($disk)->exists($directory . '/' . $finalName)) {
                                    $finalName = $filename . '_' . $counter . '.' . $extension;
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
                    ->label('Elimina allegati')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn($record) => $record && $record->attachment_path && !empty(Storage::files($record->attachment_path)))
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

                Actions\Action::make('register')
                    ->label('Protocolla')
                    ->icon('fluentui-pen-20-o')
                    ->color('warning')
                    ->visible(fn($record) => (Auth::user()->hasRole('super_admin') || Auth::user()->hasRole('manager')))
                    ->requiresConfirmation()
                    ->modalHeading('Protocolla email')
                    ->modalDescription('La mail verrà inserita nel protocollo ed eliminata dall\'elenco')
                    ->modalSubmitActionLabel('Protocolla')
                    ->form([
                        Select::make('scope_type_id')
                            ->label('Settore interno')
                            ->options(ScopeType::pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('Seleziona il settore interno della registrazione')
                    ])
                    ->action(function ($record, $data) {
                        try {
                            static::registerEmail($record, $data['scope_type_id']);
                            Notification::make()
                                ->title('Mail protocollata')
                                ->body('La mail e i suoi allegati sono stati protocollati con successo.')
                                ->success()
                                ->send();
                            $resource = $this->getResource();
                            return $this->redirect($resource::getUrl('index'));
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
                ->modalHeading('Conferma eliminazione mail')
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
                return SendEmailResource::getUrl('index');
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

//     private static function nameRecipient($email): string
//     {
//         $rec = Recipient::where(function ($query) use ($email) {
//             $query->where('mail_1', $email)
//                 ->orWhere('mail_2', $email)
//                 ->orWhere('mail_3', $email)
//                 ->orWhere('mail_4', $email)
//                 ->orWhere('mail_5', $email);
//         })
//         ->select('description', 'resp_surname', 'resp_name')
//         ->first();

//         if ($rec) {
//             // return "{$rec->description} - {$rec->resp_surname} {$rec->resp_name}";
//             return "{$rec->description}";
//         }

//         return $email;
//     }

    private static function registerEmail($record, $scopeTypeId){
        try {
            DB::beginTransaction();

            $oldPath = $record->attachment_path;
            $protocolNumber = static::newProtocol();

            $newPath = 'registry/' . $protocolNumber;

            $registry = Registry::create([
                'protocol_number' => $protocolNumber,
                'flow_type' => 'issued',
                'flow_index' => static::newIndex('issued'),
                'registry_origin_type' => 'send_email',
                'is_email' => true,
                'scope_type_id' => $scopeTypeId,
                'uid' => '#send_email' . $record->id,
                'message_id' => now()->format('Y-m-d_H-i-s') . '_' . $record->id,
                'from' => $record->account->public_name,
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
            ]);

            foreach(($record->recipients ?? []) as $receiver){
                RegistryReceiver::create([
                    'registry_id' => $registry->id,
                    'protocol_number' => $protocolNumber,
                    'address' => $receiver,
                    'pec_status' => PecStatus::WAITING,
                ]);
            }

            $disk = config('filesystems.default');
            $storage = Storage::disk($disk);

            if ($oldPath && $storage->exists($oldPath)) {

                if (!$storage->exists($newPath)) {
                    $storage->makeDirectory($newPath);
                }

                $files = $storage->allFiles($oldPath);

                // DEBUG: Logga quanti file hai trovato
                Log::info("File trovati in $oldPath: " . count($files));

                // foreach ($files as $file) {
                //     $fileName = basename($file);
                //     $newFileName = today()->format('d-m-Y') . '_' . $registry->protocol_number . '_INV_' . $fileName;
                //     $finalPath = $newPath . '/' . $newFileName;

                //     // Log per tracciamento
                //     Log::info("Copia file da $file a $finalPath");

                //     if (!$storage->copy($file, $finalPath)) {
                //         throw new \Exception("Impossibile copiare il file: $file");
                //     }
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
                $record->delete();
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
}
