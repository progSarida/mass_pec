<?php

namespace App\Filament\User\Resources\AttachmentResource\Pages;

use App\Filament\User\Resources\AttachmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewAttachment extends ViewRecord
{
    protected static string $resource = AttachmentResource::class;

    public function getTitle(): string | Htmlable
    {
        return 'Visualizza ' . $this->record->filename;
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
