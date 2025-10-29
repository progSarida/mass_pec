<?php

namespace App\Filament\Resources\IstatTypeResource\Pages;

use App\Filament\Resources\IstatTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditIstatType extends EditRecord
{
    protected static string $resource = IstatTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
