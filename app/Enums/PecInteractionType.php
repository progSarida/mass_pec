<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasIcon;

enum PecInteractionType: string implements HasLabel, HasIcon, HasColor
{
    case DOWNLOAD = 'download';
    case RECEIPT = "receipt";
    case PEC = "pec";
    case SHIPMENT = "shipment";
    case SHIPMENT_RECEIPT = "shipment_receipt";

    public function getLabel(): string
    {
        return match($this) {
            self::DOWNLOAD => 'Download pec',
            self::RECEIPT => 'Download ricevute',
            self::PEC => 'Invio PEC',
            self::SHIPMENT_RECEIPT => 'Download ricevute spedizione',
            self::SHIPMENT => 'Invio spedizione',
        };
    }

    public function getIcon(): string
    {
        return match($this) {
            self::DOWNLOAD => 'fluentui-arrow-download-20',
            self::RECEIPT => 'fluentui-receipt-search-20-o',
            self::PEC => 'fluentui-arrow-export-up-20',
            self::SHIPMENT => 'fluentui-truck-20',
            self::SHIPMENT_RECEIPT => 'fluentui-receipt-search-20-o',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::DOWNLOAD => 'info',
            self::RECEIPT => 'warning',
            self::PEC => 'success',
            self::SHIPMENT => 'primary',
            self::SHIPMENT_RECEIPT => 'primary',
        };
    }
}
