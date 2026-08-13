<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\EventParticipant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Agreguje diety z kart uczestników (do decyzji biura na Podsumowaniu).
 */
final class EventDietSummaryService
{
    /**
     * @return array{lines: list<string>, summary: string, count: int}
     */
    public function forEvent(Event $event): array
    {
        if (! Schema::hasTable('event_participants') || ! Schema::hasColumn('event_participants', 'diet')) {
            return ['lines' => [], 'summary' => '', 'count' => 0];
        }

        /** @var Collection<int, string> $diets */
        $diets = EventParticipant::query()
            ->where('event_id', $event->id)
            ->whereNotNull('diet')
            ->where('diet', '!=', '')
            ->pluck('diet')
            ->map(fn ($diet): string => trim((string) $diet))
            ->filter()
            ->values();

        if ($diets->isEmpty()) {
            return ['lines' => [], 'summary' => '', 'count' => 0];
        }

        $grouped = $diets
            ->groupBy(fn (string $diet): string => mb_strtolower($diet))
            ->map(fn (Collection $group): array => [
                'label' => (string) $group->first(),
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->values();

        $lines = $grouped
            ->map(fn (array $row): string => $row['count'].' × '.$row['label'])
            ->all();

        return [
            'lines' => $lines,
            'summary' => implode(', ', $lines),
            'count' => $diets->count(),
        ];
    }
}
