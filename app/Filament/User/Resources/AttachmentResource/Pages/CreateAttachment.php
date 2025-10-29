<?php

namespace App\Filament\User\Resources\AttachmentResource\Pages;

use App\Filament\User\Resources\AttachmentResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateAttachment extends CreateRecord
{
    protected static string $resource = AttachmentResource::class;
}
