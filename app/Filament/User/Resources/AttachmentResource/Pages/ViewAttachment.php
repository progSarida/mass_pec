<?php

namespace App\Filament\User\Resources\AttachmentResource\Pages;

use App\Filament\User\Resources\AttachmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAttachment extends ViewRecord
{
    protected static string $resource = AttachmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
