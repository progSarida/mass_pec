<?php

namespace App\Filament\User\Resources\SendEmailResource\Pages;

use App\Filament\User\Resources\SendEmailResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSendEmails extends ListRecords
{
    protected static string $resource = SendEmailResource::class;

    public function getTitle(): string
    {
        return "Email in uscita";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
