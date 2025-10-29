<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MailType: string implements HasLabel
{
    case MAIL = 'mail';
    case PEC = "pec";
    case REM = "rem";
    case CORPORATE = "corporate";
    case ENCRYPTED = "encrypted";
    case OTHER = "other";

    public function getLabel(): string
    {
        return match($this) {
            self::MAIL => 'Mail',
            self::PEC => 'PEC',
            self::REM => 'REM',
            self::CORPORATE => 'Aziendale',
            self::ENCRYPTED => 'Crittografata',
            self::OTHER => 'Altro',
        };
    }
}
