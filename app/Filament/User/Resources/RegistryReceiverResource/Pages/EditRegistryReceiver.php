<?php

namespace App\Filament\User\Resources\RegistryReceiverResource\Pages;

use App\Filament\User\Resources\RegistryReceiverResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRegistryReceiver extends EditRecord
{
    protected static string $resource = RegistryReceiverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
