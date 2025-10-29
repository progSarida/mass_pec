<?php

namespace App\Filament\User\Resources\AttachmentResource\Pages;

use App\Filament\User\Resources\AttachmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;

class ListAttachments extends ListRecords
{
    protected static string $resource = AttachmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->modalHeading('Carica Nuovo Allegato')
                ->modalWidth('md')
                ->label('Carica allegato')
                ->icon('heroicon-o-plus')
                ->createAnother(false),
        ];
    }

    public function getMaxContentWidth(): MaxWidth|string|null                                  // allarga la tabella a tutta pagina
    {
        return MaxWidth::Full;
    }
}
