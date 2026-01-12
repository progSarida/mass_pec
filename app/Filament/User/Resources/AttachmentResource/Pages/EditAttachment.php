<?php

namespace App\Filament\User\Resources\AttachmentResource\Pages;

use App\Filament\User\Resources\AttachmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditAttachment extends EditRecord
{
    protected static string $resource = AttachmentResource::class;

    public function getTitle(): string | Htmlable
    {
        return 'Modifica ' . $this->record->filename;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
