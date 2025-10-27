<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ConnectionSafetyType: string implements HasLabel
{
    case SSL = 'ssl';
    case TLS = 'tls';
    case START = 'start';

    public function getLabel(): string
    {
        return match($this) {
            self::SSL => 'SSL',
            self::TLS => 'TLS',
            self::START => 'STARTTLS',
        };
    }
}
