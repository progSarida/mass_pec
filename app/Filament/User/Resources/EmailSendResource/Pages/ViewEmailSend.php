<?php

namespace App\Filament\User\Resources\EmailSendResource\Pages;

use App\Filament\User\Resources\EmailSendResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewEmailSend extends ViewRecord
{
    protected static string $resource = EmailSendResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
