<?php

namespace App\Filament\User\Resources\InMailResource\Pages;

use App\Filament\User\Resources\InMailResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInMail extends EditRecord
{
    protected static string $resource = InMailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
