<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasIcon;

enum FlowType: string implements HasLabel, HasIcon, HasColor
{
    case RECEIVED = 'received';
    case ISSUED = "issued";
    case INTERNAL = "internal";

    public function getLabel(): string
    {
        return match($this) {
            self::RECEIVED => 'Ricevuta',
            self::ISSUED => 'Inviata',
            self::INTERNAL => 'Interna',
        };
    }

    public function getAcronym(): string
    {
        return match($this) {
            self::RECEIVED => 'Ric.',
            self::ISSUED => 'Inv.',
            self::INTERNAL => 'Int.',
        };
    }

    public function getIcon(): string
    {
        return match($this) {
            self::RECEIVED => 'fluentui-arrow-download-20',
            self::ISSUED => 'fluentui-arrow-export-up-20',
            self::INTERNAL => 'fluentui-arrow-rotate-clockwise-20',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::RECEIVED => 'info',
            self::ISSUED => 'success',
            self::INTERNAL => 'zynch',
        };
    }

    public function showArchive(): bool
    {
        return match($this) {
            self::RECEIVED => true,
            self::ISSUED => true,
            self::INTERNAL => false,
        };
    }

    public function getLetter(): string
    {
        return match($this) {
            self::RECEIVED => 'E',
            self::ISSUED => 'U',
            self::INTERNAL => 'I',
        };
    }
}
