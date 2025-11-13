<?php

namespace App\Filament\User\Resources\InMailResource\Pages;

use App\Filament\User\Resources\InMailResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewInMail extends ViewRecord
{
    protected static string $resource = InMailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
