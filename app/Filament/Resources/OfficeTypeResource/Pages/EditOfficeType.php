<?php

namespace App\Filament\Resources\OfficeTypeResource\Pages;

use App\Filament\Resources\OfficeTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOfficeType extends EditRecord
{
    protected static string $resource = OfficeTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
