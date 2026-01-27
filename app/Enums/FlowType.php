<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FlowType: string implements HasLabel
{
    case RECEIVED = 'received';
    case ISSUED = "issued";
    case INTERNAL = "internal";

    public function getLabel(): string
    {
        return match($this) {
            self::RECEIVED => 'Ricevuto',
            self::ISSUED => 'Emesso',
            self::INTERNAL => 'Interno',
        };
    }
}
