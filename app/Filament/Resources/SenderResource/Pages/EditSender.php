<?php

namespace App\Filament\Resources\SenderResource\Pages;

use App\Filament\Resources\SenderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSender extends EditRecord
{
    protected static string $resource = SenderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
