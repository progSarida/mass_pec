<?php

namespace App\Filament\User\Resources\PecInteractionResource\Pages;

use App\Filament\User\Resources\PecInteractionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPecInteractions extends ListRecords
{
    protected static string $resource = PecInteractionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\CreateAction::make(),
        ];
    }
}
