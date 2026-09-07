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

    /** @var array<int, float> suma native (actual_amount) */
    protected array $paymentStackNativeByPointId = [];

    /** @var array<int, float> suma PLN (actual_amount_pln z fallbackiem rate) */
    protected array $paymentStackPlnByPointId = [];

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

    /**
     * @return array{paid: float, planned: float}
     */
    protected function resolveComparableAmounts(EventSettlementCost $cost, ?EventProgramPoint $point, ?Event $event): array
    {
        $plannedNative = (float) ($cost->planned_amount ?? 0);
        $plannedPln = $cost->planned_amount_pln !== null ? (float) $cost->planned_amount_pln : null;
        if ($plannedPln === null && $plannedNative > 0.009) {
            $rate = (float) ($cost->planned_rate ?? 0);
            if ($rate > 0.009 && (bool) ($cost->planned_convert_to_pln ?? false)) {
                $plannedPln = round($plannedNative * $rate, 2);
            }
        }

        [$paidNative, $paidPln] = $this->sumPaymentStacks($cost, $point, $event);

        // Porównuj w PLN tylko gdy obie strony mają wiarygodne PLN.
        if ($plannedPln !== null && $plannedPln > 0.009 && $paidPln > 0.009) {
            return ['paid' => $paidPln, 'planned' => $plannedPln];
        }

        if ($plannedNative > 0.009) {
            return ['paid' => $paidNative, 'planned' => $plannedNative];
        }

        return [
            'paid' => $paidPln > 0.009 ? $paidPln : $paidNative,
            'planned' => $plannedPln ?? $plannedNative,
        ];
    }

    /**
     * @return array{0: float, 1: float} [native, pln]
     */
    protected function sumPaymentStacks(EventSettlementCost $baseCost, ?EventProgramPoint $point, ?Event $event): array
    {
        $settlement = $baseCost->settlement ?? $event?->activeSettlement;

        if (! $settlement || ! $point) {
            return $this->amountsFromCostRow($baseCost);
        }

        if (
            array_key_exists($point->id, $this->paymentStackNativeByPointId)
            || array_key_exists($point->id, $this->paymentStackPlnByPointId)
        ) {
            return [
                (float) ($this->paymentStackNativeByPointId[$point->id] ?? 0),
                (float) ($this->paymentStackPlnByPointId[$point->id] ?? 0),
            ];
        }

        $rows = $settlement->costs()
            ->where('source_type', self::PAYMENT_SOURCE_TYPE)
            ->where('source_id', $point->id)
            ->whereIn('payment_status', SettlementPaymentHealthService::BOOKED_PAYMENT_STATUSES)
            ->get(['actual_amount', 'actual_amount_pln', 'actual_rate', 'payment_status']);

        $native = 0.0;
        $pln = 0.0;
        foreach ($rows as $row) {
            [$rowNative, $rowPln] = $this->amountsFromCostRow($row);
            $native += $rowNative;
            $pln += $rowPln;
        }

        if ($native <= 0.009 && $pln <= 0.009
            && SettlementPaymentHealthService::isBookedPaymentStatus($baseCost->payment_status)) {
            return $this->amountsFromCostRow($baseCost);
        }

        return [round($native, 2), round($pln, 2)];
    }

    /**
     * @return array{0: float, 1: float} [native, pln]
     */
    protected function amountsFromCostRow(EventSettlementCost $row): array
    {
        $native = (float) ($row->actual_amount ?? 0);
        $pln = $row->actual_amount_pln !== null ? (float) $row->actual_amount_pln : 0.0;
        $rate = (float) ($row->actual_rate ?? 0);

        if ($pln <= 0.009 && $native > 0.009 && $rate > 0.009) {
            $pln = round($native * $rate, 2);
        }

        if ($native <= 0.009 && $pln > 0.009) {
            $native = $pln;
        }

        return [$native, $pln];
    }

    /**
     * @return array{code: string, color: string, tooltip: string}
     */
    protected function resolveFromSettlementCost(EventSettlementCost $cost, ?EventProgramPoint $point = null, ?Event $event = null): array
    {
        $amounts = $this->resolveComparableAmounts($cost, $point, $event);
        $planned = $amounts['planned'];
        $paid = $amounts['paid'];
        $status = (string) ($cost->payment_status ?? 'planned');
        $dueDate = $cost->advance_due_date;

        if ($planned <= 0.0) {
            return [
                'code' => '',
                'color' => 'gray',
                'tooltip' => '',
            ];
        }

        // Źródło prawdy: kwoty, nie flaga payment_status.
        if (SettlementPaymentHealthService::isFullyPaid($paid, $planned)) {
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

        if (SettlementPaymentHealthService::isPartiallyPaid($paid, $planned)) {
            return [
                'code' => '$',
                'color' => 'orange',
                'tooltip' => sprintf('Płatność: częściowo opłacona (%s / %s).', MoneyFormatter::format($paid), MoneyFormatter::format($planned)),
            ];
        }

        if (in_array($status, ['advance_required', 'reservation_required', 'planned'], true) || $paid <= 0.0) {
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
                'code' => '',
                'color' => 'gray',
                'tooltip' => '',
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

    /**
     * @param  Collection<int, EventProgramPoint>  $points
     */
    public function preloadPaymentStacks(Collection $points, Event $event): void
    {
        $this->paymentStackNativeByPointId = [];
        $this->paymentStackPlnByPointId = [];

        $settlement = $event->activeSettlement;

        if (! $settlement || $points->isEmpty()) {
            return;
        }

        $rows = $settlement->costs()
            ->where('source_type', self::PAYMENT_SOURCE_TYPE)
            ->whereIn('source_id', $points->pluck('id'))
            ->whereIn('payment_status', SettlementPaymentHealthService::BOOKED_PAYMENT_STATUSES)
            ->reorder()
            ->get(['source_id', 'actual_amount', 'actual_amount_pln', 'actual_rate']);

        foreach ($rows->groupBy('source_id') as $pointId => $group) {
            $native = 0.0;
            $pln = 0.0;
            foreach ($group as $row) {
                [$rowNative, $rowPln] = $this->amountsFromCostRow($row);
                $native += $rowNative;
                $pln += $rowPln;
            }
            $this->paymentStackNativeByPointId[(int) $pointId] = round($native, 2);
            $this->paymentStackPlnByPointId[(int) $pointId] = round($pln, 2);
        }
    }
}
