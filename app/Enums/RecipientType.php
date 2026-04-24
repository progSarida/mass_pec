<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RecipientType: string implements HasLabel
{
    case PERSON = "person";
    case ENTERPRISE = 'enterprise';
    case PUBLIC = 'public';
    public function getLabel(): string
    {
        return match($this) {
            self::PERSON => 'Soggetto privato',
            self::ENTERPRISE => 'Impresa',
            self::PUBLIC => 'Pubblica amministrazione',
        };
    }

    public function isPublic(): bool
    {
        return match($this) {
            self::PERSON => false,
            self::ENTERPRISE => false,
            self::PUBLIC => true,
        };
    }

    public function isPrivate(): bool
    {
        return match($this) {
            self::PERSON => true,
            self::ENTERPRISE => true,
            self::PUBLIC => false,
        };
    }

    public static function getOptions(): array
    {
        return collect(self::cases())->mapWithKeys(fn($case) => [$case->value => $case->getLabel()])->toArray();
    }

}
