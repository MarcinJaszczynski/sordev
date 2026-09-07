<?php

declare(strict_types=1);

namespace App\Support\Tasks;

use Carbon\CarbonInterface;

/**
 * Domyślny termin nowego zadania: koniec dnia utworzenia (lub wskazanego dnia).
 */
final class TaskDueDates
{
    public static function defaultForNew(?CarbonInterface $day = null): CarbonInterface
    {
        return ($day ?? now())->copy()->endOfDay();
    }
}
