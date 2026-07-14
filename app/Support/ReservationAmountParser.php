<?php

namespace App\Support;

/**
 * Parsuje kwotę rezerwacji — obsługa notacji „18/os” (× liczba uczestników).
 */
final class ReservationAmountParser
{
    private const PER_PERSON_PATTERN = '/\/(\s*)?(os\.?|osoba|osoby|osób|pax|person)/iu';

    public static function resolve(mixed $input, int $participantCount = 1): ?float
    {
        if ($input === null || $input === '') {
            return null;
        }

        $participantCount = max(1, $participantCount);

        if (is_int($input) || is_float($input)) {
            return round((float) $input, 2);
        }

        if (is_numeric($input) && ! is_string($input)) {
            return round((float) $input, 2);
        }

        $raw = trim((string) $input);
        if ($raw === '') {
            return null;
        }

        if (preg_match(self::PER_PERSON_PATTERN, $raw)) {
            $unit = self::extractLeadingNumber($raw);
            if ($unit === null) {
                return null;
            }

            return round($unit * $participantCount, 2);
        }

        $amount = self::extractLeadingNumber($raw);

        return $amount !== null ? round($amount, 2) : null;
    }

    private static function extractLeadingNumber(string $raw): ?float
    {
        $withoutSuffix = preg_replace(self::PER_PERSON_PATTERN, '', $raw) ?? $raw;
        $withoutSuffix = trim($withoutSuffix);

        if (preg_match('/-?\d+(?:[.,]\d+)?/', $withoutSuffix, $matches) !== 1) {
            return null;
        }

        $normalized = str_replace(',', '.', $matches[0]);

        return is_numeric($normalized) ? (float) $normalized : null;
    }
}
