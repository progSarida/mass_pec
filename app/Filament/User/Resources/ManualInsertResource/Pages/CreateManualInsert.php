<?php

namespace App\Filament\User\Resources\ManualInsertResource\Pages;

use App\Filament\User\Resources\ManualInsertResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateManualInsert extends CreateRecord
{
    protected static string $resource = ManualInsertResource::class;

    public function getTitle(): string
    {
        return "Nuovo inserimento manuale";
    }

    protected function beforeCreate(): void
    {
        //
    }
}
