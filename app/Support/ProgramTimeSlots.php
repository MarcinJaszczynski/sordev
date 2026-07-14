<?php

namespace App\Support;

class ProgramTimeSlots
{
    /** @return array<string, string> */
    public static function options(int $stepMinutes = 15): array
    {
        $slots = [];

        for ($minutes = 0; $minutes < 24 * 60; $minutes += max(1, $stepMinutes)) {
            $label = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
            $slots[$label] = $label;
        }

        return $slots;
    }
}
