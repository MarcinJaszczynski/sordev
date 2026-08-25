<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlementCost;
use App\Support\PilotSetFinanceMemberLine;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

final class PilotProgramPointFinanceDisplay
{
    /**
     * @param  EloquentCollection<int, EventProgramPoint>|Collection<int, EventProgramPoint>  $points
     * @return array<int, array<string, mixed>>
     */
    public function hintsForPoints(Event $event, EloquentCollection|Collection $points): array
    {
        if ($points->isEmpty()) {
            return [];
        }

        $programPointIds = $points->pluck('id')->map(fn ($id): int => (int) $id);
        $setCards = app(PilotSetFinanceDisplay::class)->cardsForEvent($event, $programPointIds);

        $setParentIds = $points
            ->filter(fn (EventProgramPoint $point): bool => $this->isSetParent($point))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $costs = $this->loadCostsForPoints(
            $event->activeSettlement ?? $event->activeSettlement()->first(),
            $points->pluck('id')->map(fn ($id): int => (int) $id),
        );

        $hints = [];

        foreach ($points as $point) {
            if (! $point->parent_id || ! in_array((int) $point->parent_id, $setParentIds, true)) {
                continue;
            }

            $parentCard = $setCards[(int) $point->parent_id] ?? null;
            $childLine = $parentCard
                ? collect($parentCard->memberLines)->first(fn (PilotSetFinanceMemberLine $line): bool => $line->pointId === (int) $point->id)
                : null;

            if ($childLine && $childLine->status === PilotSetFinanceMemberLine::STATUS_PILOT_DUE) {
                $hints[(int) $point->id] = [
                    'is_set_child' => true,
                    'has_pilot_obligation' => true,
                    'payer_label' => 'Pilot płaci',
                    'lines' => [$childLine->displayLabel],
                    'payment_lines' => [],
                ];
            } else {
                $hints[(int) $point->id] = [
                    'is_set_child' => true,
                    'has_pilot_obligation' => $childLine?->status === PilotSetFinanceMemberLine::STATUS_PILOT_DUE,
                ];
            }
        }

        foreach ($points as $point) {
            if (! $this->isSetParent($point)) {
                continue;
            }

            $card = $setCards[(int) $point->id] ?? null;

            if ($card !== null) {
                $hints[(int) $point->id] = $card->toHintArray();
            }
        }

        foreach ($points as $point) {
            $pointId = (int) $point->id;

            if (isset($hints[$pointId]) || $this->isSetParent($point)) {
                continue;
            }

            if (! $event->activeSettlement && ! $event->relationLoaded('activeSettlement')) {
                $settlement = $event->activeSettlement()->first();
            } else {
                $settlement = $event->activeSettlement;
            }

            if (! $settlement) {
                continue;
            }

            $rows = $costs->where('source_id', $point->id);
            $base = $rows->first(fn (EventSettlementCost $cost): bool => $cost->source_type === 'program_point');
            $paymentRows = $this->paymentRowsFromCosts($rows);

            $hint = $this->buildSinglePointHint($point, $base, $paymentRows);

            if ($hint !== null) {
                $hints[$pointId] = $hint;
            }
        }

        return $hints;
    }

    private function isSetParent(EventProgramPoint $point): bool
    {
        if ((int) ($point->children_count ?? 0) > 0) {
            return true;
        }

        return $point->relationLoaded('children') && $point->children->isNotEmpty();
    }

    /**
     * @param  Collection<int, EventSettlementCost>  $paymentRows
     */
    private function pointConcernsPilot(?EventSettlementCost $base, Collection $paymentRows): bool
    {
        if (($base?->paid_by ?? 'office') === 'pilot') {
            return true;
        }

        if ($paymentRows->contains(fn (EventSettlementCost $row): bool => ($row->paid_by ?? 'office') === 'pilot')) {
            return true;
        }

        $advanceRow = $this->resolveAdvanceRow($base, $paymentRows);

        return $advanceRow !== null && ($advanceRow->paid_by ?? 'office') === 'pilot';
    }

