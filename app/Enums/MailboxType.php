<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MailboxType: string implements HasLabel
{
    case RECEIVED = 'received';
    case ISSUED = "issued";

    public function getLabel(): string
    {
        return match($this) {
            self::RECEIVED => 'In arrivo',
            self::ISSUED => 'Inviata',
        };
    }

    public function getParameter(): string
    {
        return match($this) {
            self::RECEIVED => 'INBOX',
            self::ISSUED => 'INBOX.Inviata',
        };
    }
}
