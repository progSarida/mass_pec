<?php

namespace App\Filament\User\Resources\ShipmentResource\Pages;

use App\Filament\User\Resources\ShipmentResource;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;

class CreateShipment extends CreateRecord
{
    protected static string $resource = ShipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('attachments')
                ->label('Selezione allegati')
                ->modalHeading('Selezione allegati')
                ->modalWidth('md')
                ->form(fn () => [
                    TextInput::make('note')->label('Note'),
                ])->action(function (array $data) {
                    dd('ASSEGNAZIONE ALLEGATI');
                }),
            Actions\Action::make('receivers')
                ->label('Selezione Pec destinatari')
                ->modalHeading('Selezione Pec destinatari')
                ->modalWidth('md')
                ->form(fn () => [
                    TextInput::make('note')->label('Note'),
                ])->action(function (array $data) {
                    dd('ASSEGNAZIONE RICEVENTI');
                }),
        ];
    }
}
