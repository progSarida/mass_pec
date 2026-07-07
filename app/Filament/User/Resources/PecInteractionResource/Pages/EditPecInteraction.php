<?php

namespace App\Filament\User\Resources\PecInteractionResource\Pages;

use App\Filament\User\Resources\PecInteractionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPecInteraction extends EditRecord
{
    protected static string $resource = PecInteractionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\ViewAction::make(),
            // Actions\DeleteAction::make(),
        ];
    }
}
