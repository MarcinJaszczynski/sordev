<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlementCost;
use App\Support\MoneyFormatter;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ProgramPointPaymentStatusResolver
{
    private const PAYMENT_SOURCE_TYPE = 'program_point_payment';

    public function __construct(
        protected int $dueSoonDays = 14,
    ) {}

    /**
     * @return array{code: string, color: string, tooltip: string}
     */
    public function resolve(EventProgramPoint $point, ?Event $event = null): array
    {
        $cost = $this->resolveSettlementCost($point, $event);

        if ($cost) {
            return $this->resolveFromSettlementCost($cost, $point, $event);
        }

        return $this->resolveFromPointAmounts($point);
    }

    protected function resolveSettlementCost(EventProgramPoint $point, ?Event $event): ?EventSettlementCost
    {
        if ($point->relationLoaded('settlementCosts')) {
            return $point->settlementCosts->first();
        }

        $event ??= $point->event;

        if (! $event) {
            return EventSettlementCost::query()
                ->where('source_type', 'program_point')
                ->where('source_id', $point->id)
                ->whereHas('settlement', fn ($query) => $query->whereIn('status', ['draft', 'active', 'pilot_settled']))
                ->latest('id')
                ->first();
        }

        $settlement = $event->activeSettlement;

        if (! $settlement) {
            return null;
        }

        return $settlement->costs()
            ->where('source_type', 'program_point')
            ->where('source_id', $point->id)
            ->first();
    }

    protected function sumPaymentStackPln(EventSettlementCost $baseCost, ?EventProgramPoint $point, ?Event $event): float
    {
        $settlement = $baseCost->settlement ?? $event?->activeSettlement;

        if (! $settlement || ! $point) {
            return (float) ($baseCost->actual_amount_pln ?? $baseCost->actual_amount ?? 0);
        }

        $stackSum = (float) $settlement->costs()
            ->where('source_type', self::PAYMENT_SOURCE_TYPE)
            ->where('source_id', $point->id)
            ->where('payment_status', '!=', 'cancelled')
            ->sum('actual_amount_pln');

        if ($stackSum > 0) {
            return $stackSum;
        }

        return (float) ($baseCost->actual_amount_pln ?? $baseCost->actual_amount ?? 0);
    }

    /**
     * @return array{code: string, color: string, tooltip: string}
     */
    protected function resolveFromSettlementCost(EventSettlementCost $cost, ?EventProgramPoint $point = null, ?Event $event = null): array
    {
        $planned = (float) ($cost->planned_amount_pln ?? $cost->planned_amount ?? 0);
        $paid = $this->sumPaymentStackPln($cost, $point, $event);
        $status = (string) ($cost->payment_status ?? 'planned');
        $dueDate = $cost->advance_due_date;

        if ($planned <= 0.0) {
            return [
                'code' => 'N/A',
                'color' => 'gray',
                'tooltip' => 'Płatność: brak zaplanowanej kwoty w rozliczeniu.',
            ];
        }

        if ($status === 'paid' || $paid + 0.01 >= $planned) {
            return [
                'code' => '$',
                'color' => 'green',
                'tooltip' => sprintf('Płatność: opłacona (%s / %s).', MoneyFormatter::format($paid), MoneyFormatter::format($planned)),
            ];
        }

        if ($dueDate instanceof Carbon) {
            if ($dueDate->isPast() && ! $dueDate->isToday()) {
                return [
                    'code' => '$',
                    'color' => 'red',
                    'tooltip' => sprintf(
                        'Płatność: przeterminowana (termin %s, %s / %s).',
                        $dueDate->format('d.m.Y'),
                        MoneyFormatter::format($paid),
                        MoneyFormatter::format($planned),
                    ),
                ];
            }

            if ($dueDate->isFuture() && $dueDate->lte(now()->addDays($this->dueSoonDays))) {
                return [
                    'code' => '$',
                    'color' => 'orange',
                    'tooltip' => sprintf(
                        'Płatność: do zapłaty (termin %s, %s / %s).',
                        $dueDate->format('d.m.Y'),
                        MoneyFormatter::format($paid),
                        MoneyFormatter::format($planned),
                    ),
                ];
            }
        }

        if ($paid > 0.0 && $paid + 0.01 < $planned) {
            return [
                'code' => '$',
                'color' => 'orange',
                'tooltip' => sprintf('Płatność: częściowo opłacona (%s / %s).', MoneyFormatter::format($paid), MoneyFormatter::format($planned)),
            ];
        }

        if (in_array($status, ['advance_required', 'reservation_required', 'planned'], true)) {
            return [
                'code' => '$',
                'color' => 'red',
                'tooltip' => sprintf(
                    'Płatność: do zapłaty (%s, %s / %s).',
                    EventSettlementCost::$paymentStatuses[$status] ?? $status,
                    MoneyFormatter::format($paid),
                    MoneyFormatter::format($planned),
                ),
            ];
        }

        return [
            'code' => '$',
            'color' => 'orange',
            'tooltip' => sprintf('Płatność: w toku (%s / %s).', MoneyFormatter::format($paid), MoneyFormatter::format($planned)),
        ];
    }

    /**
     * @return array{code: string, color: string, tooltip: string}
     */
    protected function resolveFromPointAmounts(EventProgramPoint $point): array
    {
        $planned = (float) ($point->planned_price ?? $point->total_price ?? 0);
        $paid = (float) ($point->paid_price ?? 0);

        if ($planned <= 0.0) {
            return [
                'code' => 'N/A',
                'color' => 'gray',
                'tooltip' => 'Płatność: brak zaplanowanej kwoty.',
            ];
        }

        if ($paid + 0.01 >= $planned) {
            return [
                'code' => '$',
                'color' => 'green',
                'tooltip' => sprintf('Płatność: opłacona (%s / %s).', $point->formatAmount($paid), $point->formatAmount($planned)),
            ];
        }

        if ($paid > 0.0) {
            return [
                'code' => '$',
                'color' => 'orange',
                'tooltip' => sprintf('Płatność: częściowo opłacona (%s / %s).', $point->formatAmount($paid), $point->formatAmount($planned)),
            ];
        }

        return [
            'code' => '$',
            'color' => 'red',
            'tooltip' => sprintf('Płatność: do zapłaty (%s / %s).', $point->formatAmount($paid), $point->formatAmount($planned)),
        ];
    }

    /**
     * @param  Collection<int, EventProgramPoint>  $points
     */
    public function preloadSettlementCosts(Collection $points, Event $event): void
    {
        $settlement = $event->activeSettlement;

        if (! $settlement) {
            $points->each(fn (EventProgramPoint $point) => $point->setRelation('settlementCosts', collect()));

            return;
        }

        $costsByPointId = $settlement->costs()
            ->where('source_type', 'program_point')
            ->whereIn('source_id', $points->pluck('id'))
            ->get()
            ->keyBy('source_id');

        $points->each(function (EventProgramPoint $point) use ($costsByPointId): void {
            $cost = $costsByPointId->get($point->id);
            $point->setRelation('settlementCosts', $cost ? collect([$cost]) : collect());
        });
    }
}
