<?php

namespace App\Filament\User\Resources\RegistryResource\Pages;

use App\Filament\User\Resources\RegistryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewRegistry extends ViewRecord
{
    protected static string $resource = RegistryResource::class;

    public function getTitle(): string | Htmlable
    {
        return $this->record->subject;
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
