<?php

namespace App\Support;

/**
 * Wspólna walidacja opcjonalnego PESEL (11 cyfr) — użytkownik, impreza, kontrahent-pilot.
 */
final class PilotIdentityValidation
{
    /**
     * @return list<string>
     */
    public static function optionalPeselRules(): array
    {
        return ['nullable', 'digits:11'];
    }
}
