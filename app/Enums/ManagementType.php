<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ManagementType: string implements HasLabel
{
    case IAB = 'iab';

    public function getLabel(): string
    {
        return match($this) {
            self::IAB => 'IAB',
        };
    }
}
