<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Checklist zgód uczestnika (wycieczki szkolne).
 */
final class EventParticipantConsents
{
    public const TERMS = 'terms';

    public const INSURANCE = 'insurance';

    public const RODO = 'rodo';

    public const IMAGE = 'image';

    public const MEDICAL = 'medical';

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::TERMS => 'Regulamin / warunki udziału',
            self::INSURANCE => 'Ubezpieczenie',
            self::RODO => 'RODO',
            self::IMAGE => 'Wizerunek',
            self::MEDICAL => 'Karta / dane medyczne',
        ];
    }

    /** @return list<string> */
    public static function requiredKeys(): array
    {
        return [self::TERMS, self::INSURANCE, self::RODO];
    }

    /** @return list<string> */
    public static function allKeys(): array
    {
        return array_keys(self::labels());
    }

    /**
     * @param  array<string, mixed>|null  $consents
     * @return array<string, bool>
     */
    public static function checklist(?array $consents): array
    {
        $consents = is_array($consents) ? $consents : [];
        $out = [];
        foreach (self::allKeys() as $key) {
            $out[$key] = filled($consents[$key] ?? null);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $consents
     */
    public static function hasRequired(?array $consents): bool
    {
        $checklist = self::checklist($consents);
        foreach (self::requiredKeys() as $key) {
            if (! ($checklist[$key] ?? false)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, bool>  $flags
     * @param  array<string, mixed>|null  $existing
     * @return array<string, string|null>
     */
    public static function applyFlags(array $flags, ?array $existing = null, ?string $acceptedAt = null): array
    {
        $existing = is_array($existing) ? $existing : [];
        $at = $acceptedAt ?? now()->toIso8601String();
        $out = [];

        foreach (self::allKeys() as $key) {
            $want = (bool) ($flags[$key] ?? false);
            if ($want) {
                $out[$key] = filled($existing[$key] ?? null) ? (string) $existing[$key] : $at;
            } else {
                $out[$key] = null;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $consents
     */
    public static function completedCount(?array $consents): int
    {
        return count(array_filter(self::checklist($consents)));
    }

    /**
     * Mapowanie legacy meta flow (AgreementFlow) → flagi kanoniczne uczestnika.
     *
     * flow.data → rodo; communication zostaje tylko w meta umowy (nie w rosterze).
     *
     * @param  array<string, mixed>|null  $flowConsents
     * @return array<string, bool>
     */
    public static function flagsFromAgreementFlow(?array $flowConsents): array
    {
        $flowConsents = is_array($flowConsents) ? $flowConsents : [];

        return [
            self::TERMS => (bool) ($flowConsents['terms'] ?? false),
            self::INSURANCE => (bool) ($flowConsents['insurance'] ?? false),
            self::RODO => (bool) ($flowConsents['data'] ?? $flowConsents['rodo'] ?? false),
            self::IMAGE => (bool) ($flowConsents['image'] ?? false),
            self::MEDICAL => (bool) ($flowConsents['medical'] ?? false),
        ];
    }
}