    /**
     * @param  Collection<int, EventSettlementCost>  $costs
     */
    private function paymentRowsFromCosts(Collection $costs): Collection
    {
        return $costs
            ->filter(fn (EventSettlementCost $cost): bool => $cost->source_type === 'program_point_payment'
                && $cost->payment_status !== 'cancelled');
    }

    /**
     * @param  Collection<int, EventSettlementCost>  $paymentRows
     */
    private function resolveAdvanceRow(?EventSettlementCost $base, Collection $paymentRows): ?EventSettlementCost
    {
        $advanceRow = $paymentRows->first(fn (EventSettlementCost $row): bool => $this->isAdvanceRow($row));

        if ($advanceRow) {
            return $advanceRow;
        }

        if ($base && (float) ($base->advance_amount ?? 0) > 0) {
            return $base;
        }

        return null;
    }

    private function isAdvanceRow(EventSettlementCost $row, ?EventSettlementCost $resolvedAdvanceRow = null): bool
    {
        if ($resolvedAdvanceRow && $row->id === $resolvedAdvanceRow->id) {
            return true;
        }

        return in_array((string) $row->advance_type, ['advance', 'deposit'], true)
            || (float) ($row->advance_amount ?? 0) > 0;
    }

    private function formatPaymentRowLine(EventSettlementCost $row, bool $convertToPln): ?string
    {
        $parts = [];

        if (filled($row->actual_amount)) {
            $parts[] = \App\Support\CurrencyAmountDisplay::format(
                (float) $row->actual_amount,
                $row->actualCurrency ?? $row->plannedCurrency,
                $convertToPln,
            );
        } elseif ((float) ($row->advance_amount ?? 0) > 0) {
            $parts[] = \App\Support\CurrencyAmountDisplay::format(
                (float) $row->advance_amount,
                $row->plannedCurrency,
                $convertToPln,
            );
        }

        if ($row->advance_due_date) {
            $parts[] = 'do '.$row->advance_due_date->format('d.m.Y');
        }

        if ($row->paid_at) {
            $parts[] = 'wpłacono: '.$row->paid_at->format('d.m.Y');
        }

        if ($row->document_number) {
            $parts[] = $row->document_number;
        }

        return $parts !== [] ? implode(' · ', $parts) : 'Wpłata pilota';
    }

    /**
     * @param  Collection<int, int>  $pointIds
     * @return Collection<int, EventSettlementCost>
     */
    private function loadCostsForPoints(?\App\Models\EventSettlement $settlement, Collection $pointIds): Collection
    {
        if (! $settlement) {
            return collect();
        }

        $childIds = EventProgramPoint::query()
            ->whereIn('parent_id', $pointIds)
            ->pluck('id');

        $allIds = $pointIds->merge($childIds)->unique()->values();

        return $settlement->costs()
            ->whereIn('source_id', $allIds)
            ->whereIn('source_type', ['program_point', 'program_point_payment'])
            ->with(['plannedCurrency', 'actualCurrency'])
            ->get();
    }

