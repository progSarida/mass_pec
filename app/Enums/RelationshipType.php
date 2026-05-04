<?php

namespace App\Enums;

enum RelationshipType: string
{
    case REPLY = "reply";
    case FORWARD = "forward";

    public function parentLabel(): string
    {
        return match($this) {
            self::FORWARD => 'Inoltro di',
            self::REPLY => 'Risposta a',
        };
    }

    public function childLabel(): string
    {
        return match($this) {
            self::FORWARD => 'Inoltro',
            self::REPLY => 'Risposta',
        };
    }

    public function parentColor(): string
    {
        return match($this) {
            self::FORWARD => 'info',
            self::REPLY => 'danger',
        };
    }

    public function childColor(): string
    {
        return match($this) {
            self::FORWARD => 'gray',
            self::REPLY => 'warning',
        };
    }
}
