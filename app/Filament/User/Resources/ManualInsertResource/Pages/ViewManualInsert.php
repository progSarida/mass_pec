<?php

namespace App\Filament\User\Resources\ManualInsertResource\Pages;

use App\Filament\User\Resources\ManualInsertResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewManualInsert extends ViewRecord
{
    protected static string $resource = ManualInsertResource::class;

    public function getTitle(): string
    {
        return "Visualizza inserimento manuale";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\EditAction::make(),
            ])
            ->label('Operazioni')
            ->icon('heroicon-m-ellipsis-vertical')
            ->color('info')
            ->button(),
        ];
    }
}
