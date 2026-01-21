<?php

namespace App\Filament\User\Resources\ShipmentErrorResource\Pages;

use App\Filament\User\Resources\ShipmentErrorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShipmentError extends EditRecord
{
    protected static string $resource = ShipmentErrorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
