<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

final class CalendarDay
{
    /**
     * Zaległość względem dnia kalendarzowego (dziś jeszcze nie jest „po terminie”).
     */
    public static function isBeforeToday(mixed $date): bool
    {
        if (! $date) {
            return false;
        }

        $value = $date instanceof CarbonInterface
            ? $date->copy()
            : Carbon::parse($date);

        return $value->toDateString() < now()->toDateString();
    }

    public static function isToday(mixed $date): bool
    {
        if (! $date) {
            return false;
        }

        $value = $date instanceof CarbonInterface
            ? $date->copy()
            : Carbon::parse($date);

        return $value->toDateString() === now()->toDateString();
    }
}
