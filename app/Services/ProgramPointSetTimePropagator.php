<?php

namespace App\Services;

use App\Models\EventProgramPoint;
use Carbon\Carbon;

class ProgramPointSetTimePropagator
{
    /**
     * Propaguje godziny z punktu nadrzędnego (set) do podpunktów bez własnych godzin.
     * Przy wielu podpunktach dzieli okno czasu rodzica równomiernie.
     */
    public function propagateFromParent(EventProgramPoint $parent): int
    {
        if ($parent->parent_id !== null) {
            return 0;
        }

        if (blank($parent->start_time) && blank($parent->end_time)) {
            return 0;
        }

        $parent->loadMissing('children');

        if ($parent->children->isEmpty()) {
            return 0;
        }

        $children = $parent->children->sortBy('order')->values();

        if ($children->count() === 1) {
            return $this->inheritParentWindow($children->first(), $parent) ? 1 : 0;
        }

        if (filled($parent->start_time) && filled($parent->end_time)) {
            return $this->distributeEvenlyAmongChildren($parent, $children);
        }

        $updated = 0;

        foreach ($children as $child) {
            if ($this->inheritParentWindow($child, $parent)) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, EventProgramPoint>  $children
     */
    protected function distributeEvenlyAmongChildren(EventProgramPoint $parent, $children): int
    {
        $startMin = $this->timeToMinutes((string) $parent->start_time);
        $endMin = $this->timeToMinutes((string) $parent->end_time);

        if ($endMin <= $startMin) {
            return 0;
        }

        $count = max(1, $children->count());
        $duration = $endMin - $startMin;
        $updated = 0;

        foreach ($children as $index => $child) {
            if (! $this->shouldAutoAssignChildTimes($child, $parent)) {
                continue;
            }

            $slotStart = (int) round($startMin + ($duration * $index) / $count);
            $slotEnd = (int) round($startMin + ($duration * ($index + 1)) / $count);

            if ($slotEnd <= $slotStart) {
                $slotEnd = $slotStart + 1;
            }

            $child->update([
                'start_time' => $this->minutesToTime($slotStart),
                'end_time' => $this->minutesToTime($slotEnd),
            ]);

            $updated++;
        }

        return $updated;
    }

    protected function shouldAutoAssignChildTimes(EventProgramPoint $child, EventProgramPoint $parent): bool
    {
        if (blank($child->start_time) && blank($child->end_time)) {
            return true;
        }

        return $child->start_time === $parent->start_time
            && $child->end_time === $parent->end_time;
    }

    protected function inheritParentWindow(EventProgramPoint $child, EventProgramPoint $parent): bool
    {
        $payload = [];

        if (blank($child->start_time) && filled($parent->start_time)) {
            $payload['start_time'] = $parent->start_time;
        }

        if (blank($child->end_time) && filled($parent->end_time)) {
            $payload['end_time'] = $parent->end_time;
        }

        if ($payload === []) {
            return false;
        }

        $child->update($payload);

        return true;
    }

    protected function timeToMinutes(string $time): int
    {
        $time = trim($time);

        if ($time === '') {
            return 0;
        }

        try {
            return Carbon::createFromFormat('H:i', strlen($time) > 5 ? substr($time, 0, 5) : $time)->hour * 60
                + Carbon::createFromFormat('H:i', strlen($time) > 5 ? substr($time, 0, 5) : $time)->minute;
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function minutesToTime(int $minutes): string
    {
        $minutes = max(0, min($minutes, 23 * 60 + 59));

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
