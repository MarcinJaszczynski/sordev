<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Bramki środowiskowe dla narzędzi deweloperskich i symulatora płatności.
 */
final class SecurityEnvironment
{
    public static function allowsDevTools(): bool
    {
        return app()->environment(['local', 'testing']);
    }

    public static function allowsFakePayments(): bool
    {
        return self::allowsDevTools()
            && (string) config('payments.driver') === 'fake'
            && ! app()->isProduction();
    }
}
