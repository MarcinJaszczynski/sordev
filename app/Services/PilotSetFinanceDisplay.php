<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Support\CurrencyAmountDisplay;
use App\Support\EventProgramPointPricesSummary;
use App\Support\PilotSetFinanceCard;
use App\Support\PilotSetFinanceMemberLine;
use App\Support\SetFinanceCurrencyBuckets;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

final class PilotSetFinanceDisplay
{
    /**
     * @param  Collection<int, int>|null  $programPointIds
     * @return array<int, PilotSetFinanceCard>
     */
    public function cardsForEvent(Event $event, ?Collection $programPointIds = null): array
    {
        $settlement = $event->relationLoaded('activeSettlement')
            ? $event->activeSettlement
            : $event->activeSettlement()->first();

        if (! $settlement) {
            return [];
        }

        $programPointIds ??= collect(
            app(EventProgramPointOrderService::class)
                ->pilotProgramPoints($event)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
        );

        $parents = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->where('active', true)
            ->whereNull('parent_id')
            ->withCount('children')
            ->orderBy('day')
            ->orderBy('order')
            ->get()
            ->filter(fn (EventProgramPoint $point): bool => (int) ($point->children_count ?? 0) > 0);

        if ($parents->isEmpty()) {
            return [];
        }

        $costCache = new ProgramPointSettlementCostCache;
        $costCache->warm($parents, $event);

        $cards = [];

        foreach ($parents as $parent) {
            $card = $this->buildCard($parent, $settlement, $costCache, $programPointIds);

            if ($card !== null) {
                $cards[(int) $parent->id] = $card;
            }
        }

        return $cards;
    }

    /**
     * @return EloquentCollection<int, EventProgramPoint>
     */
    public function pilotMembers(EventProgramPoint $parent): EloquentCollection
    {
        $members = new EloquentCollection;

        if ((bool) $parent->active) {
            $parent->loadMissing(['currency', 'templatePoint']);
            $members->push($parent);
        }

        $children = EventProgramPoint::query()
            ->where('parent_id', $parent->id)
            ->where('active', true)
            ->with(['currency', 'templatePoint'])
            ->orderBy('order')
            ->get();

        return $members->merge($children)->values();
    }

    /**
     * @param  Collection<int, int>  $programPointIds
     */
    public function buildCard(
        EventProgramPoint $parent,
        EventSettlement $settlement,
        ProgramPointSettlementCostCache $costCache,
        Collection $programPointIds,
    ): ?PilotSetFinanceCard {
        $memberLines = [];
        $dueBuckets = new SetFinanceCurrencyBuckets;
        $plannedPilotBuckets = new SetFinanceCurrencyBuckets;
        $hasAnySettlementLine = false;

        foreach ($this->pilotMembers($parent) as $member) {
            $base = $costCache->baseCost((int) $member->id);
            $paymentRows = $costCache->paymentRows((int) $member->id);

            if (! $base && $paymentRows->isEmpty()) {
                continue;
            }

            $hasAnySettlementLine = true;
            $line = $this->buildMemberLine($member, $base, $paymentRows, $programPointIds);

            if ($line === null) {
                continue;
            }

            $memberLines[] = $line;

            if ($line->countsTowardPilotTotal && $line->remainingAmount > 0) {
                $dueBuckets->add($line->remainingAmount, $line->currency, $line->convertToPln);
            }

            if (in_array($line->status, [PilotSetFinanceMemberLine::STATUS_PILOT_DUE, PilotSetFinanceMemberLine::STATUS_PILOT_PAID], true)
                && $line->plannedAmount > 0) {
                $plannedPilotBuckets->add($line->plannedAmount, $line->currency, $line->convertToPln);
            }
        }

        if (! $hasAnySettlementLine || $memberLines === []) {
            return null;
        }

        $hasPilotObligation = $dueBuckets->hasAmount()
            || collect($memberLines)->contains(
                fn (PilotSetFinanceMemberLine $line): bool => $line->status === PilotSetFinanceMemberLine::STATUS_PILOT_DUE
            );

        $parentName = $parent->templatePoint->name ?? $parent->name ?? ('Set #'.$parent->id);

        return new PilotSetFinanceCard(
            parentId: (int) $parent->id,
            parentName: $parentName,
            day: (int) ($parent->day ?? 1),
            order: (int) ($parent->order ?? 0),
            inProgram: $programPointIds->contains((int) $parent->id),
            totalPilotDueLabel: $dueBuckets->hasAmount() ? $dueBuckets->formatMixed() : '0 PLN',
            plannedPilotLabel: $plannedPilotBuckets->hasAmount() ? $plannedPilotBuckets->formatMixed() : '0 PLN',
            hasPilotObligation: $hasPilotObligation,
            memberLines: $memberLines,
        );
    }

