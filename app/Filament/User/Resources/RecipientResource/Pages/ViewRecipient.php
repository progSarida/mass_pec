<?php

namespace App\Filament\User\Resources\RecipientResource\Pages;

use App\Filament\User\Resources\RecipientResource;
use App\Models\Recipient;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewRecipient extends ViewRecord
{
    protected static string $resource = RecipientResource::class;

    public function getTitle(): string | Htmlable
    {
        // return $this->record->description;
        return "Visualizza interlocutore";
    }

    protected function getHeaderActions(): array
    {
        $currentRecipient = $this->record;
        $previousDRecipient = Recipient::where('description', '<', $currentRecipient->description)->orderBy('description', 'desc')->first();
        $nextDRecipient = Recipient::where('description', '>', $currentRecipient->description)->orderBy('description', 'asc')->first();
        $previousPRecipient = Recipient::whereHas('city', function ($query) use ($currentRecipient) {
            $query->where('province_id', $currentRecipient->city->province_id);
        })
        ->where('id', '<', $currentRecipient->id)->orderBy('id', 'desc')->first();
        $nextPRecipient = Recipient::whereHas('city', function ($query) use ($currentRecipient) {
            $query->where('province_id', $currentRecipient->city->province_id);
        })
        ->where('id', '>', $currentRecipient->id)->orderBy('id', 'asc')->first();
        return [
            Actions\Action::make('back')
                ->label('Indietro')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
            // Scorrimento alfabetico
            Actions\Action::make('previous_d_recipient')
                ->label('Alfabetico prec.')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousDRecipient) { return $previousDRecipient;})
                ->action(function () use ($previousDRecipient) {
                    $this->redirect(RecipientResource::getUrl('view', ['record' => $previousDRecipient->id]));
                }),
            Actions\Action::make('next_d_recipient')
                ->label('Alfabetico succ.')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextDRecipient) { return $nextDRecipient;})
                ->action(function () use ($nextDRecipient) {
                    $this->redirect(RecipientResource::getUrl('view', ['record' => $nextDRecipient->id]));
                }),
            // Scorrimento provincia
            Actions\Action::make('previous_p_recipient')
                ->label('Prec. provincia')
                ->color('info')
                ->icon('heroicon-o-arrow-left-circle')
                ->visible(function () use ($previousPRecipient) { return $previousPRecipient;})
                ->action(function () use ($previousPRecipient) {
                    $this->redirect(RecipientResource::getUrl('view', ['record' => $previousPRecipient->id]));
                }),
            Actions\Action::make('next_p_recipient')
                ->label('Succ. provincia')
                ->color('info')
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(function () use ($nextPRecipient) { return $nextPRecipient;})
                ->action(function () use ($nextPRecipient) {
                    $this->redirect(RecipientResource::getUrl('view', ['record' => $nextPRecipient->id]));
                }),
            Actions\EditAction::make(),
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
