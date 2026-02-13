<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasIcon;

enum PecStatus: string implements HasLabel, HasIcon, HasColor
{
    case WAITING = 'waiting';
    case ACCEPTED = 'accepted';
    case NOT_ACCEPTED = 'not_accepted';
    case DELIVERED = 'delivered';
    case NOT_DELIVERED = 'not_delivered';
    case ANOMALY = 'anomaly';

    public function getLabel(): string
    {
        return match($this) {
            self::WAITING => 'In attesa di ricevuta',
            self::ACCEPTED => 'Accettata',
            self::NOT_ACCEPTED => 'Non accettata',
            self::DELIVERED => 'Consegnata',
            self::NOT_DELIVERED => 'Non consegnata',
            self::ANOMALY => 'Anomalia',
        };
    }

    // Icone tipi
    public function getIcon(): string
    {
        return match($this) {
            self::WAITING => 'fluentui-mail-pause-20-o',
            self::ACCEPTED => 'fluentui-mail-clock-20-o',
            self::NOT_ACCEPTED => 'fluentui-presence-blocked-20-o',
            self::DELIVERED => 'fluentui-mail-checkmark-20-o',
            self::NOT_DELIVERED => 'fluentui-mail-prohibited-20-o',
            self::ANOMALY => 'fluentui-error-circle-20-o',
        };
    }


    public function getColor(): string
    {
        return match($this) {
            self::WAITING => 'gray',
            self::ACCEPTED => 'info',
            self::NOT_ACCEPTED => 'danger',
            self::DELIVERED => 'success',
            self::NOT_DELIVERED => 'danger',
            self::ANOMALY => 'warning',
        };
    }
}
