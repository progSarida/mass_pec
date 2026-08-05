<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasIcon;

enum ManageRegistryType: string implements HasLabel, HasIcon, HasColor
{
    case NONE = 'none';
    case TODO = 'todo';
    case DONE = 'done';

    public function getLabel(): string
    {
        return match($this) {
            self::NONE => 'Nessuna',
            self::TODO => 'Da evadere',
            self::DONE => 'Evasa',
            default => '',
        };
    }

    public function getFilterLabel(): string
    {
        return match($this) {
            self::NONE => 'Da non evadere',
            self::TODO => 'Da evadere',
            self::DONE => 'Evase',
            default => '',
        };
    }

    public function getIcon(): string
    {
        return match($this) {
            self::NONE => '',
            self::TODO => 'heroicon-s-cog-6-tooth',
            self::DONE => 'heroicon-s-check-circle',
            default => '',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::NONE => 'gray',
            self::TODO => 'warning',
            self::DONE => 'success',
            default => '',
        };
    }

    public function showType(): bool                                                // mostra il tipo di gestione nel form della voce del protocollo
    {
        return match($this) {
            self::NONE => false,
            self::TODO => true,
            self::DONE => true,
            default => false,
        };
    }

    public function showToAssign(): bool                                            // le opzioni disponibili per l'assegnazione alla protocollazione
    {
        return match($this) {
            self::NONE => true,
            self::TODO => true,
            self::DONE => false,
            default => false,
        };
    }

    public function showManage(): bool                                              // mostra il pulsante per la modifica della gestione della voce
    {
        return match($this) {
            self::NONE => false,
            self::TODO => true,
            self::DONE => false,
            default => false,
        };
    }

    public function showToUpdate(): bool                                            // le opzioni disponibili per l'assegnazione alla modifica
    {
        return match($this) {
            self::NONE => true,
            self::TODO => true,
            self::DONE => true,
            default => false,
        };
    }

    public function showOptions(): array                                            // le opzioni disponibili per l'assegnazione alla modifica in base al valore corrente
    {
        return match($this) {
            self::NONE => [
                self::NONE,
                self::TODO,
                self::DONE,
            ],
            self::TODO => [
                // self::NONE,
                self::TODO,
                self::DONE,
            ],
            self::DONE => [
                // self::NONE,
                self::TODO,
                self::DONE,
            ],
        };
    }
}
