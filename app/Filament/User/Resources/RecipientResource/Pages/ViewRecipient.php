<?php

namespace App\Filament\User\Resources\RecipientResource\Pages;

use App\Filament\User\Resources\RecipientResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewRecipient extends ViewRecord
{
    protected static string $resource = RecipientResource::class;

    public function getTitle(): string | Htmlable
    {
        return $this->record->description;
    }

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
