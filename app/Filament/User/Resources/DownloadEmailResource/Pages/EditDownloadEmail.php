<?php

namespace App\Filament\User\Resources\DownloadEmailResource\Pages;

use App\Filament\User\Resources\DownloadEmailResource;
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

class EditDownloadEmail extends EditRecord
{
    protected static string $resource = DownloadEmailResource::class;

    public function getTitle(): string | Htmlable
    {
        return $this->record->subject;
    }

    protected function getHeaderActions(): array
    {
        return [
            // Actions\DeleteAction::make(),
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
                        ->label('Ambito')
                        ->options(ScopeType::pluck('name', 'id'))
                        ->searchable()
                        ->placeholder('Seleziona l\'ambito della registrazione')
                ])
                ->action(function ($record, $data) {
                    try {
                        $this->registerEmail($record, $data['scope_type_id']);
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
                ->modalHeading('Conferma eliminazione email')
                ->modalDescription('Sei sicuro di voler eliminare questa email? Questa azione non può essere annullata.')
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
                return DownloadEmailResource::getUrl('index');
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

    private function registerEmail($record, $scopeTypeId)
    {
        try {
            DB::beginTransaction();

            $oldPath = $record->attachment_path;
            $protocolNumber = static::newProtocol();
            $newPath = 'registry/' . $protocolNumber;

            $registry = Registry::create([
                'protocol_number' => $protocolNumber,
                'flow_type' => 'received',
                'is_email' => true,
                'scope_type_id' => $scopeTypeId,
                'uid' => $record->uid,
                'message_id' => $record->message_id,
                'from' => $record->from,
                'subject' => $record->subject,
                'body' => $record->body,
                'receive_date' => $record->receive_date,
                'attachment_path' => $newPath,
                'download_date' => $record->created_at,
                'download_user_id' => $record->download_user_id,
                'register_user_id' => Auth::id(),
            ]);

            // Sposta l'intera cartella degli allegati
            if ($oldPath && Storage::exists($oldPath)) {
                Storage::move($oldPath, $newPath);
            }

            // Elimina il record originale
            Model::withoutEvents(function () use ($record) {
                $record->delete();
            });

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
}
