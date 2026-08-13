<?php

namespace App\Services;

use App\Models\Insurance;
use Illuminate\Support\Collection;

/**
 * Jedno źródło prawdy dla kosztu ubezpieczenia NNW (dzień → polisa).
 *
 * Reguła biznesowa:
 *  - koszt = Σ(cena/os. polisy × liczba dni-przypisań) × (płacący + gratis),
 *  - gratis jest chroniony (wchodzi w koszt), ale nie płaci —
 *    cena za osobę w ofercie = suma końcowa ÷ tylko płacący (robi to EventCostCalculator).
 *
 * Flagi per_day / per_person nie zmieniają wzoru (przypisanie jest już per dzień);
 * polisa wchodzi do kosztu gdy jest włączona i aktywna.
 */
class InsuranceCostCalculator
{
    public static function isChargeable(?Insurance $insurance): bool
    {
        if (! $insurance) {
            return false;
        }

        return (bool) $insurance->insurance_enabled && (bool) $insurance->active;
    }

    public static function unitPricePln(?Insurance $insurance): float
    {
        if (! self::isChargeable($insurance)) {
            return 0.0;
        }

        return max(0.0, (float) ($insurance->price_per_person ?? 0));
    }

    /**
     * Koszt jednego przypisania dzień→polisa (z gratisami).
     */
    public static function dayAssignmentCost(?Insurance $insurance, int $payingCount, int $gratisCount = 0): float
    {
        $headcount = max(0, $payingCount) + max(0, $gratisCount);
        if ($headcount <= 0) {
            return 0.0;
        }

        $unit = self::unitPricePln($insurance);
        if ($unit <= 0) {
            return 0.0;
        }

        return round($unit * $headcount, 2);
    }

    /**
     * Suma kosztów z kolekcji przypisań dzień→polisa (szablon lub impreza).
     *
     * @param  iterable<mixed>  $dayInsurances  elementy z day, insurance (relacja lub lazy)
     */
    public static function totalForDayAssignments(iterable $dayInsurances, int $payingCount, int $gratisCount = 0): float
    {
        $headcount = max(0, $payingCount) + max(0, $gratisCount);
        if ($headcount <= 0) {
            return 0.0;
        }

        $items = $dayInsurances instanceof Collection
            ? $dayInsurances
            : collect($dayInsurances);

        $unitSum = 0.0;
        foreach ($items as $dayInsurance) {
            $day = (int) ($dayInsurance->day ?? 0);
            if ($day <= 0) {
                continue;
            }

            $unitSum += self::unitPricePln($dayInsurance->insurance ?? null);
        }

        if ($unitSum <= 0) {
            return 0.0;
        }

        return round($unitSum * $headcount, 2);
    }
}
