<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\VendorInvoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ExecutiveProfitLossService
{
    /**
     * @param  array{
     *     date_from?: ?string,
     *     date_to?: ?string,
     *     status?: ?string,
     *     search?: ?string
     * }  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function eventRows(array $filters = []): Collection
    {
        $query = EventSettlement::query()
            ->with(['event'])
            ->whereHas('event');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereHas('event', fn (Builder $event) => $event->whereDate('start_date', '>=', $filters['date_from']));
        }

        if (! empty($filters['date_to'])) {
            $query->whereHas('event', fn (Builder $event) => $event->whereDate('start_date', '<=', $filters['date_to']));
        }

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->whereHas('event', fn (Builder $event) => $event
                ->where('name', 'like', $term)
                ->orWhere('code', 'like', $term));
        }

        return $query
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn (EventSettlement $settlement): array => $this->mapSettlementRow($settlement))
            ->sortByDesc('net_result_pln')
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, float|int>
     */
    public function summarize(Collection $rows): array
    {
        return [
            'events' => $rows->count(),
            'revenue_pln' => round($rows->sum('revenue_pln'), 2),
            'costs_pln' => round($rows->sum('costs_pln'), 2),
            'net_result_pln' => round($rows->sum('net_result_pln'), 2),
            'receivables_pln' => round($rows->sum('receivables_pln'), 2),
            'payables_pln' => round($rows->sum('payables_pln'), 2),
            'cash_balance_pln' => round($rows->sum('revenue_pln') - $rows->sum('costs_pln') - $rows->sum('payables_pln'), 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mapSettlementRow(EventSettlement $settlement): array
    {
        $event = $settlement->event;
        $revenue = $this->resolveRevenuePln($settlement);
        $costs = (float) $settlement->actual_cost_pln;
        $receivables = max(0, (float) $settlement->participant_due_pln - (float) $settlement->participant_paid_pln);
        $payables = $this->resolvePayablesPln($event);

        $plannedMargin = null;
        $plannedMarginPercent = null;

        if ($event) {
            try {
                $presenter = EventCalculationPresenter::for($event);
                $plannedMargin = $presenter->marginDeltaPln();
                $plannedMarginPercent = $presenter->marginDeltaPercent();
            } catch (\Throwable) {
            }
        }

        return [
            'settlement_id' => $settlement->id,
            'event_id' => $event?->id,
            'event_code' => $event?->code,
            'event_name' => $event?->name,
            'event_date' => $event?->start_date?->format('Y-m-d'),
            'status' => $settlement->status,
            'status_label' => EventSettlement::$statuses[$settlement->status] ?? $settlement->status,
            'revenue_pln' => round($revenue, 2),
            'costs_pln' => round($costs, 2),
            'net_result_pln' => round($revenue - $costs, 2),
            'receivables_pln' => round($receivables, 2),
            'payables_pln' => round($payables, 2),
            'planned_margin_pln' => $plannedMargin !== null ? round($plannedMargin, 2) : null,
            'planned_margin_percent' => $plannedMarginPercent,
        ];
    }

    protected function resolveRevenuePln(EventSettlement $settlement): float
    {
        $fromParticipants = (float) $settlement->participant_paid_pln;
        $fromContracts = 0.0;

        if ($settlement->event_id && Schema::hasTable('contracts')) {
            $fromContracts = (float) Contract::query()
                ->where('event_id', $settlement->event_id)
                ->whereNotIn('status', ['cancelled', 'template'])
                ->sum('amount_paid');
        }

        return $fromParticipants + $fromContracts;
    }

    protected function resolvePayablesPln(?Event $event): float
    {
        if (! $event || ! Schema::hasTable('vendor_invoices')) {
            return 0.0;
        }

        return (float) VendorInvoice::query()
            ->where('event_id', $event->id)
            ->where('payment_status', 'due')
            ->where('approval_status', 'approved')
            ->sum('gross_amount');
    }

    /**
     * @return array<int, array{month: string, revenue: float, costs: float, net: float}>
     */
    public function monthlyTrend(int $months = 12): array
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

        $settlements = EventSettlement::query()
            ->with('event')
            ->whereHas('event', fn (Builder $query) => $query->whereDate('start_date', '>=', $start))
            ->get();

        foreach ($settlements as $settlement) {
            $monthKey = $settlement->event?->start_date?->format('Y-m');
            if (! $monthKey || ! isset($buckets[$monthKey])) {
                continue;
            }

            $row = $this->mapSettlementRow($settlement);
            $buckets[$monthKey]['revenue'] += $row['revenue_pln'];
            $buckets[$monthKey]['costs'] += $row['costs_pln'];
            $buckets[$monthKey]['net'] += $row['net_result_pln'];
            $buckets[$monthKey]['events']++;
        }

        foreach ($buckets as &$bucket) {
            $bucket['revenue'] = round($bucket['revenue'], 2);
            $bucket['costs'] = round($bucket['costs'], 2);
            $bucket['net'] = round($bucket['net'], 2);
        }

        return array_values($buckets);
    }
}
