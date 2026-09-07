<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EventAnalyticsPhase;
use App\Enums\ProfitRecognitionMode;
use App\Models\Event;
use App\Models\EventSettlement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ExecutiveProfitLossService
{
    public function __construct(
        private readonly EventProfitRecognitionService $recognition,
    ) {}

    /**
     * @param  array{
     *     date_from?: ?string,
     *     date_to?: ?string,
     *     date_axis?: ?string,
     *     phase?: ?string,
     *     event_status?: ?string,
     *     status?: ?string,
     *     template_id?: int|string|null,
     *     client?: ?string,
     *     recognition_mode?: ?string,
     *     search?: ?string
     * }  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function eventRows(array $filters = []): Collection
    {
        $mode = ProfitRecognitionMode::tryFrom((string) ($filters['recognition_mode'] ?? 'auto'))
            ?? ProfitRecognitionMode::Auto;

        return $this->filteredEventsQuery($filters)
            ->with(['latestSettlement.costs', 'eventTemplate'])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn (Event $event): array => $this->recognition->forEvent($event, $mode)->toArray())
            ->sortByDesc('net_result_pln')
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, float|int>
     */
    public function summarize(Collection $rows): array
    {
        $revenue = round((float) $rows->sum('revenue_pln'), 2);
        $costs = round((float) $rows->sum('costs_pln'), 2);
        $paidRevenue = round((float) $rows->sum('revenue_paid_pln'), 2);
        $paidCosts = round((float) $rows->sum('cost_paid_pln'), 2);

        return [
            'events' => $rows->count(),
            'revenue_pln' => $revenue,
            'costs_pln' => $costs,
            'net_result_pln' => round($revenue - $costs, 2),
            'revenue_paid_pln' => $paidRevenue,
            'cost_paid_pln' => $paidCosts,
            'cost_planned_pln' => round((float) $rows->sum('cost_planned_pln'), 2),
            'cost_outstanding_pln' => round((float) $rows->sum('cost_outstanding_pln'), 2),
            'revenue_due_pln' => round((float) $rows->sum('revenue_due_pln'), 2),
            'receivables_pln' => round((float) $rows->sum('receivables_pln'), 2),
            'payables_pln' => round((float) $rows->sum('payables_pln'), 2),
            'cash_balance_pln' => round($paidRevenue - $paidCosts, 2),
            'avg_margin_percent' => $revenue > 0
                ? round((($revenue - $costs) / $revenue) * 100, 1)
                : null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function aggregateByTemplate(Collection $rows): array
    {
        return $rows
            ->groupBy(fn (array $row): string => (string) ($row['template_id'] ?? 'none'))
            ->map(function (Collection $group): array {
                $revenue = round((float) $group->sum('revenue_pln'), 2);
                $costs = round((float) $group->sum('costs_pln'), 2);
                $net = round($revenue - $costs, 2);

                return [
                    'key' => $group->first()['template_id'] ?? null,
                    'label' => $group->first()['template_name'] ?? 'Bez szablonu',
                    'events' => $group->count(),
                    'revenue_pln' => $revenue,
                    'costs_pln' => $costs,
                    'net_result_pln' => $net,
                    'margin_percent' => $revenue > 0 ? round(($net / $revenue) * 100, 1) : null,
                ];
            })
            ->sortByDesc('net_result_pln')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function aggregateByClient(Collection $rows): array
    {
        return $rows
            ->groupBy(fn (array $row): string => mb_strtolower(trim((string) ($row['client_name'] ?? ''))) ?: 'none')
            ->map(function (Collection $group): array {
                $revenue = round((float) $group->sum('revenue_pln'), 2);
                $costs = round((float) $group->sum('costs_pln'), 2);
                $net = round($revenue - $costs, 2);

                return [
                    'label' => $group->first()['client_name'] ?? 'Bez klienta',
                    'events' => $group->count(),
                    'revenue_pln' => $revenue,
                    'costs_pln' => $costs,
                    'net_result_pln' => $net,
                    'margin_percent' => $revenue > 0 ? round(($net / $revenue) * 100, 1) : null,
                ];
            })
            ->sortByDesc('net_result_pln')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function aggregateByPhase(Collection $rows): array
    {
        return collect(EventAnalyticsPhase::cases())
            ->map(function (EventAnalyticsPhase $phase) use ($rows): array {
                $group = $rows->where('phase', $phase->value);
                $revenue = round((float) $group->sum('revenue_pln'), 2);
                $costs = round((float) $group->sum('costs_pln'), 2);
                $net = round($revenue - $costs, 2);

                return [
                    'phase' => $phase->value,
                    'label' => $phase->label(),
                    'events' => $group->count(),
                    'revenue_pln' => $revenue,
                    'costs_pln' => $costs,
                    'net_result_pln' => $net,
                    'margin_percent' => $revenue > 0 ? round(($net / $revenue) * 100, 1) : null,
                ];
            })
            ->filter(fn (array $row): bool => $row['events'] > 0)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array{month: string, label: string, revenue: float, costs: float, net: float, events: int}>
     */
    public function monthlyTrend(array $filters = [], int $months = 12): array
    {
        $start = now()->subMonths($months - 1)->startOfMonth();
        $buckets = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i);
            $key = $month->format('Y-m');
            $buckets[$key] = [
                'month' => $key,
                'label' => $month->translatedFormat('M Y'),
                'revenue' => 0.0,
                'costs' => 0.0,
                'net' => 0.0,
                'events' => 0,
            ];
        }

        $trendFilters = $filters;
        if (empty($trendFilters['date_from'])) {
            $trendFilters['date_from'] = $start->toDateString();
        }

        foreach ($this->eventRows($trendFilters) as $row) {
            $monthKey = isset($row['event_date'])
                ? substr((string) $row['event_date'], 0, 7)
                : null;
            if (! $monthKey || ! isset($buckets[$monthKey])) {
                continue;
            }

            $buckets[$monthKey]['revenue'] += (float) $row['revenue_pln'];
            $buckets[$monthKey]['costs'] += (float) $row['costs_pln'];
            $buckets[$monthKey]['net'] += (float) $row['net_result_pln'];
            $buckets[$monthKey]['events']++;
        }

        foreach ($buckets as &$bucket) {
            $bucket['revenue'] = round($bucket['revenue'], 2);
            $bucket['costs'] = round($bucket['costs'], 2);
            $bucket['net'] = round($bucket['net'], 2);
        }

        return array_values($buckets);
    }

    /**
     * @deprecated Używane tylko przez starsze testy — preferuj eventRows()+recognition.
     *
     * @return array<string, mixed>
     */
    public function mapSettlementRow(EventSettlement $settlement): array
    {
        $event = $settlement->event;
        if (! $event) {
            return [
                'settlement_id' => $settlement->id,
                'event_id' => null,
                'revenue_pln' => 0.0,
                'costs_pln' => 0.0,
                'net_result_pln' => 0.0,
                'receivables_pln' => 0.0,
                'payables_pln' => 0.0,
            ];
        }

        $event->setRelation('latestSettlement', $settlement);

        return $this->recognition->forEvent($event)->toArray();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function filteredEventsQuery(array $filters): Builder
    {
        $dateAxis = (string) ($filters['date_axis'] ?? 'start_date');
        if (! in_array($dateAxis, ['start_date', 'end_date'], true)) {
            $dateAxis = 'start_date';
        }

        $query = Event::query();

        if (! empty($filters['date_from'])) {
            $query->whereDate($dateAxis, '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate($dateAxis, '<=', $filters['date_to']);
        }

        if (! empty($filters['event_statuses']) && is_array($filters['event_statuses'])) {
            $query->whereIn('status', $filters['event_statuses']);
        } elseif (! empty($filters['event_status'])) {
            $query->where('status', $filters['event_status']);
        }

        if (! empty($filters['template_id'])) {
            if ($filters['template_id'] === 'none') {
                $query->whereNull('event_template_id');
            } else {
                $query->where('event_template_id', (int) $filters['template_id']);
            }
        }

        if (! empty($filters['client'])) {
            if ($filters['client'] === '__none__') {
                $query->where(function (Builder $inner): void {
                    $inner->whereNull('client_name')
                        ->orWhere('client_name', '')
                        ->orWhere('client_name', '—');
                });
            } else {
                $term = '%'.$filters['client'].'%';
                $query->where('client_name', 'like', $term);
            }
        }

        if (! empty($filters['status'])) {
            $query->whereHas('latestSettlement', fn (Builder $settlement) => $settlement->where('status', $filters['status']));
        }

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term)
                    ->orWhere('client_name', 'like', $term);
            });
        }

        if (! empty($filters['phase'])) {
            $phase = EventAnalyticsPhase::tryFrom((string) $filters['phase']);
            if ($phase) {
                $this->applyPhaseConstraint($query, $phase);
            }
        }

        return $query;
    }

    protected function applyPhaseConstraint(Builder $query, EventAnalyticsPhase $phase): void
    {
        $today = now()->toDateString();

        match ($phase) {
            EventAnalyticsPhase::Cancelled => $query->whereIn('status', [
                Event::STATUS_CANCELLED,
                Event::STATUS_PENDING_CANCELLATION,
            ]),
            EventAnalyticsPhase::Future => $query
                ->whereNotIn('status', [Event::STATUS_CANCELLED, Event::STATUS_PENDING_CANCELLATION])
                ->where(function (Builder $q) use ($today): void {
                    $q->where(function (Builder $inner) use ($today): void {
                        $inner->whereNotNull('end_date')
                            ->whereDate('end_date', '>=', $today)
                            ->whereDate('start_date', '>', $today);
                    })->orWhere(function (Builder $inner) use ($today): void {
                        $inner->whereNull('end_date')
                            ->where(function (Builder $dates) use ($today): void {
                                $dates->whereNull('start_date')
                                    ->orWhereDate('start_date', '>', $today);
                            });
                    });
                }),
            EventAnalyticsPhase::Completed => $query
                ->whereNotIn('status', [Event::STATUS_CANCELLED, Event::STATUS_PENDING_CANCELLATION])
                ->where(function (Builder $q) use ($today): void {
                    $q->where(function (Builder $inner) use ($today): void {
                        $inner->whereNotNull('end_date')->whereDate('end_date', '<', $today);
                    })->orWhere(function (Builder $inner) use ($today): void {
                        $inner->whereNull('end_date')
                            ->whereNotNull('start_date')
                            ->whereDate('start_date', '<', $today);
                    });
                }),
            EventAnalyticsPhase::InProgress => $query
                ->whereNotIn('status', [Event::STATUS_CANCELLED, Event::STATUS_PENDING_CANCELLATION])
                ->where(function (Builder $q) use ($today): void {
                    $q->where(function (Builder $inner) use ($today): void {
                        $inner->whereNotNull('end_date')
                            ->whereDate('end_date', '>=', $today)
                            ->where(function (Builder $start) use ($today): void {
                                $start->whereNull('start_date')
                                    ->orWhereDate('start_date', '<=', $today);
                            });
                    })->orWhere(function (Builder $inner) use ($today): void {
                        $inner->whereNull('end_date')
                            ->whereDate('start_date', '=', $today);
                    });
                }),
        };
    }
}
