<?php

namespace App\Filament\User\Resources\RegistryResource\Pages;

use App\Filament\User\Resources\RegistryResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditRegistry extends EditRecord
{
    protected static string $resource = RegistryResource::class;

    public function getTitle(): string | Htmlable
    {
        return $this->record->subject;
    }

    protected function getHeaderActions(): array
    {
        return [
            // Actions\DeleteAction::make(),
            Action::make('uploadFile')
                ->label('Carica File')
                ->icon('heroicon-o-document-arrow-up')
                ->color('info')
                // ->visible(fn($record) => !$record->is_email)
                ->form([
                    FileUpload::make('attachments')
                        ->label('Seleziona File')
                        ->multiple()
                        ->directory(fn ($record) => $record->attachment_path)
                        ->preserveFilenames()
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
                ->modalDescription('Sei sicuro di voler eliminare questa voce del protocollo? Questa azione non può essere annullata.')
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
}
