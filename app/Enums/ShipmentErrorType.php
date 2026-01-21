<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ShipmentErrorType: string implements HasLabel
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
}
