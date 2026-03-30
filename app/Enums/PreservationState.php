<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PreservationState: string implements HasLabel
{
    case SENT = 'sent';
    case NOT_SENT = 'not_sent';

    public function getLabel(): string
    {
        return match($this) {
            self::SENT => 'Inviato',
            self::NOT_SENT => 'Non inviato',
        };
    }
}
