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

    /**
     * Ritorna l'etichetta semantica in base alla direzione e alla profondità
     */
    public function getRelationLabel(string $direction, int $depth = 1): string
    {
        $label = match($direction) {
            'parent' => match($this) {
                self::LINK => 'Collegato a',
                self::REPLY => 'Risposta a',
                self::FORWARD => 'Inoltro di',
            },
            'child' => match($this) {
                self::LINK => 'Collegato da',
                self::REPLY => 'Risposta ricevuta',
                self::FORWARD => 'Inoltrato a',
            },
            default => 'Correlato',
        };

        return $depth > 1 ? "{$label} (livello {$depth} rete)" : $label;
    }

    /**
     * Colore del badge Filament
     */
    public function getRelationColor(string $direction, int $depth = 1): string
    {
        return match($direction) {
            'parent' => match($this) {
                self::LINK => $depth > 1 ? 'gray' : 'info',
                self::REPLY => $depth > 1 ? 'gray' : 'success',
                self::FORWARD => $depth > 1 ? 'gray' : 'warning',
            },
            'child' => match($this) {
                self::LINK => $depth > 1 ? 'gray' : 'info',
                self::REPLY => $depth > 1 ? 'gray' : 'success',
                self::FORWARD => $depth > 1 ? 'gray' : 'warning',
            },
            default => 'gray',
        };
    }

    /**
     * Icona rappresentativa della relazione
     */
    public function getRelationIcon(string $direction, int $depth = 1): string
    {
        return match($direction) {
            'parent' => match($this) {
                self::LINK => 'heroicon-m-link',
                self::REPLY => 'heroicon-m-arrow-uturn-right',
                self::FORWARD => 'heroicon-m-arrow-top-right-on-square',
            },
            'child' => match($this) {
                self::LINK => 'heroicon-m-link',
                self::REPLY => 'heroicon-m-arrow-uturn-left',
                self::FORWARD => 'heroicon-m-arrow-right-start-on-rectangle',
            },
            default => 'heroicon-m-link',               // 'heroicon-m-share'
        };
    }
}
