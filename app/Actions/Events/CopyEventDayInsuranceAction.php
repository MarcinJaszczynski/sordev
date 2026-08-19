<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Models\EventDayInsurance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Kopiuje przypisanie produkt→dzień na kolejne dni horyzontu imprezy.
 *
 * Idempotentne: pomija istniejące pary (event, day, insurance_id).
 * Nie kopiuje is_done ani finansów — nowy wiersz kosztorysu powstaje przy sync.
 */
final class CopyEventDayInsuranceAction
{
    public const MODE_NEXT = 'next';

    public const MODE_REMAINING = 'remaining';

    /**
     * @return list<EventDayInsurance>
     */
    public function __invoke(EventDayInsurance $source, string $mode = self::MODE_NEXT): array
    {
        if (! in_array($mode, [self::MODE_NEXT, self::MODE_REMAINING], true)) {
            throw new InvalidArgumentException("Nieznany tryb kopiowania ubezpieczenia: {$mode}");
        }

        $insuranceId = (int) ($source->insurance_id ?? 0);
        if ($insuranceId <= 0) {
            return [];
        }

        $event = $source->event ?? $source->event()->firstOrFail();
        $fromDay = max(1, (int) $source->day);
        $maxDay = $event->resolveCoreProgramDaysCount();

        $targetDays = match ($mode) {
            self::MODE_NEXT => $fromDay < $maxDay ? [$fromDay + 1] : [],
            self::MODE_REMAINING => $this->daysAfter($fromDay, $maxDay),
        };

        if ($targetDays === []) {
            return [];
        }

        /** @var list<EventDayInsurance> $created */
        $created = [];

        DB::transaction(function () use ($event, $insuranceId, $targetDays, &$created): void {
            foreach ($targetDays as $day) {
                $exists = EventDayInsurance::query()
                    ->where('event_id', $event->id)
                    ->where('day', $day)
                    ->where('insurance_id', $insuranceId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $attributes = [
                    'event_id' => $event->id,
                    'day' => $day,
                    'insurance_id' => $insuranceId,
                ];

                if (Schema::hasColumn('event_day_insurance', 'is_done')) {
                    $attributes['is_done'] = false;
                }

                $created[] = EventDayInsurance::query()->create($attributes);
            }
        });

        return $created;
    }

    /**
     * @return list<int>
     */
    private function daysAfter(int $fromDay, int $maxDay): array
    {
        if ($fromDay >= $maxDay) {
            return [];
        }

        $days = [];
        for ($day = $fromDay + 1; $day <= $maxDay; $day++) {
            $days[] = $day;
        }

        return $days;
    }
}
