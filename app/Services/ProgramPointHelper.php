<?php

namespace App\Services;

use Illuminate\Support\Collection;

class ProgramPointHelper
{
    /**
     * Filtruje kolekcję punktów programu zostawiając tylko te, które powinny być
     * uwzględnione w kalkulacji. Obsługuje zarówno modele z pivot (->pivot->include_in_calculation)
     * jak i modele, które mają bezpośrednio pole include_in_calculation.
     *
     * @param Collection $points
     * @return Collection
     */
    public static function filterIncluded(Collection $points): Collection
    {
        return $points->filter(function ($p) {
            // jeśli istnieje pivot (np. przy many-to-many z szablonem), użyj flagi z pivotu
            if (isset($p->pivot) && isset($p->pivot->include_in_calculation)) {
                return (bool)$p->pivot->include_in_calculation;
            }

            // w innych przypadkach oczekujemy pola w samym modelu (np. EventProgramPoint)
            if (isset($p->include_in_calculation)) {
                return (bool)$p->include_in_calculation;
            }

            // domyślnie: uwzględniaj
            return true;
        });
    }

    /**
     * Suma wybranej kolumny po przefiltrowanych punktach (included only)
     *
     * @param Collection $points
     * @param string $field
     * @return float|int
     */
    public static function sumIncluded(Collection $points, string $field = 'total_price')
    {
        return (float) self::filterIncluded($points)->sum($field);
    }

    /**
     * Liczba punktów uwzględnionych w kalkulacji
     */
    public static function countIncluded(Collection $points): int
    {
        return self::filterIncluded($points)->count();
    }
}
