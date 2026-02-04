<?php

namespace App\Filament\User\Resources\SignatureResource\Pages;

use App\Filament\User\Resources\SignatureResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSignature extends ViewRecord
{
    protected static string $resource = SignatureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Indietro')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
            Actions\EditAction::make(),
        ];
    }
}
