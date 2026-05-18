<?php

namespace App\Filament\User\Resources\ManualInsertResource\Pages;

use App\Filament\User\Resources\ManualInsertResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;

class ListManualInserts extends ListRecords
{
    protected static string $resource = ManualInsertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getMaxContentWidth(): MaxWidth|string|null                                  // allarga la tabella a tutta pagina
    {
        return MaxWidth::Full;
    }
}
