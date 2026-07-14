<?php

namespace App\Support;

/**
 * Walidacja numerów telefonu — format międzynarodowy (+48, +44 itd.), ze spacjami i myślnikami.
 */
final class PhoneValidation
{
    public const MAX_LENGTH = 50;

    /** @var string Prefiks +, cyfry, spacje, nawiasy, myślniki, kropki, ukośniki. */
    public const REGEX = '/^[+]?[\d\s().\/-]{6,50}$/';

    /**
     * @return list<string|\Illuminate\Validation\Rules\Unique>
     */
    public static function optionalRules(): array
    {
        return ['nullable', 'string', 'max:'.self::MAX_LENGTH, 'regex:'.self::REGEX];
    }

    /**
     * @return list<string>
     */
    public static function requiredRules(): array
    {
        return ['required', 'string', 'max:'.self::MAX_LENGTH, 'regex:'.self::REGEX];
    }
}
