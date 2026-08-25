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
    public function __invoke(
        Event $event,
        int $day,
        array $insuranceIds,
        ?int $policyId = null,
    ): array {
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

        $resolvedPolicyId = $policyId;
        if ($resolvedPolicyId !== null
            && $resolvedPolicyId > 0
            && Schema::hasColumn('event_day_insurance', 'event_insurance_policy_id')) {
            // keep
        } else {
            $resolvedPolicyId = null;
        }

        /** @var list<EventDayInsurance> $created */
        $created = [];

        DB::transaction(function () use ($event, $day, $validIds, $resolvedPolicyId, &$created): void {
            foreach ($validIds as $insuranceId) {
                $existing = EventDayInsurance::query()
                    ->where('event_id', $event->id)
                    ->where('day', $day)
                    ->where('insurance_id', $insuranceId)
                    ->first();

                if ($existing) {
                    if ($resolvedPolicyId
                        && Schema::hasColumn('event_day_insurance', 'event_insurance_policy_id')
                        && ! $existing->event_insurance_policy_id) {
                        $existing->update(['event_insurance_policy_id' => $resolvedPolicyId]);
                    }

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

                if ($resolvedPolicyId && Schema::hasColumn('event_day_insurance', 'event_insurance_policy_id')) {
                    $attributes['event_insurance_policy_id'] = $resolvedPolicyId;
                }

                $created[] = EventDayInsurance::query()->create($attributes);
            }
        });

        return $created;
    }
}
