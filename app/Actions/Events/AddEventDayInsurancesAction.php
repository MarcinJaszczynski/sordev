<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\EventDayInsurance;
use App\Models\Insurance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dodaje produkty ubezpieczenia na wskazany dzień imprezy.
 *
 * Idempotentne: pomija istniejące pary (event, day, insurance_id).
 * Nie usuwa pozycji i nie rusza is_done / finansów — sync kosztorysu jest po stronie UI.
 */
final class AddEventDayInsurancesAction
{
    /**
     * @param  list<int|string>  $insuranceIds
     * @return list<EventDayInsurance>
     */
    public function __invoke(Event $event, int $day, array $insuranceIds): array
    {
        if ($day < 1 || $day > $event->resolveCoreProgramDaysCount()) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $insuranceIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($ids === []) {
            return [];
        }

        $validIds = Insurance::query()
            ->active()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($validIds === []) {
            return [];
        }

        /** @var list<EventDayInsurance> $created */
        $created = [];

        DB::transaction(function () use ($event, $day, $validIds, &$created): void {
            foreach ($validIds as $insuranceId) {
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
}
