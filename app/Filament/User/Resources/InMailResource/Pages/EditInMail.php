<?php

namespace App\Filament\User\Resources\InMailResource\Pages;

use App\Filament\User\Resources\InMailResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditInMail extends EditRecord
{
    protected static string $resource = InMailResource::class;

    public function getTitle(): string | Htmlable
    {
        return $this->record->subject;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
