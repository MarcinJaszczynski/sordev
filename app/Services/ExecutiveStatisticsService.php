<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\Reservation;
use Illuminate\Support\Collection;

class ExecutiveStatisticsService
{
    /**
     * @return array<string, int|float>
     */
    public function overview(): array
    {
        return [
            'events_total' => Event::query()->count(),
            'events_active' => Event::query()->whereIn('status', [
                Event::STATUS_CONFIRMED,
                Event::STATUS_PROVISIONAL_RESERVATION,
                Event::STATUS_TO_SETTLE,
                Event::STATUS_OFFER,
            ])->count(),
            'events_settled' => Event::query()->where('status', Event::STATUS_SETTLED)->count(),
            'open_settlements' => EventSettlement::query()->whereIn('status', ['draft', 'active', 'pilot_settled'])->count(),
            'closed_settlements' => EventSettlement::query()->where('status', 'closed')->count(),
            'pending_reservations' => Reservation::query()->where('status', 'pending')->count(),
            'participants_upcoming' => (int) Event::query()
                ->whereDate('start_date', '>=', now()->toDateString())
                ->sum('participant_count'),
        ];
    }

    /**
     * @return Collection<int, array{status: string, label: string, count: int}>
     */
    public function eventsByStatus(): Collection
    {
        $labels = [
            Event::STATUS_OFFER => 'Oferta',
            Event::STATUS_PROVISIONAL_RESERVATION => 'Rezerwacja',
            Event::STATUS_CONFIRMED => 'Potwierdzona',
            Event::STATUS_TO_SETTLE => 'Do rozliczenia',
            Event::STATUS_SETTLED => 'Rozliczona',
            Event::STATUS_CANCELLED => 'Anulowana',
        ];

        $counts = Event::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return collect($labels)->map(fn (string $label, string $status): array => [
            'status' => $status,
            'label' => $label,
            'count' => (int) ($counts[$status] ?? 0),
        ])->values();
    }

    /**
     * @return array<int, array{month: string, label: string, count: int}>
     */
    public function eventsPerMonth(int $months = 12): array
    {
        $start = now()->subMonths($months - 1)->startOfMonth();
        $buckets = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i);
            $key = $month->format('Y-m');
            $buckets[$key] = [
                'month' => $key,
                'label' => $month->translatedFormat('M Y'),
                'count' => 0,
            ];
        }

        $rows = Event::query()
            ->whereDate('start_date', '>=', $start)
            ->get(['start_date']);

        foreach ($rows as $event) {
            $key = $event->start_date?->format('Y-m');
            if ($key && isset($buckets[$key])) {
                $buckets[$key]['count']++;
            }
        }

        return array_values($buckets);
    }

    /**
     * @return Collection<int, array{status: string, label: string, count: int}>
     */
    public function settlementsByStatus(): Collection
    {
        $counts = EventSettlement::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return collect(EventSettlement::$statuses)->map(fn (string $label, string $status): array => [
            'status' => $status,
            'label' => $label,
            'count' => (int) ($counts[$status] ?? 0),
        ])->values();
    }
}
