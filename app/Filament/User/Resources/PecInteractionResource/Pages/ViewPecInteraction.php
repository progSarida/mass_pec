<?php

namespace App\Filament\User\Resources\PecInteractionResource\Pages;

use App\Filament\User\Resources\PecInteractionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPecInteraction extends ViewRecord
{
    protected static string $resource = PecInteractionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\EditAction::make(),
        ];
    }
}
