<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasIcon;

enum ShipmentErrorType: string implements HasLabel, HasIcon, HasColor
{
    case ANOMALY = 'anomaly';
    case NOT_ACCEPTED = 'not_accepted';
    case NOT_DELIVERED = 'not_delivered';

    public function getLabel(): string
    {
        return match($this) {
            self::ANOMALY => 'Anomalia',
            self::NOT_ACCEPTED => 'Non accettato',
            self::NOT_DELIVERED => 'Non consegnato',
        };
    }

    // Icone tipi
    public function getIcon(): string
    {
        return match($this) {
            self::ANOMALY => 'fluentui-error-circle-20-o',
            self::NOT_ACCEPTED => 'fluentui-error-circle-settings-20-o',
            self::NOT_DELIVERED => 'fluentui-mail-error-20-o',
        };
    }


    public function getColor(): string
    {
        return match($this) {
            self::ANOMALY => 'info',
            self::NOT_ACCEPTED => 'warning',
            self::NOT_DELIVERED => 'danger',
        };
    }
}
