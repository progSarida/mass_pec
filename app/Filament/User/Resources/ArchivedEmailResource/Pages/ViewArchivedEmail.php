<?php

namespace App\Filament\User\Resources\ArchivedEmailResource\Pages;

use App\Filament\User\Resources\ArchivedEmailResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewArchivedEmail extends ViewRecord
{
    protected static string $resource = ArchivedEmailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
