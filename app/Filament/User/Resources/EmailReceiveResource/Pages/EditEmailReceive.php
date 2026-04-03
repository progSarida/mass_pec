<?php

namespace App\Filament\User\Resources\EmailReceiveResource\Pages;

use App\Filament\User\Resources\EmailReceiveResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmailReceive extends EditRecord
{
    protected static string $resource = EmailReceiveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
