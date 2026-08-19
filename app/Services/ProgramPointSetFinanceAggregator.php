<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlementCost;
use App\Support\EventProgramPointPricesSummary;
use App\Support\ProgramPointSetFinanceSummary;
use App\Support\SetFinanceCurrencyBuckets;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

final class ProgramPointSetFinanceAggregator
{
    public function __construct(
        private readonly ProgramPointSettlementCostCache $costCache,
        private readonly EventPaymentScheduleService $paymentSchedule,
    ) {}

    public function summarize(
        EventProgramPoint $parent,
        Event $event,
        ?int $participantCount = null,
    ): ProgramPointSetFinanceSummary {
        $participantCount = max(1, (int) ($participantCount ?? $event->participant_count ?? 1));
        $members = $this->members($parent);

        $calcBuckets = new SetFinanceCurrencyBuckets;
        $plannedBuckets = new SetFinanceCurrencyBuckets;
        $paidBuckets = new SetFinanceCurrencyBuckets;
        $advanceBuckets = new SetFinanceCurrencyBuckets;
        $advanceRowCount = 0;

        $officeBuckets = new SetFinanceCurrencyBuckets;
        $pilotBuckets = new SetFinanceCurrencyBuckets;

        $paymentCount = 0;
        $paidByValues = collect();
        $scheduleRows = collect();
        $hasSettlement = false;

        foreach ($members as $point) {
            $baseCost = $this->costCache->baseCost((int) $point->id);
            $paymentRows = $this->costCache->paymentRows((int) $point->id);

            if ($baseCost || $paymentRows->isNotEmpty()) {
                $hasSettlement = true;
            }

            $currency = $baseCost?->plannedCurrency ?? $point->currency;
            $convertToPln = (bool) ($baseCost?->planned_convert_to_pln ?? $point->convert_to_pln ?? true);

            $calcBuckets->add((float) $point->resolveCalculationTotal($participantCount), $currency, $convertToPln);

            $plannedAmount = (float) ($baseCost?->planned_amount ?? $point->planned_price ?? 0);
            if ($plannedAmount <= 0) {
                $plannedAmount = (float) ($point->planned_price ?? 0);
            }
            $plannedBuckets->add($plannedAmount, $currency, $convertToPln);

            $this->accumulatePaidBuckets($paidBuckets, $baseCost, $paymentRows, $currency, $convertToPln);

            $pointAdvanceRows = $paymentRows->filter(fn (EventSettlementCost $row): bool => (float) ($row->advance_amount ?? 0) > 0
                || in_array((string) $row->advance_type, ['advance', 'deposit'], true));
            $advanceAmount = (float) $pointAdvanceRows->sum(fn (EventSettlementCost $row) => (float) ($row->advance_amount ?? 0));
            if ($advanceAmount <= 0 && $baseCost) {
                $advanceAmount = (float) ($baseCost->advance_amount ?? 0);
            }
            if ($advanceAmount > 0) {
                $advanceBuckets->add($advanceAmount, $currency, $convertToPln);
                $advanceRowCount += $pointAdvanceRows->count() > 0
                    ? $pointAdvanceRows->count()
                    : 1;
            }

            if ($baseCost) {
                $paidByValues->push($baseCost->paid_by ?? 'office');
                $this->accumulatePayerBucket($officeBuckets, $pilotBuckets, $plannedAmount, $currency, $convertToPln, (string) ($baseCost->paid_by ?? 'office'));
            }

            foreach ($paymentRows as $row) {
                $paymentCount++;
                $paidByValues->push($row->paid_by ?? 'office');
                $rowAmount = (float) ($row->actual_amount ?? $row->planned_amount ?? $row->advance_amount ?? 0);
                $rowCurrency = $row->actualCurrency ?? $row->plannedCurrency ?? $currency;
                $rowConvert = (bool) ($row->planned_convert_to_pln ?? $convertToPln);
                $this->accumulatePayerBucket(
                    $officeBuckets,
                    $pilotBuckets,
                    $rowAmount,
                    $rowCurrency,
                    $rowConvert,
                    (string) ($row->paid_by ?? 'office'),
                );
            }

            $scheduleRows = $scheduleRows->merge(
                $this->paymentSchedule->collectForProgramPoint($point, $event)
            );
        }

        $plannedPlnEquiv = $plannedBuckets->plnEquivalentTotal();
        $paidPlnEquiv = $paidBuckets->plnEquivalentTotal();

        $advanceHtml = null;
        $advanceLabel = $advanceBuckets->formatMixed();

        if ($advanceBuckets->hasAmount()) {
            $paidPart = $paidBuckets->formatMixed();
            $remainingBuckets = clone $plannedBuckets;
            $remainingBuckets->subtract($paidBuckets);
            $remaining = $remainingBuckets->formatMixed();
            $advanceLabelWord = $advanceRowCount > 1 ? 'Zaliczki' : 'Zaliczka';
            $advanceHtml = '💰 '.$advanceLabelWord.' '.e($advanceLabel).' · wpł. '.e($paidPart).' · do dop. '.e($remaining);
        }

        return new ProgramPointSetFinanceSummary(
            calcLabel: $calcBuckets->formatMixed(),
            plannedLabel: $plannedBuckets->formatMixed(),
            paidLabel: $paidBuckets->formatMixed(),
            paidStatus: EventProgramPointPricesSummary::resolvePaidStatus($paidPlnEquiv, $plannedPlnEquiv),
            advanceHtml: $advanceHtml,
            advanceLabel: $advanceBuckets->hasAmount() ? $advanceLabel : null,
            payerLines: $this->buildPayerLines($officeBuckets, $pilotBuckets),
            settlementInfoHtml: $this->buildSettlementInfoHtml($paidByValues, $paymentCount),
            paymentDueLines: $this->buildPaymentDueLines($scheduleRows),
            paymentCount: $paymentCount,
            hasPilotShare: $pilotBuckets->hasAmount(),
            hasOfficeShare: $officeBuckets->hasAmount(),
            hasSettlement: $hasSettlement,
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function collectScheduleRows(EventProgramPoint $parent, Event $event): Collection
    {
        $rows = collect();

        foreach ($this->members($parent) as $point) {
            $rows = $rows->merge($this->paymentSchedule->collectForProgramPoint($point, $event));
        }

        return $rows
            ->sortBy([
                ['due_date', 'asc'],
                ['kind_label', 'asc'],
            ])
            ->values();
    }

    /**
     * @return EloquentCollection<int, EventProgramPoint>
     */
    public function members(EventProgramPoint $parent): EloquentCollection
    {
        $members = new EloquentCollection;

        if ($this->isIncludedMember($parent)) {
            $parent->loadMissing(['currency', 'templatePoint']);
            $members->push($parent);
        }

        $children = EventProgramPoint::query()
            ->where('parent_id', $parent->id)
            ->where('active', true)
            ->where('include_in_calculation', true)
            ->with(['currency', 'templatePoint'])
            ->orderBy('order')
            ->get();

        return $members->merge($children)->values();
    }

    /**
     * @param  EloquentCollection<int, EventProgramPoint>|Collection<int, EventProgramPoint>  $visibleParents
     * @return list<int>
     */
    public function childIdsForParents(Collection|EloquentCollection $visibleParents): array
    {
        $parentIds = $visibleParents
            ->filter(fn (EventProgramPoint $point): bool => $point->parent_id === null)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($parentIds === []) {
            return [];
        }

        return EventProgramPoint::query()
            ->whereIn('parent_id', $parentIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function isIncludedMember(EventProgramPoint $point): bool
    {
        return (bool) $point->active && (bool) $point->include_in_calculation;
    }

    /**
     * @param  EloquentCollection<int, EventSettlementCost>|Collection<int, EventSettlementCost>  $paymentRows
     */
    private function accumulatePaidBuckets(
        SetFinanceCurrencyBuckets $paidBuckets,
        ?EventSettlementCost $baseCost,
        Collection|EloquentCollection $paymentRows,
        ?Currency $fallbackCurrency,
        bool $fallbackConvertToPln,
    ): void {
        $added = false;

        foreach ($paymentRows as $row) {
            $rowCurrency = $row->actualCurrency ?? $row->plannedCurrency ?? $fallbackCurrency;
            $rowConvert = (bool) ($row->planned_convert_to_pln ?? $fallbackConvertToPln);
            $actualAmount = (float) ($row->actual_amount ?? 0);

            if ($actualAmount > 0) {
                $paidBuckets->add($actualAmount, $rowCurrency, $rowConvert);
                $added = true;

                continue;
            }

            if ($row->actual_amount_pln !== null && (float) $row->actual_amount_pln > 0) {
                $paidBuckets->add((float) $row->actual_amount_pln, null, true);
                $added = true;
            }
        }

        if ($added || ! $baseCost) {
            return;
        }

        $baseCurrency = $baseCost->actualCurrency ?? $baseCost->plannedCurrency ?? $fallbackCurrency;
        $baseConvert = (bool) ($baseCost->planned_convert_to_pln ?? $fallbackConvertToPln);
        $baseActual = (float) ($baseCost->actual_amount ?? 0);

        if ($baseActual > 0) {
            $paidBuckets->add($baseActual, $baseCurrency, $baseConvert);

            return;
        }

        if ($baseCost->actual_amount_pln !== null && (float) $baseCost->actual_amount_pln > 0) {
            $paidBuckets->add((float) $baseCost->actual_amount_pln, null, true);
        }
    }

    private function accumulatePayerBucket(
        SetFinanceCurrencyBuckets $officeBuckets,
        SetFinanceCurrencyBuckets $pilotBuckets,
        float $amount,
        ?Currency $currency,
        bool $convertToPln,
        string $paidBy,
    ): void {
        if ($amount <= 0) {
            return;
        }

        if ($paidBy === 'pilot') {
            $pilotBuckets->add($amount, $currency, $convertToPln);
        } else {
            $officeBuckets->add($amount, $currency, $convertToPln);
        }
    }

    /**
     * @return array<int, string>
     */
    private function buildPayerLines(SetFinanceCurrencyBuckets $officeBuckets, SetFinanceCurrencyBuckets $pilotBuckets): array
    {
        $lines = [];

        if ($officeBuckets->hasAmount()) {
            $lines[] = 'Biuro: '.$officeBuckets->formatMixed();
        }

        if ($pilotBuckets->hasAmount()) {
            $lines[] = 'Pilot: '.$pilotBuckets->formatMixed();
        }

        return $lines;
    }

    private function buildSettlementInfoHtml(Collection $paidByValues, int $paymentCount): string
    {
        $unique = $paidByValues->filter()->unique()->values();

        $paidBy = match ($unique->count()) {
            0 => '<span style="color:#999">—</span>',
            1 => $unique->first() === 'pilot'
                ? '<span style="color:#1976d2">👤 Pilot</span>'
                : '<span style="color:#388e3c">🏢 Biuro</span>',
            default => '<span style="color:#7b1fa2">👤 Pilot + 🏢 Biuro</span>',
        };

        return "<div>{$paidBy}<br><span style='font-size:10px;color:#888'>Σ set</span><br><span style='font-size:10px;color:#888'>Wpłat: {$paymentCount}</span></div>";
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $scheduleRows
     * @return array<int, string>
     */
    private function buildPaymentDueLines(Collection $scheduleRows): array
    {
        return $scheduleRows
            ->sortBy(fn (array $row) => $row['due_date'] ?? '')
            ->take(5)
            ->map(fn (array $row): string => \App\Support\EventProgramPointPaymentDueColumn::plainLine($row))
            ->values()
            ->all();
    }
}
