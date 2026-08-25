<?php

namespace App\Enums;

enum ChecklistItemInputType: string
{
    case CheckOnly = 'check_only';
    case Number = 'number';
    case Text = 'text';

    public function label(): string
    {
        return match ($this) {
            self::CheckOnly => 'Tylko odhaczenie',
            self::Number => 'Liczba (np. licznik km)',
            self::Text => 'Tekst',
        };
    }

    public function requiresValue(): bool
    {
        return $this !== self::CheckOnly;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
