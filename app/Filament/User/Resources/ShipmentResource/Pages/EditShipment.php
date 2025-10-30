<?php

namespace App\Filament\User\Resources\ShipmentResource\Pages;

use App\Filament\User\Resources\ShipmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShipment extends EditRecord
{
    protected static string $resource = ShipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('send')
                ->label('Invio PEC')
                ->action(function (array $data) {
                    dd('INVIO');
                }),
            Actions\Action::make('download')
                ->label('Scarico ricevute')
                ->action(function (array $data) {
                    dd('SCARICO');
                }),
            Actions\Action::make('extract')
                ->label('Estrazione')
                ->action(function (array $data) {
                    dd('ESTRAZIONE');
                }),
            Actions\Action::make('receivers')
                ->label('Pec  destinatari')
                ->modalHeading('Pec destinatari')
                ->modalWidth('md')
                ->form(fn () => [
                    // qui inserire lista mail a cui è stata inviata la spedizione
                ]),
            Actions\DeleteAction::make(),
        ];
    }
}
