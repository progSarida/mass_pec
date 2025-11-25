<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MailProtocolType: string implements HasLabel
{
    case SMTP = 'smtp';
    case IMAP = "imap";
    case POP3 = "pop3";

    public function getLabel(): string
    {
        return match($this) {
            self::SMTP => 'SMTP',
            self::IMAP => 'IMAP',
            self::POP3 => 'POP3',
        };
    }
}