    /**
     * @param  Collection<int, EventSettlementCost>  $paymentRows
     * @return array<int, string>
     */
    private function buildPilotObligationLines(?EventSettlementCost $base, Collection $paymentRows): array
    {
        $lines = [];
        $convertToPln = (bool) ($base?->planned_convert_to_pln ?? true);

        if ($base && ($base->paid_by ?? 'office') === 'pilot') {
            $plannedAmount = (float) ($base->planned_amount ?? 0);
            if ($plannedAmount > 0) {
                $lines[] = 'Planowane: '.\App\Support\CurrencyAmountDisplay::format(
                    $plannedAmount,
                    $base->plannedCurrency,
                    $convertToPln,
                );
            }
        }

        $advanceRow = $this->resolveAdvanceRow($base, $paymentRows);
        $advancePayer = $advanceRow?->paid_by
            ?? ((float) ($base?->advance_amount ?? 0) > 0 ? ($base?->paid_by ?? 'office') : null);

        if ($advancePayer === 'pilot') {
            $advanceAmount = (float) ($advanceRow?->advance_amount ?? $base?->advance_amount ?? 0);
            if ($advanceAmount > 0) {
                $currency = $advanceRow?->actualCurrency
                    ?? $advanceRow?->plannedCurrency
                    ?? $base?->plannedCurrency;
                $line = 'Zaliczka: '.\App\Support\CurrencyAmountDisplay::format($advanceAmount, $currency, $convertToPln);
                $due = $advanceRow?->advance_due_date ?? $base?->advance_due_date;
                if ($due) {
                    $line .= ' · do '.$due->format('d.m.Y');
                }
                $lines[] = $line;
            }
        }

        foreach ($paymentRows as $row) {
            if (($row->paid_by ?? 'office') !== 'pilot' || $this->isAdvanceRow($row, $advanceRow)) {
                continue;
            }

            $line = $this->formatPaymentRowLine($row, $convertToPln);
            if ($line !== null) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @param  Collection<int, EventSettlementCost>  $paymentRows
     * @return array<string, mixed>|null
     */
    private function buildSinglePointHint(
        EventProgramPoint $point,
        ?EventSettlementCost $base,
        Collection $paymentRows,
    ): ?array {
        if (! $base) {
            return null;
        }

        if (! $this->pointConcernsPilot($base, $paymentRows)) {
            $payer = (string) ($base->paid_by ?? 'office');
            $planned = (float) ($base->planned_amount ?? 0);
            if ($payer !== 'office' || $planned <= 0.009) {
                return null;
            }

            $convertToPln = (bool) ($base->planned_convert_to_pln ?? true);

            return [
                'has_pilot_obligation' => false,
                'has_office_obligation' => true,
                'payer' => 'office',
                'payer_label' => 'Płaci biuro',
                'lines' => [],
                'planned_label' => \App\Support\CurrencyAmountDisplay::format($planned, $base->plannedCurrency, $convertToPln),
                'advance_label' => null,
                'due_date_label' => null,
                'payment_lines' => [],
            ];
        }

        $payer = (string) ($base->paid_by ?? 'office');
        $convertToPln = (bool) ($base->planned_convert_to_pln ?? true);
        $lines = $this->buildPilotObligationLines($base, $paymentRows);

        $plannedLabel = $base->planned_amount !== null && $payer === 'pilot'
            ? \App\Support\CurrencyAmountDisplay::format((float) $base->planned_amount, $base->plannedCurrency, $convertToPln)
            : null;

        $advanceRow = $this->resolveAdvanceRow($base, $paymentRows);
        $advanceAmount = (float) ($advanceRow?->advance_amount ?? $base->advance_amount ?? 0);
        $advancePayer = $advanceRow?->paid_by
            ?? ((float) ($base->advance_amount ?? 0) > 0 ? ($base->paid_by ?? 'office') : null);

        $advanceLabel = $advanceAmount > 0 && $advancePayer === 'pilot'
            ? \App\Support\CurrencyAmountDisplay::format(
                $advanceAmount,
                $advanceRow?->actualCurrency ?? $advanceRow?->plannedCurrency ?? $base->plannedCurrency,
                $convertToPln,
            )
            : null;

        $dueDate = $advancePayer === 'pilot'
            ? ($advanceRow?->advance_due_date ?? $base->advance_due_date)
            : null;

        $paymentLines = $paymentRows
            ->reject(fn (EventSettlementCost $row): bool => $this->isAdvanceRow($row, $advanceRow))
            ->filter(fn (EventSettlementCost $row): bool => ($row->paid_by ?? 'office') === 'pilot')
            ->map(fn (EventSettlementCost $row): string => $this->formatPaymentRowLine($row, $convertToPln) ?? 'Wpłata pilota')
            ->values()
            ->all();

        return [
            'has_pilot_obligation' => true,
            'payer' => $payer,
            'payer_label' => $payer === 'pilot' ? 'Pilot płaci' : 'Pilot płaci część',
            'lines' => $lines,
            'planned_label' => $plannedLabel,
            'advance_label' => $advanceLabel,
            'due_date_label' => $dueDate?->format('d.m.Y'),
            'payment_lines' => $paymentLines,
        ];
    }
}
