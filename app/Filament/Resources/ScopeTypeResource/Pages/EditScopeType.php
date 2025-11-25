<?php

namespace App\Filament\Resources\ScopeTypeResource\Pages;

use App\Filament\Resources\ScopeTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditScopeType extends EditRecord
{
    protected static string $resource = ScopeTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
