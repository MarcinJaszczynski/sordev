<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Str;

final class ParticipantNameMatcher
{
    public static function normalizeKey(string $value): string
    {
        $value = Str::ascii(mb_strtolower(trim($value)));

        return preg_replace('/\s+/', ' ', $value) ?? '';
    }

    public static function fullName(?string $firstName, ?string $lastName): string
    {
        return trim(implode(' ', array_filter([
            trim((string) $firstName),
            trim((string) $lastName),
        ])));
    }

    /**
     * @return array{first_name: ?string, last_name: ?string}
     */
    public static function parseFullName(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['first_name' => null, 'last_name' => null];
        }

        $segments = preg_split('/\s+/', $name, 2) ?: [];

        return [
            'first_name' => $segments[0] ?? null,
            'last_name' => $segments[1] ?? null,
        ];
    }

    public static function namesMatch(string $left, string $right): bool
    {
        $leftKey = self::normalizeKey($left);
        $rightKey = self::normalizeKey($right);

        if ($leftKey === '' || $rightKey === '') {
            return false;
        }

        if ($leftKey === $rightKey) {
            return true;
        }

        return Str::contains($leftKey, $rightKey) || Str::contains($rightKey, $leftKey);
    }

    public static function birthDatesMatch(?CarbonInterface $left, ?CarbonInterface $right): string
    {
        if (! $left || ! $right) {
            return 'unknown';
        }

        return $left->toDateString() === $right->toDateString() ? 'match' : 'mismatch';
    }
}
