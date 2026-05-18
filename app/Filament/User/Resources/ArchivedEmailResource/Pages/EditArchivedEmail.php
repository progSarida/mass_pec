<?php

namespace App\Filament\User\Resources\ArchivedEmailResource\Pages;

use App\Filament\User\Resources\ArchivedEmailResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditArchivedEmail extends EditRecord
{
    protected static string $resource = ArchivedEmailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            // Actions\DeleteAction::make(),
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
