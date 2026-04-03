<?php

namespace App\Filament\User\Resources\EmailReceiveResource\Pages;

use App\Filament\User\Resources\EmailReceiveResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewEmailReceive extends ViewRecord
{
    protected static string $resource = EmailReceiveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
