<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Źródło ceny hotelu w kalkulacji oferty dla klienta.
 *
 * offer      = zamrożona cena z szablonu / katalogu (S)
 * negotiated = cena uzgodniona z hotelem (P) — też baza planowanego w settlement
 */
final class HotelCalculationSource
{
    public const OFFER = 'offer';

    public const NEGOTIATED = 'negotiated';

    public static function normalize(?string $source): string
    {
        return $source === self::NEGOTIATED ? self::NEGOTIATED : self::OFFER;
    }

    public static function isOffer(?string $source): bool
    {
        return self::normalize($source) === self::OFFER;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::OFFER => 'Cena z szablonu (oferta)',
            self::NEGOTIATED => 'Cena uzgodniona z hotelem',
        ];
    }

    public static function label(?string $source): string
    {
        $normalized = self::normalize($source);

        return self::options()[$normalized] ?? $normalized;
    }
}
