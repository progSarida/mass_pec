<?php

namespace App\Filament\Resources\AdminTypeResource\Pages;

use App\Filament\Resources\AdminTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAdminTypes extends ListRecords
{
    protected static string $resource = AdminTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
