<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventProgramPoint;

class EventHotelServiceDuplicator
{
    /**
     * Klonuje usługę hotelu na pozostałe dni imprezy (pomija dzień źródłowy).
     *
     * @return list<EventProgramPoint>
     */
    public function duplicateToAllDays(EventProgramPoint $source, Event $event): array
    {
        $source->loadMissing(['currency', 'templatePoint']);

        $maxDay = max(1, (int) ($event->duration_days ?? 1));
        $sourceDay = (int) ($source->day ?? 1);
        $created = [];

        for ($day = 1; $day <= $maxDay; $day++) {
            if ($day === $sourceDay) {
                continue;
            }

            $clone = $source->replicate([
                'paid_price',
                'calculated_price',
                'planned_price',
            ]);
            $clone->day = $day;
            $clone->order = $this->nextOrderForDay($event, $day);
            $clone->save();

            $created[] = $clone->fresh(['currency', 'templatePoint']);
        }

        return $created;
    }

    protected function nextOrderForDay(Event $event, int $day): int
    {
        $max = EventProgramPoint::query()
            ->where('event_id', $event->getKey())
            ->where('day', $day)
            ->max('order');

        return ((int) $max) + 1;
    }
}
