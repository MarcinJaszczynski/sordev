<?php

namespace App\Services;

class PriceRoundingService
{
    /**
     * Serwis odpowiedzialny za jednolite zasady zaokrąglania cen przed zapisem.
     * - PLN: zaokrąglenie do najbliższej 5 (ceil)
     * - inne waluty: zaokrąglenie do najbliższej 10 (ceil)
     */
    /**
     * Rounds a price per person according to currency rules
     * PLN  -> round up to nearest 5
     * OTHER -> round up to nearest 10 (as requested)
     */
    public static function roundPerPerson(?float $raw, string $currencyCode): ?float
    {
        if ($raw === null) return null;
        if ($raw <= 0) return 0.0;
        $upper = strtoupper($currencyCode);
        if ($upper === 'PLN') {
            return (float)(ceil($raw / 5) * 5);
        }
        // inne waluty: w górę do 10
        return (float)(ceil($raw / 10) * 10);
    }
}
