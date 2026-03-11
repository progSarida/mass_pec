<?php

namespace App\Filament\User\Resources\InMailResource\Pages;

use App\Enums\ManageRegistryType;
use App\Filament\User\Resources\InMailResource;
use App\Models\InMail;
use App\Models\Registry;
use App\Models\ScopeType;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EditInMail extends EditRecord
{
    protected static string $resource = InMailResource::class;

    public function getTitle(): string | Htmlable
    {
        // return $this->record->subject;
        return "Modifica email ricevuta";
    }

    protected function getHeaderActions(): array
    {
        $currentInMail = $this->record;
        $previousCInMail = InMail::where('created_at', '<=', $currentInMail->created_at)->where('id', '!=', $currentInMail->id)
                                ->orderBy('created_at', 'desc')->orderBy('id', 'desc')->first();
        $nextCInMail = InMail::where('created_at', '>=', $currentInMail->created_at)->where('id', '!=', $currentInMail->id)
                                ->orderBy('created_at', 'asc')->orderBy('id', 'asc')->first();
        $previousRInMail = InMail::where('receive_date', '<=', $currentInMail->receive_date)->where('id', '!=', $currentInMail->id)
                                ->orderBy('receive_date', 'desc')->orderBy('id', 'desc')->first();
        $nextRInMail = InMail::where('receive_date', '>=', $currentInMail->receive_date)->where('id', '!=', $currentInMail->id)
                                ->orderBy('receive_date', 'asc')->orderBy('id', 'asc')->first();
        return [
            // Actions\DeleteAction::make(),
            // Scorrimento cronologico
            Actions\Action::make('previous_c_in_mail')
                ->label('Scarico')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousCInMail) { return $previousCInMail;})
                ->action(function () use ($previousCInMail) {
                    $this->redirect(InMailResource::getUrl('edit', ['record' => $previousCInMail->id]));
                }),
            Actions\Action::make('next_c_in_mail')
                ->label('Scarico')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextCInMail) { return $nextCInMail;})
                ->action(function () use ($nextCInMail) {
                    $this->redirect(InMailResource::getUrl('edit', ['record' => $nextCInMail->id]));
                }),
            // Scorrimento ricezione
            Actions\Action::make('previous_r_in_mail')
                ->label('Ricezione')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousRInMail) { return $previousRInMail;})
                ->action(function () use ($previousRInMail) {
                    $this->redirect(InMailResource::getUrl('edit', ['record' => $previousRInMail->id]));
                }),
            Actions\Action::make('next_r_in_mail')
                ->label('Ricezione')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextRInMail) { return $nextRInMail;})
                ->action(function () use ($nextRInMail) {
                    $this->redirect(InMailResource::getUrl('edit', ['record' => $nextRInMail->id]));
                }),
            Actions\ActionGroup::make([
                Actions\Action::make('register')
                    ->label('Protocolla')
                    ->icon('fluentui-pen-20-o')
                    ->color('warning')
                    ->visible(fn() => Auth::user()->hasRole('super_admin') || Auth::user()->hasRole('manager'))
                    ->requiresConfirmation()
                    ->modalHeading('Protocolla email')
                    ->modalDescription('La mail verrà inserita nel protocollo ed eliminata dall\'elenco')
                    ->modalSubmitActionLabel('Protocolla')
                    ->form([
                        Select::make('scope_type_id')
                            ->label('Settore interno')
                            ->options(ScopeType::pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('Seleziona il settore interno della registrazione'),
                        Select::make('manage_registry_type')
                            ->label('Gestione')
                            ->options(
                                collect(ManageRegistryType::cases())
                                    ->filter(fn (ManageRegistryType $enum) => $enum->showToAssign())
                                    ->mapWithKeys(fn (ManageRegistryType $enum) => [
                                        $enum->value => $enum->getLabel()
                                    ])
                            )
                            ->default(ManageRegistryType::NONE->value)
                    ])
                    ->action(function ($record, $data) {
                        try {
                            static::registerEmail($record, $data);
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
                return InMailResource::getUrl('index');
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

    private static function registerEmail($record, $data)
    {
        try {
            DB::beginTransaction();

            $scopeTypeId = $data['scope_type_id'];
            $manageRegistryType = $data['manage_registry_type'];

            $oldPath = $record->attachment_path;
            $protocolNumber = static::newProtocol();
            $newPath = 'registry/' . $protocolNumber;

            $registry = Registry::create([
                'protocol_number' => $protocolNumber,
                'flow_type' => 'received',
                'flow_index' => static::newIndex('received'),
                'registry_origin_type' => 'in_mail',
                'is_email' => true,
                'scope_type_id' => $scopeTypeId,
                'uid' => $record->uid,
                'message_id' => $record->message_id,
                'sender_id' => $record->sender_id,
                'from' => $record->from,
                'subject' => $record->subject,
                'body' => $record->body,
                'receive_date' => $record->receive_date,
                'account_id' => null,
                'send_date' => null,
                'send_user_id' => null,
                'shipment_id' => null,
                'attachment_path' => $newPath,
                'download_date' => $record->created_at,
                'download_user_id' => $record->download_user_id,
                'register_user_id' => Auth::id(),
                'manage_registry_type' => $manageRegistryType,
            ]);

            $disk = config('filesystems.default');
            $storage = Storage::disk($disk);

            // Copio cartella allegati
            if ($oldPath && $storage->exists($oldPath)) {
                // Storage::disk($disk)->makeDirectory($newPath);

                if (!$storage->exists($newPath)) {
                    $storage->makeDirectory($newPath);
                }
                $files = Storage::disk($disk)->allFiles($oldPath);

                // foreach ($files as $file) {
                //     $relativePath = str_replace($oldPath . '/', '', $file);
                //     $newFilePath = $newPath . '/' . today()->format('d-m-Y') . '_' . $registry->protocol_number . '_RIC_' . $relativePath;

                //     $directory = dirname($newFilePath);
                //     if (!Storage::disk($disk)->exists($directory)) {
                //         Storage::disk($disk)->makeDirectory($directory);
                //     }

                //     Storage::disk($disk)->put($newFilePath, Storage::disk($disk)->get($file));
                // }

                foreach ($files as $file) {
                    $fileName = basename($file);
                    $newFileName = today()->format('d-m-Y') . '_' . $registry->protocol_number . '_RIC_' . $fileName;
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

            return $registry;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Errore protocollazione email: " . $e->getMessage() . ' - Linea: ' . $e->getLine());
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
