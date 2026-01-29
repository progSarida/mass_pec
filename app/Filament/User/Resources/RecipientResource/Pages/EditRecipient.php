<?php

namespace App\Filament\User\Resources\RecipientResource\Pages;

use App\Filament\User\Resources\RecipientResource;
use App\Models\Recipient;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditRecipient extends EditRecord
{
    protected static string $resource = RecipientResource::class;

    public function getTitle(): string | Htmlable
    {
        // return $this->record->description;
        return "Modifica interlocutore";
    }

    protected function getHeaderActions(): array
    {
        $currentRecipient = $this->record;
        $previousDRecipient = Recipient::where('description', '<', $currentRecipient->description)->orderBy('description', 'desc')->first();
        $nextDRecipient = Recipient::where('description', '>', $currentRecipient->description)->orderBy('description', 'asc')->first();
        $previousIRecipient = Recipient::where('id', '<', $currentRecipient->id)->orderBy('id', 'desc')->first();
        $nextIRecipient = Recipient::where('id', '>', $currentRecipient->id)->orderBy('id', 'asc')->first();
        return [
            // Scorrimento alfabetico
            Actions\Action::make('previous_d_recipient')
                ->label('Alfabetico prec.')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousDRecipient) { return $previousDRecipient;})
                ->action(function () use ($previousDRecipient) {
                    $this->redirect(RecipientResource::getUrl('edit', ['record' => $previousDRecipient->id]));
                }),
            Actions\Action::make('next_d_recipient')
                ->label('Alfabetico succ.')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextDRecipient) { return $nextDRecipient;})
                ->action(function () use ($nextDRecipient) {
                    $this->redirect(RecipientResource::getUrl('edit', ['record' => $nextDRecipient->id]));
                }),
            // Actions\DeleteAction::make(),
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
                ->modalHeading('Conferma eliminazione interlocutore')
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
                return RecipientResource::getUrl('index');
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
