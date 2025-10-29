<?php

namespace App\Filament\Resources\AdminTypeResource\Pages;

use App\Filament\Resources\AdminTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAdminType extends EditRecord
{
    protected static string $resource = AdminTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