    /**
     * @param  Collection<int, int>  $programPointIds
     */
    private function buildMemberLine(
        EventProgramPoint $member,
        ?EventSettlementCost $base,
        Collection|EloquentCollection $paymentRows,
        Collection $programPointIds,
    ): ?PilotSetFinanceMemberLine {
        $payer = $this->resolveMemberPayer($base, $paymentRows);
        $name = $member->templatePoint->name ?? $member->name ?? ('Punkt #'.$member->id);
        $inProgram = $programPointIds->contains((int) $member->id);
        $convertToPln = (bool) ($base?->planned_convert_to_pln ?? $member->convert_to_pln ?? true);
        $currency = $base?->plannedCurrency ?? $member->currency;

        $planned = $this->resolvePlannedAmount($base, $member);
        $paid = $this->resolvePaidAmount($base, $paymentRows);
        $paidStatus = EventProgramPointPricesSummary::resolvePaidStatus($paid, $planned);
        $remaining = max(0, $planned - $paid);

        if ($payer === 'pilot') {
            $amountLabel = $remaining > 0
                ? CurrencyAmountDisplay::format($remaining, $currency, $convertToPln)
                : ($planned > 0 ? CurrencyAmountDisplay::format($planned, $currency, $convertToPln) : null);

            $dueDate = $this->resolveDueDate($base, $paymentRows);

            if ($paidStatus === EventProgramPointPricesSummary::STATUS_FULL) {
                return new PilotSetFinanceMemberLine(
                    pointId: (int) $member->id,
                    name: $name,
                    status: PilotSetFinanceMemberLine::STATUS_PILOT_PAID,
                    displayLabel: $name.' — '.($amountLabel ?? '0 PLN').' (opłacone)',
                    inProgram: $inProgram,
                    amountLabel: $amountLabel,
                    dueDateLabel: $dueDate,
                    plannedAmount: $planned,
                    remainingAmount: 0,
                    currency: $currency,
                    convertToPln: $convertToPln,
                );
            }

            $display = $name.' — '.($amountLabel ?? '—');
            if ($dueDate) {
                $display .= ' · do '.$dueDate;
            }
            if ($planned > 0) {
                $display .= ' (planowana)';
            }

            return new PilotSetFinanceMemberLine(
                pointId: (int) $member->id,
                name: $name,
                status: PilotSetFinanceMemberLine::STATUS_PILOT_DUE,
                displayLabel: $display,
                inProgram: $inProgram,
                countsTowardPilotTotal: $remaining > 0,
                amountLabel: $amountLabel,
                dueDateLabel: $dueDate,
                plannedAmount: $planned,
                remainingAmount: $remaining,
                currency: $currency,
                convertToPln: $convertToPln,
            );
        }

        if ($paidStatus === EventProgramPointPricesSummary::STATUS_FULL || $paid > 0) {
            return new PilotSetFinanceMemberLine(
                pointId: (int) $member->id,
                name: $name,
                status: PilotSetFinanceMemberLine::STATUS_OFFICE_PAID,
                displayLabel: $name.' — opłacone przez biuro',
                inProgram: $inProgram,
            );
        }

        return new PilotSetFinanceMemberLine(
            pointId: (int) $member->id,
            name: $name,
            status: PilotSetFinanceMemberLine::STATUS_OFFICE_DUE,
            displayLabel: $name.' — płaci biuro',
            inProgram: $inProgram,
        );
    }

    /**
     * @param  EloquentCollection<int, EventSettlementCost>|Collection<int, EventSettlementCost>  $paymentRows
     */
    private function resolveMemberPayer(?EventSettlementCost $base, Collection|EloquentCollection $paymentRows): string
    {
        if (($base?->paid_by ?? 'office') === 'pilot') {
            return 'pilot';
        }

        $advanceRow = $this->resolveAdvanceRow($base, $paymentRows);

        if ($advanceRow && ($advanceRow->paid_by ?? 'office') === 'pilot') {
            return 'pilot';
        }

        if ($paymentRows->contains(fn (EventSettlementCost $row): bool => ($row->paid_by ?? 'office') === 'pilot')) {
            return 'pilot';
        }

        return 'office';
    }

    private function resolvePlannedAmount(?EventSettlementCost $base, EventProgramPoint $member): float
    {
        $planned = (float) ($base?->planned_amount ?? $member->planned_price ?? 0);

        if ($planned <= 0) {
            $planned = (float) ($member->planned_price ?? 0);
        }

        return $planned;
    }

    /**
     * @param  EloquentCollection<int, EventSettlementCost>|Collection<int, EventSettlementCost>  $paymentRows
     */
    private function resolvePaidAmount(?EventSettlementCost $base, Collection|EloquentCollection $paymentRows): float
    {
        $paidForeign = (float) $paymentRows->sum(fn (EventSettlementCost $row) => (float) ($row->actual_amount ?? 0));

        if ($paidForeign <= 0) {
            $paidForeign = (float) $paymentRows->sum(function (EventSettlementCost $row): float {
                if ($row->actual_amount_pln !== null) {
                    return (float) $row->actual_amount_pln;
                }

                return (float) ($row->actual_amount ?? 0) * (float) ($row->actual_rate ?? 1);
            });
        }

        if ($paidForeign <= 0 && $base) {
            $paidForeign = (float) ($base->actual_amount ?? $base->actual_amount_pln ?? 0);
        }

        return $paidForeign;
    }

    /**
     * @param  EloquentCollection<int, EventSettlementCost>|Collection<int, EventSettlementCost>  $paymentRows
     */
    private function resolveDueDate(?EventSettlementCost $base, Collection|EloquentCollection $paymentRows): ?string
    {
        $advanceRow = $this->resolveAdvanceRow($base, $paymentRows);
        $due = $advanceRow?->advance_due_date ?? $base?->advance_due_date;

        return $due?->format('d.m.Y');
    }

    /**
     * @param  EloquentCollection<int, EventSettlementCost>|Collection<int, EventSettlementCost>  $paymentRows
     */
    private function resolveAdvanceRow(?EventSettlementCost $base, Collection|EloquentCollection $paymentRows): ?EventSettlementCost
    {
        $advanceRow = $paymentRows->first(fn (EventSettlementCost $row): bool => in_array((string) $row->advance_type, ['advance', 'deposit'], true)
            || (float) ($row->advance_amount ?? 0) > 0);

        if ($advanceRow) {
            return $advanceRow;
        }

        if ($base && (float) ($base->advance_amount ?? 0) > 0) {
            return $base;
        }

        return null;
    }
}
