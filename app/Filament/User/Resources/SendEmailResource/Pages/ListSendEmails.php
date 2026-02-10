<?php

namespace App\Filament\User\Resources\SendEmailResource\Pages;

use App\Filament\User\Resources\SendEmailResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;

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

    public function getMaxContentWidth(): MaxWidth|string|null                                  // allarga la tabella a tutta pagina
    {
        return MaxWidth::Full;
    }
}
