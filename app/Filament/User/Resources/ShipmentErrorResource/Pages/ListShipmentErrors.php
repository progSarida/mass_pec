<?php

namespace App\Filament\User\Resources\ShipmentErrorResource\Pages;

use App\Filament\User\Resources\ShipmentErrorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShipmentErrors extends ListRecords
{
    protected static string $resource = ShipmentErrorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
