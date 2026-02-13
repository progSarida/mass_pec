<?php

namespace App\Filament\User\Resources\RegistryReceiverResource\Pages;

use App\Filament\User\Resources\RegistryReceiverResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRegistryReceivers extends ListRecords
{
    protected static string $resource = RegistryReceiverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
