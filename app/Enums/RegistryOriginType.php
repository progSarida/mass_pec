<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RegistryOriginType: string implements HasLabel
{
    case SHIPMENT = 'shipment';
    case IN_MAIL = "in_mail";
    case DOWNLOAD_EMAIL = "download_email";
    case SEND_EMAIL = "send_email";
    case MANUAL = "manual";

    public function getLabel(): string
    {
        return match($this) {
            self::SHIPMENT => 'Spedizioni',
            self::IN_MAIL => 'Pec Massiva',
            self::DOWNLOAD_EMAIL => 'Posta in arrivo',
            self::SEND_EMAIL => 'Posta inviata',
            self::MANUAL => 'Manuale',
        };
    }
}
