<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RelationshipType: string implements HasLabel
{
    case LINK = "link";
    case REPLY = "reply";
    case FORWARD = "forward";

    public function getLabel(): string
    {
        return match($this) {
            self::LINK => 'Collegamento',
            self::REPLY => 'Risposta',
            self::FORWARD => 'Inoltro',
        };
    }

    public function parentLabel(): string
    {
        return match($this) {
            self::LINK => 'Collegato a',
            self::REPLY => 'Risposta a',
            self::FORWARD => 'Inoltro di',
        };
    }

    public function childLabel(): string
    {
        return match($this) {
            self::LINK => 'Collegato',
            self::REPLY => 'Risposta',
            self::FORWARD => 'Inoltro',
        };
    }

    public function parentColor(): string
    {
        return match($this) {
            self::LINK => 'gray',
            self::REPLY => 'danger',
            self::FORWARD => 'warning',
        };
    }

    public function childColor(): string
    {
        return match($this) {
            self::LINK => 'gray',
            self::REPLY => 'info',
            self::FORWARD => 'info',
        };
    }
}
