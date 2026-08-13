<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Jednolity copy CTA płatności online we wszystkich portalach / signed linkach.
 */
final class PaymentCta
{
    public static function label(bool $includeSimulatorHint = false): string
    {
        $label = 'Zapłać online';

        if ($includeSimulatorHint && self::isFakeDriver()) {
            return $label.' (symulator)';
        }

        return $label;
    }

    public static function isFakeDriver(): bool
    {
        return (string) config('payments.driver', 'fake') === 'fake';
    }
}
