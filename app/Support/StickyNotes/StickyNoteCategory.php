<?php

namespace App\Support\StickyNotes;

class StickyNoteCategory
{
    public const GENERAL = 'general';

    public const HOTEL = 'hotel';

    public const GRATIS = 'gratis';

    public const PICKUP = 'pickup';

    public const TRANSPORT = 'transport';

    public const FINANCE = 'finance';

    public static function labels(): array
    {
        return [
            self::GENERAL => 'Ogólne',
            self::HOTEL => 'Hotel / nocleg',
            self::GRATIS => 'Opiekun/Inne',
            self::PICKUP => 'Podjazd / zbiórka',
            self::TRANSPORT => 'Transport',
            self::FINANCE => 'Finanse',
        ];
    }

    public static function options(): array
    {
        return self::labels();
    }

    public static function label(?string $category): string
    {
        return self::labels()[$category] ?? self::labels()[self::GENERAL];
    }

    public static function isValid(?string $category): bool
    {
        return filled($category) && array_key_exists($category, self::labels());
    }

    public static function normalize(?string $category): string
    {
        return self::isValid($category) ? $category : self::GENERAL;
    }
}
