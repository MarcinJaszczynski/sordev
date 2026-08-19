<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\EventDayInsurance;
use App\Models\EventTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wgrywa ubezpieczenia dniowe z szablonu na imprezę.
 *
 * Idempotentne: pomija istniejące pary (event, day, insurance_id).
 * Nie wykracza poza horyzont trwania imprezy i nie nadpisuje is_done.
 */
final class SyncEventDayInsurancesFromTemplateAction
{
    /**
     * @return list<EventDayInsurance>
     */
    public function __invoke(Event $event, EventTemplate $template): array
    {
        if (
            ! Schema::hasTable('event_day_insurance')
            || ! Schema::hasTable('event_template_day_insurance')
        ) {
            return [];
        }

        $maxDay = $this->eventDurationDays($event);
        if ($maxDay < 1) {
            return [];
        }

        /** @var list<EventDayInsurance> $created */
        $created = [];

        DB::transaction(function () use ($event, $template, $maxDay, &$created): void {
            $rows = $template->dayInsurances()
                ->whereNotNull('insurance_id')
                ->where('day', '>=', 1)
                ->where('day', '<=', $maxDay)
                ->orderBy('day')
                ->orderBy('id')
                ->get();

            foreach ($rows as $row) {
                $day = (int) $row->day;
                $insuranceId = (int) $row->insurance_id;
                if ($day < 1 || $insuranceId <= 0) {
                    continue;
                }

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
     * Dni trwania samej imprezy — bez duration szablonu.
     * Szablon 5-dniowy na 4-dniowej imprezie nie dokłada dnia 5.
     */
    private function eventDurationDays(Event $event): int
    {
        $fromDates = 1;
        if ($event->start_date && $event->end_date) {
            $fromDates = max(1, (int) $event->start_date->copy()->startOfDay()
                ->diffInDays($event->end_date->copy()->startOfDay()) + 1);
        }

        return max(1, (int) ($event->duration_days ?? 0), $fromDates);
    }
}
