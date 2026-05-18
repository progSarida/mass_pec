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
    case REPLY = "reply";
    case FORWARD = "forward";

    public function getLabel(): string
    {
        return match($this) {
            self::SHIPMENT => 'Spedizioni',
            self::IN_MAIL => 'Pec Massiva',
            self::DOWNLOAD_EMAIL => 'PEC in arrivo',
            self::SEND_EMAIL => 'PEC inviata',
            self::MANUAL => 'Inserimento manuale',
            self::FORWARD => 'Inoltro',
            self::REPLY => 'Risposta',
        };
    }

    public function showRich(): bool
    {
        return match($this) {
            self::SHIPMENT => false,
            self::IN_MAIL => false,
            self::DOWNLOAD_EMAIL => false,
            self::SEND_EMAIL => false,
            self::MANUAL => true,
            self::FORWARD => false,
            self::REPLY => true,
        };
    }

    public function showArea(): bool
    {
        return match($this) {
            self::SHIPMENT => true,
            self::IN_MAIL => true,
            self::DOWNLOAD_EMAIL => true,
            self::SEND_EMAIL => true,
            self::MANUAL => false,
            self::FORWARD => true,
            self::REPLY => false,
        };
    }
}
