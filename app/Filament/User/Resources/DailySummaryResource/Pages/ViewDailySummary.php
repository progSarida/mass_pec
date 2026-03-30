<?php

namespace App\Filament\User\Resources\DailySummaryResource\Pages;

use App\Filament\User\Resources\DailySummaryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDailySummary extends ViewRecord
{
    protected static string $resource = DailySummaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
