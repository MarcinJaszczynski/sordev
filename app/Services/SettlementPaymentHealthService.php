<?php

namespace App\Services;

use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class SettlementPaymentHealthService
{
    public const STATUS_OK = 'ok';

    public const STATUS_NA = 'n/a';

    public const STATUS_SHORTFALL = 'shortfall';

    public const STATUS_DUE = 'due';

    public const STATUS_OVERDUE = 'overdue';

    /** Zapłacono więcej niż plan — wymaga ręcznego zatwierdzenia nadpłaty. */
    public const STATUS_OVERPAYMENT_REVIEW = 'overpayment_review';

    public const TOLERANCE = 0.01;

    /** Rodzaje wpłat domykających fakturę (pełna płatność / dopłata końcowa). */
    public const INVOICE_SETTLING_ADVANCE_TYPES = [
        'full',
        'final',
    ];

    /** Statusy wierszy wpłat uznawane za zaksięgowane (pieniądze faktycznie wyszły). */
    public const BOOKED_PAYMENT_STATUSES = [
        'paid',
        'advance_paid',
        'partially_paid',
    ];

    public static array $statusLabels = [
        self::STATUS_OK => 'Zapłacono',
        self::STATUS_NA => 'Brak kwoty',
        self::STATUS_SHORTFALL => 'Do zapłaty',
        self::STATUS_DUE => 'Do zapłaty',
        self::STATUS_OVERDUE => 'Po terminie',
        self::STATUS_OVERPAYMENT_REVIEW => 'Do sprawdzenia',
    ];

    public static array $statusColors = [
        self::STATUS_OK => ['#dcfce7', '#166534'],
        self::STATUS_NA => ['#f3f4f6', '#4b5563'],
        self::STATUS_SHORTFALL => ['#fee2e2', '#991b1b'],
        self::STATUS_DUE => ['#dbeafe', '#1e40af'],
        self::STATUS_OVERDUE => ['#ffedd5', '#9a3412'],
        self::STATUS_OVERPAYMENT_REVIEW => ['#fecaca', '#7f1d1d'],
    ];

    public static function moneyEquals(float $a, float $b): bool
    {
        return abs(self::toCents($a) - self::toCents($b)) <= 1;
    }

    public static function toCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    public static function isFullyPaid(float $paidPln, float $plannedPln): bool
    {
        if ($plannedPln <= self::TOLERANCE) {
            return false;
        }

        return self::toCents($paidPln) >= self::toCents($plannedPln) - 1;
    }

    public static function isOverpaid(float $paidPln, float $plannedPln): bool
    {
        if ($plannedPln <= self::TOLERANCE) {
            return $paidPln > self::TOLERANCE;
        }

        return self::toCents($paidPln) > self::toCents($plannedPln) + 1;
    }

    public static function isPartiallyPaid(float $paidPln, float $plannedPln): bool
    {
        return $paidPln > self::TOLERANCE
            && $plannedPln > self::TOLERANCE
            && self::toCents($paidPln) < self::toCents($plannedPln) - 1;
    }

    public static function remainingPln(float $paidPln, float $plannedPln): float
    {
        return max(0, round($plannedPln - $paidPln, 2));
    }

    /** Oszczędność gdy faktura domknięta poniżej planu (plan − zapłacone). */
    public static function savingsPln(float $paidPln, float $plannedPln): float
    {
        if ($plannedPln <= self::TOLERANCE || $paidPln <= self::TOLERANCE) {
            return 0.0;
        }

        $diff = round($plannedPln - $paidPln, 2);

        return $diff > self::TOLERANCE ? $diff : 0.0;
    }

    public static function overpaymentPln(float $paidPln, float $plannedPln): float
    {
        if (! self::isOverpaid($paidPln, $plannedPln)) {
            return 0.0;
        }

        return round($paidPln - max(0, $plannedPln), 2);
    }

    public static function isBookedPaymentStatus(?string $status): bool
    {
        return in_array((string) $status, self::BOOKED_PAYMENT_STATUSES, true);
    }

    /**
     * Pełna płatność faktury: tylko jawne „Pełna płatność” / „Dopłata końcowa”.
     * (DB default advance_type=full dotyczy głównie wierszy planu — nie traktujemy go
     * jako domknięcie faktury bez wpłaty typu final/full utworzonej przez UI.)
     */
    public static function isInvoiceSettlingAdvanceType(?string $advanceType): bool
    {
        return in_array((string) $advanceType, self::INVOICE_SETTLING_ADVANCE_TYPES, true);
    }

    public static function isOverpaymentApproved(EventSettlementCost $planCost): bool
    {
        return ($planCost->approval_status ?? null) === 'approved';
    }

    /**
     * @param  Collection<int, EventSettlementCost>  $paymentRows
     */
    public static function hasInvoiceSettlingPayment(Collection $paymentRows): bool
    {
        return $paymentRows
            ->filter(fn (EventSettlementCost $row): bool => self::isBookedPaymentStatus($row->payment_status))
            ->filter(fn (EventSettlementCost $row): bool => EventSettlementCost::isPaymentSourceType($row->source_type)
                || ($row->source_type === 'manual' && EventSettlementCost::isManualPaymentRow($row)))
            ->contains(fn (EventSettlementCost $row): bool => self::isInvoiceSettlingAdvanceType($row->advance_type));
    }

    /**
     * Zaksięgowana wpłata 0 = świadome domknięcie (np. pokryte na innej pozycji tego samego kontrahenta).
     * Nie mylić z walutą obcą bez przeliczenia na PLN (actual_amount > 0, actual_amount_pln = 0).
     *
     * @param  Collection<int, EventSettlementCost>  $paymentRows
     */
    public static function hasBookedZeroClosure(Collection $paymentRows): bool
    {
        return $paymentRows
            ->filter(fn (EventSettlementCost $row): bool => self::isBookedPaymentStatus($row->payment_status))
            ->contains(function (EventSettlementCost $row): bool {
                $amount = (float) ($row->actual_amount ?? 0);
                $amountPln = (float) ($row->actual_amount_pln ?? 0);

                return $amount <= self::TOLERANCE && $amountPln <= self::TOLERANCE;
            });
    }

    /**
     * @return array{
     *     coverage_status: string,
     *     coverage_label: string,
     *     planned_pln: float,
     *     paid_pln: float,
     *     remaining_pln: float,
     *     savings_pln: float,
     *     overpayment_pln: float,
     *     invoice_settled: bool,
     *     overpayment_approved: bool,
     *     next_due_date: ?Carbon,
     *     paid_by: string,
     *     cost_id: int,
     *     name: ?string,
     *     source_type: ?string
     * }
     */
    public function evaluatePlanCost(EventSettlementCost $planCost, Collection $allCosts): array
    {
        $paidPln = $this->paidPlnForPlanCost($planCost, $allCosts);
        $plannedPln = $this->plannedPlnForCost($planCost);
        // Status wg ekwiwalentu PLN — także gdy convert_to_pln=false (plan w walucie, planned_amount_pln=null).
        $statusPln = $this->indicativePlannedPlnForCost($planCost);
        $paymentRows = $this->paymentRowsForPlanCost($planCost, $allCosts);
        $invoiceSettled = self::hasInvoiceSettlingPayment($paymentRows);
        $overpaymentApproved = self::isOverpaymentApproved($planCost);
        $remainingPln = self::remainingPln($paidPln, $plannedPln);
        $savingsPln = ($invoiceSettled && self::isPartiallyPaid($paidPln, $statusPln))
            ? self::savingsPln($paidPln, $statusPln)
            : 0.0;
        $overpaymentPln = self::overpaymentPln($paidPln, $statusPln);
        $nextDue = $this->nextDueDate($planCost, $allCosts);
        $zeroClosure = self::hasBookedZeroClosure($paymentRows);
        $coverageStatus = $this->resolveStatus(
            $paidPln,
            $statusPln,
            $nextDue,
            $invoiceSettled,
            $overpaymentApproved,
            $zeroClosure,
        );

        return [
            'coverage_status' => $coverageStatus,
            'coverage_label' => self::$statusLabels[$coverageStatus] ?? $coverageStatus,
            'planned_pln' => $plannedPln,
            'status_planned_pln' => $statusPln,
            'paid_pln' => $paidPln,
            'remaining_pln' => $invoiceSettled ? 0.0 : $remainingPln,
            'savings_pln' => $savingsPln,
            'overpayment_pln' => $overpaymentPln,
            'invoice_settled' => $invoiceSettled,
            'overpayment_approved' => $overpaymentApproved,
            'next_due_date' => $nextDue,
            'paid_by' => (string) ($planCost->paid_by ?? 'office'),
            'cost_id' => (int) $planCost->id,
            'name' => $planCost->name,
            'source_type' => $planCost->source_type,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function evaluateSettlement(EventSettlement $settlement): Collection
    {
        $allCosts = $settlement->relationLoaded('costs')
            ? $settlement->costs
            : $settlement->costs()->get();

        return $this->listPlanCosts($allCosts)
            ->map(fn (EventSettlementCost $cost): array => $this->evaluatePlanCost($cost, $allCosts))
            ->values();
    }

    /**
     * @return Collection<int, EventSettlementCost>
     */
    public function listPlanCosts(Collection $allCosts): Collection
    {
        return $allCosts
            ->filter(fn (EventSettlementCost $cost): bool => $this->isEvaluablePlanCost($cost))
            ->values();
    }

    public function isEvaluablePlanCost(EventSettlementCost $cost): bool
    {
        if ($cost->payment_status === 'cancelled') {
            return false;
        }

        if (EventSettlementCost::isPaymentSourceType($cost->source_type)) {
            return false;
        }

        if ($cost->source_type === 'manual') {
            // Pozycja z planem > 0 to zawsze wiersz planu (nawet gdy status jak przy wpłacie).
            $planned = (float) ($cost->planned_amount_pln ?? $cost->planned_amount ?? 0);
            if ($planned > self::TOLERANCE) {
                return true;
            }

            return ! EventSettlementCost::isManualPaymentRow($cost);
        }

        return in_array($cost->source_type, [
            'program_point',
            'transport',
            'transport_contractor',
            'accommodation',
            'accommodation_hotel',
            'accommodation_hotel_stay',
            'insurance_day',
        ], true);
    }

    public function paidPlnForPlanCost(EventSettlementCost $planCost, Collection $allCosts): float
    {
        return round((float) $this->paymentRowsForPlanCost($planCost, $allCosts)
            ->filter(fn (EventSettlementCost $row): bool => self::isBookedPaymentStatus($row->payment_status))
            ->sum(fn (EventSettlementCost $row): float => (float) ($row->actual_amount_pln ?? 0)), 2);
    }

    public function plannedPlnForCost(EventSettlementCost $planCost): float
    {
        if ($planCost->planned_amount_pln !== null) {
            return round((float) $planCost->planned_amount_pln, 2);
        }

        return round((float) ($planCost->resolvePlannedAmountPln() ?? 0), 2);
    }

    /**
     * Kwota planu do oceny statusu wpłat: PLN z planu albo orientacyjny ekwiwalent (waluta × kurs).
     */
    public function indicativePlannedPlnForCost(EventSettlementCost $planCost): float
    {
        $plannedPln = $this->plannedPlnForCost($planCost);
        if ($plannedPln > self::TOLERANCE) {
            return $plannedPln;
        }

        $amount = (float) ($planCost->planned_amount ?? 0);
        if ($amount <= self::TOLERANCE) {
            return 0.0;
        }

        $currency = $planCost->relationLoaded('plannedCurrency')
            ? $planCost->plannedCurrency
            : $planCost->plannedCurrency()->first();
        $symbol = strtoupper((string) ($currency?->symbol ?? $currency?->code ?? 'PLN'));
        if ($symbol === 'PLN' || $symbol === '') {
            return round($amount, 2);
        }

        $rate = (float) ($planCost->planned_rate ?? ($currency?->exchange_rate ?? 1));
        if ($rate <= 0) {
            $rate = 1.0;
        }

        return round($amount * $rate, 2);
    }

    /**
     * @return Collection<int, EventSettlementCost>
     */
    public function paymentRowsForPlanCost(EventSettlementCost $planCost, Collection $allCosts): Collection
    {
        if ($planCost->source_type === 'program_point') {
            return $allCosts->filter(
                fn (EventSettlementCost $row): bool => $row->source_type === 'program_point_payment'
                    && (int) $row->source_id === (int) $planCost->source_id,
            )->values();
        }

        if ($planCost->source_type === 'insurance_day') {
            return $allCosts->filter(
                fn (EventSettlementCost $row): bool => $row->source_type === 'insurance_day_payment'
                    && (int) $row->source_id === (int) $planCost->source_id,
            )->values();
        }

        if (in_array($planCost->source_type, ['transport', 'accommodation'], true)) {
            $paymentType = $planCost->source_type.'_payment';

            return $allCosts->filter(
                fn (EventSettlementCost $row): bool => $row->source_type === $paymentType
                    && $row->source_id === null,
            )->values();
        }

        if (in_array($planCost->source_type, [
            'accommodation_hotel',
            'accommodation_hotel_stay',
            'transport_contractor',
        ], true)) {
            $paymentType = $planCost->source_type.'_payment';

            return $allCosts->filter(
                fn (EventSettlementCost $row): bool => $row->source_type === $paymentType
                    && (int) $row->source_id === (int) $planCost->source_id,
            )->values();
        }

        if ($planCost->source_type === 'manual') {
            $linked = $allCosts->filter(
                fn (EventSettlementCost $row): bool => $row->source_type === 'manual_payment'
                    && (int) $row->source_id === (int) $planCost->id,
            );

            if ($linked->isNotEmpty()) {
                return $linked->values();
            }

            return filled($planCost->actual_amount_pln) && self::isBookedPaymentStatus($planCost->payment_status)
                ? collect([$planCost])
                : collect();
        }

        if (filled($planCost->actual_amount_pln)
            && self::isBookedPaymentStatus($planCost->payment_status)
            && ! EventSettlementCost::isPaymentSourceType($planCost->source_type)) {
            return collect([$planCost]);
        }

        return collect();
    }

    public function resolveStatus(
        float $paidPln,
        float $plannedPln,
        ?Carbon $nextDue,
        bool $invoiceSettled = false,
        bool $overpaymentApproved = false,
        bool $zeroClosure = false,
    ): string {
        // Świadome domknięcie wpłatą 0 (pokryte gdzie indziej).
        if ($zeroClosure) {
            return self::STATUS_OK;
        }

        // Brak planu ≠ opłacone — osobny stan UI.
        if ($plannedPln <= self::TOLERANCE) {
            return self::STATUS_NA;
        }

        if (self::isOverpaid($paidPln, $plannedPln)) {
            return $overpaymentApproved
                ? self::STATUS_OK
                : self::STATUS_OVERPAYMENT_REVIEW;
        }

        if (self::isFullyPaid($paidPln, $plannedPln)) {
            return self::STATUS_OK;
        }

        // Pełna płatność faktury poniżej planu = rozliczone (oszczędność), nie „Do zapłaty”.
        if ($invoiceSettled && $paidPln > self::TOLERANCE) {
            return self::STATUS_OK;
        }

        if ($nextDue === null) {
            return self::STATUS_SHORTFALL;
        }

        if ($nextDue->endOfDay()->isFuture() || $nextDue->isToday()) {
            return self::STATUS_DUE;
        }

        return self::STATUS_OVERDUE;
    }

    /**
     * Status planu na podstawie sumy zaksięgowanych wpłat + domknięcia faktury.
     */
    public function planPaymentStatusFromAmounts(
        float $paidPln,
        float $plannedPln,
        bool $invoiceSettled = false,
        bool $overpaymentApproved = false,
        bool $zeroClosure = false,
    ): string {
        if ($zeroClosure) {
            return 'paid';
        }

        if ($plannedPln <= self::TOLERANCE) {
            return 'planned';
        }

        if (self::isOverpaid($paidPln, $plannedPln)) {
            return $overpaymentApproved ? 'paid' : 'partially_paid';
        }

        if (self::isFullyPaid($paidPln, $plannedPln)) {
            return 'paid';
        }

        if ($invoiceSettled && $paidPln > self::TOLERANCE) {
            return 'paid';
        }

        if ($paidPln > self::TOLERANCE) {
            return 'partially_paid';
        }

        return 'advance_required';
    }

    public function nextDueDate(EventSettlementCost $planCost, Collection $allCosts): ?Carbon
    {
        $dates = $this->paymentRowsForPlanCost($planCost, $allCosts)
            ->filter(fn (EventSettlementCost $row): bool => filled($row->advance_due_date))
            ->pluck('advance_due_date');

        if (filled($planCost->advance_due_date)
            && $this->paidPlnForPlanCost($planCost, $allCosts) < $this->plannedPlnForCost($planCost) - self::TOLERANCE) {
            $dates = $dates->push($planCost->advance_due_date);
        }

        $sorted = $dates
            ->filter()
            ->map(fn ($date) => $date instanceof Carbon ? $date : Carbon::parse($date))
            ->sortBy(fn (Carbon $date) => $date->timestamp)
            ->values();

        return $sorted->first();
    }

    public function statusBadgeHtml(string $status): string
    {
        [$bg, $fg] = self::$statusColors[$status] ?? ['#f3f4f6', '#374151'];
        $label = htmlspecialchars(self::$statusLabels[$status] ?? $status);

        return "<span class='admin-table-pill' style='background:{$bg};color:{$fg}'>{$label}</span>";
    }

    /**
     * Przelicza i zapisuje payment_status planu (+ flaga pending przy nadpłacie).
     *
     * @param  Collection<int, EventSettlementCost>  $allCosts
     */
    public function syncPlanPaymentStatus(EventSettlementCost $plan, Collection $allCosts): void
    {
        $paid = $this->paidPlnForPlanCost($plan, $allCosts);
        $planned = $this->indicativePlannedPlnForCost($plan);
        $payments = $this->paymentRowsForPlanCost($plan, $allCosts);
        $invoiceSettled = self::hasInvoiceSettlingPayment($payments);
        $overpaymentApproved = self::isOverpaymentApproved($plan);
        $zeroClosure = self::hasBookedZeroClosure($payments);

        $payload = [
            'payment_status' => $this->planPaymentStatusFromAmounts(
                $paid,
                $planned,
                $invoiceSettled,
                $overpaymentApproved,
                $zeroClosure,
            ),
        ];

        if (self::isOverpaid($paid, $planned) && ! $overpaymentApproved) {
            if (($plan->approval_status ?? null) !== 'pending') {
                $payload['approval_status'] = 'pending';
                $payload['reviewed_at'] = null;
                $payload['reviewed_by'] = null;
            }
        }

        $plan->update($payload);
    }

    /**
     * Sync statusu po zapisie wpłaty; opcjonalnie od razu zatwierdza nadpłatę.
     *
     * @param  Collection<int, EventSettlementCost>  $allCosts
     */
    public function syncPlanPaymentStatusAfterPayment(
        EventSettlementCost $plan,
        Collection $allCosts,
        bool $approveOverpayment = false,
        ?int $reviewedBy = null,
    ): void {
        $this->syncPlanPaymentStatus($plan, $allCosts);

        if (! $approveOverpayment) {
            return;
        }

        $plan = $plan->fresh() ?? $plan;
        $paid = $this->paidPlnForPlanCost($plan, $allCosts);
        $planned = $this->indicativePlannedPlnForCost($plan);
        if (! self::isOverpaid($paid, $planned)) {
            return;
        }

        $this->approveOverpayment($plan, $reviewedBy);
        $this->syncPlanPaymentStatus($plan->fresh() ?? $plan, $allCosts);
    }

    /**
     * Zatwierdza nadpłatę względem planu (ręczne potwierdzenie).
     */
    public function approveOverpayment(EventSettlementCost $plan, ?int $reviewedBy = null): void
    {
        $plan->update([
            'approval_status' => 'approved',
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * @return array<string, int>
     */
    public function countByStatus(Collection $evaluations): array
    {
        $counts = [
            self::STATUS_OK => 0,
            self::STATUS_NA => 0,
            self::STATUS_DUE => 0,
            self::STATUS_OVERDUE => 0,
            self::STATUS_SHORTFALL => 0,
            self::STATUS_OVERPAYMENT_REVIEW => 0,
        ];

        foreach ($evaluations as $row) {
            $status = (string) ($row['coverage_status'] ?? self::STATUS_SHORTFALL);
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }

        return $counts;
    }
}
