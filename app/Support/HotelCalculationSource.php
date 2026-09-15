<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Źródło ceny hotelu w kalkulacji oferty dla klienta.
 *
 * offer      = warstwa S (cena ofertowa ze szablonu/katalogu) — idzie do ceny całej imprezy
 * negotiated = warstwa P (uzgodniona z hotelem) — settlement zawsze; cena imprezy tylko gdy wybrane
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
            self::OFFER => 'Cena z szablonu (oferta S)',
            self::NEGOTIATED => 'Cena uzgodniona z hotelem (P)',
        ];
    }

    public static function label(?string $source): string
    {
        $normalized = self::normalize($source);

        return self::options()[$normalized] ?? $normalized;
    }
}
