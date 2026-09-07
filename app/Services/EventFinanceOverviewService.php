<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Currency;
use App\Models\Event;
use App\Models\EventHotelStay;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\EventSettlementDocument;
use App\Models\VendorInvoice;
use App\Support\CurrencyAmountDisplay;
use App\Support\MoneyFormatter;
use App\Support\ProgramPointCostPricing;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Read-model uproszczonego ekranu Finanse imprezy:
 * szablon / planowane / zapłacono + health + dokumenty.
 */
final class EventFinanceOverviewService
{
    public const FILTER_ALL = 'all';

    public const FILTER_OFFICE = 'office';

    public const FILTER_PILOT = 'pilot';

    public const FILTER_OVERDUE = 'overdue';

    public const FILTER_NO_DOCUMENT = 'no_document';

    public const FILTER_UNGROUPED = 'ungrouped';

    public const SORT_NAME = 'name';

    public const SORT_CONTRACTOR = 'contractor';

    public const SORT_PLANNED = 'planned_pln';

    public const SORT_PAID = 'paid_pln';

    public const SORT_STATUS = 'ui_status';

    public const SORT_DUE = 'next_due_date';

    /** @var list<string> */
    public static array $sortableColumns = [
        self::SORT_NAME,
        self::SORT_CONTRACTOR,
        self::SORT_PLANNED,
        self::SORT_PAID,
        self::SORT_STATUS,
        self::SORT_DUE,
    ];

    /** Uproszczone etykiety statusu UI (nie 8 raw payment_status). */
    public static array $uiStatusLabels = [
        'paid' => 'Zapłacono',
        'partial' => 'Częściowo',
        'advance' => 'Zaliczka',
        'due' => 'Do zapłaty',
        'overdue' => 'Po terminie',
        'ok' => 'Zapłacono',
        'n/a' => 'Brak kwoty',
        'review' => 'Do sprawdzenia',
    ];

    public function __construct(
        private readonly SettlementPaymentHealthService $health,
        private readonly EventSettlementCostGroupService $groups,
        private readonly SettlementCostContractorResolver $contractors,
    ) {}

    /**
     * @return array{
     *   settlement_id: int|null,
     *   totals: array<string, float|string|null>,
     *   counts: array<string, int>,
     *   pilot_cash_paid: float,
     *   rows: list<array<string, mixed>>,
     *   groups: list<array<string, mixed>>,
     *   filter: string,
     *   group_filter: int|string|null,
     *   hide_zero: bool,
     *   hidden_zero_count: int
     * }
     */
    public function forEvent(
        Event $event,
        string $filter = self::FILTER_ALL,
        int|string|null $groupFilter = null,
        bool $hideZero = true,
        string $search = '',
        string $sortBy = self::SORT_NAME,
        string $sortDir = 'asc',
    ): array {
        $settlement = EventSettlement::findActiveForEvent($event);

        if (! $settlement) {
            return $this->emptyOverview($filter, $groupFilter, $hideZero, $search, $sortBy, $sortDir);
        }

        $searchKey = mb_strtolower(trim($search));
        $sortBy = in_array($sortBy, self::$sortableColumns, true) ? $sortBy : self::SORT_NAME;
        $sortDir = $sortDir === 'desc' ? 'desc' : 'asc';

        // Wersja cache: settlement + ostatnia zmiana kosztu (paid_by też bumpuje updated_at pozycji).
        $costsStamp = $settlement->costs()->max('updated_at');
        $cacheKey = sprintf(
            'event-finance-overview:%d:%s:%s:%d:%s:%s:%s:%s:%s',
            (int) $event->id,
            $filter,
            is_scalar($groupFilter) ? (string) $groupFilter : 'null',
            $hideZero ? 1 : 0,
            md5($searchKey),
            $sortBy,
            $sortDir,
            (string) ($settlement->updated_at?->timestamp ?? $settlement->id),
            $costsStamp ? (string) strtotime((string) $costsStamp) : '0',
        );

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 45, function () use ($event, $settlement, $filter, $groupFilter, $hideZero, $searchKey, $sortBy, $sortDir): array {
            return $this->buildOverviewForSettlement($event, $settlement, $filter, $groupFilter, $hideZero, $searchKey, $sortBy, $sortDir);
        });
    }

    public static function forgetOverviewCacheForEvent(int $eventId): void
    {
        // Klucz cache zawiera settlement.updated_at — MySQL DATETIME ma precyzję do sekundy,
        // więc zwykły touch() w tej samej sekundzie nie zmienia klucza (stale hit po usunięciu dok.).
        $settlement = EventSettlement::query()->where('event_id', $eventId)->orderByDesc('id')->first();
        if (! $settlement) {
            return;
        }

        $bump = now()->addSecond();
        $settlement->forceFill(['updated_at' => $bump])->saveQuietly();
    }

    /**
     * Pojedynczy wiersz drawera (także gdy pozycja jest „zerowa” / świeżo upsertowana).
     *
     * @return array<string, mixed>|null
     */
    public function rowForCostId(Event $event, int $costId): ?array
    {
        self::forgetOverviewCacheForEvent((int) $event->id);

        $overview = $this->forEvent(
            $event,
            self::FILTER_ALL,
            null,
            hideZero: false,
        );

        foreach ($overview['rows'] as $row) {
            if ((int) ($row['cost_id'] ?? 0) === $costId) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOverviewForSettlement(
        Event $event,
        EventSettlement $settlement,
        string $filter,
        int|string|null $groupFilter,
        bool $hideZero,
        string $searchKey = '',
        string $sortBy = self::SORT_NAME,
        string $sortDir = 'asc',
    ): array {
        // Świeża relacja w closure cache (unikamy stale po assign grup).
        $settlement = $settlement->fresh() ?? $settlement;
        $settlement->loadMissing([
            'costs.contractor',
            'costs.plannedCurrency',
            'costs.actualCurrency',
            'costs.financeGroup',
            'documents',
        ]);

        $event->loadMissing([
            'transportContractor',
            'driverContractor',
            'pilotContractor',
            'hotelStays.contractor',
            'transportProgramPoints.contractor',
            'hotelServiceProgramPoints.contractor',
        ]);

        $groupModels = $this->groups->ensureGroups($settlement);
        if (! $settlement->relationLoaded('costs')) {
            $settlement->load([
                'costs.contractor',
                'costs.plannedCurrency',
                'costs.actualCurrency',
                'costs.financeGroup',
            ]);
        }

        /** @var Collection<int, EventSettlementCost> $allCosts */
        $allCosts = $settlement->costs;
        $evaluations = $this->health->evaluateSettlement($settlement)->keyBy('cost_id');

        // Eager-load punktów programu (unikamy N+1 w calculationDisplayForPlanCost).
        $programPointIds = $allCosts
            ->filter(fn (EventSettlementCost $c): bool => $c->source_type === 'program_point' && $c->source_id)
            ->pluck('source_id')
            ->unique()
            ->filter()
            ->values()
            ->all();
        /** @var Collection<int, EventProgramPoint> $programPointsById */
        $programPointsById = $programPointIds === []
            ? collect()
            : EventProgramPoint::query()
                ->with('currency')
                ->whereIn('id', $programPointIds)
                ->get()
                ->keyBy('id');

        $hasHotelPlanCosts = $allCosts->contains(
            fn (EventSettlementCost $cost): bool => in_array($cost->source_type, [
                HotelStaySettlementSync::SOURCE_HOTEL,
                HotelStaySettlementSync::SOURCE_STAY,
            ], true)
        );

        $planCostIds = $this->health->listPlanCosts($allCosts)->pluck('id')->map(fn ($id) => (int) $id)->all();
        /** @var Collection<int, VendorInvoice> $invoicesByCostId */
        $invoicesByCostId = $planCostIds === [] || ! Schema::hasTable('vendor_invoices')
            ? collect()
            : \App\Models\VendorInvoice::query()
                ->with('contractor')
                ->whereIn('event_settlement_cost_id', $planCostIds)
                ->get()
                ->keyBy(fn (VendorInvoice $invoice): int => (int) $invoice->event_settlement_cost_id);

        $settlementDocuments = collect($settlement->documents ?? []);

        $rows = [];
        $sumCalc = 0.0;
        $sumPlan = 0.0;
        $sumPaid = 0.0;
        $sumOfficePaid = 0.0;
        $sumPilotPaid = 0.0;
        $pilotCashPaid = 0.0;
        $hiddenZeroCount = 0;
        /** @var array<string, float> $plannedForeignBuckets */
        $plannedForeignBuckets = [];
        /** @var array<string, float> $paidForeignBuckets */
        $paidForeignBuckets = [];
        $nonConvertedIndicativePln = 0.0;

        foreach ($this->health->listPlanCosts($allCosts) as $planCost) {
            $point = ($planCost->source_type === 'program_point' && $planCost->source_id)
                ? $programPointsById->get((int) $planCost->source_id)
                : null;

            // Nocleg z planu hotelowego ma własny wiersz accommodation_hotel* —
            // ukryj zdublowany program_point dla is_hotel (sumy i UI bez podwójnego liczenia).
            if (
                $hasHotelPlanCosts
                && $planCost->source_type === 'program_point'
                && $point instanceof EventProgramPoint
                && (bool) ($point->is_hotel ?? false)
            ) {
                continue;
            }

            /** @var array<string, mixed>|null $eval */
            $eval = $evaluations->get($planCost->id);
            $planned = (float) ($eval['planned_pln'] ?? $this->health->plannedPlnForCost($planCost));
            $statusPlanned = (float) ($eval['status_planned_pln'] ?? $this->health->indicativePlannedPlnForCost($planCost));
            $paid = (float) ($eval['paid_pln'] ?? 0);
            [$calc, $pricingHint, $calcLabel] = $this->calculationDisplayForPlanCost($planCost, $event, $point);
            $payments = $this->health->paymentRowsForPlanCost($planCost, $allCosts);
            $documents = $this->documentsForPlanCost($planCost, $settlement, $payments);
            $documentMeta = $this->documentMetaForPlanCost($planCost, $settlement, $payments, $documents);
            $paymentMeta = $this->paymentMetaForPlanCost($payments, $planCost);
            $paidBy = (string) ($planCost->paid_by ?? 'office');
            $coverage = (string) ($eval['coverage_status'] ?? SettlementPaymentHealthService::STATUS_SHORTFALL);
            $uiStatus = $this->mapUiStatus($coverage, $statusPlanned, $paid, $planCost, $eval);
            /** @var Carbon|null $nextDue */
            $nextDue = $eval['next_due_date'] ?? null;
            $groupId = $planCost->finance_group_id ? (int) $planCost->finance_group_id : null;
            $plannedLabel = $this->plannedAmountLabel($planCost, $planned);
            $invoiceSettled = (bool) ($eval['invoice_settled'] ?? false);
            $savingsPln = (float) ($eval['savings_pln'] ?? 0);
            $overpaymentPln = (float) ($eval['overpayment_pln'] ?? 0);
            $remainingPln = $invoiceSettled
                ? 0.0
                : max(0, round($planned - $paid, 2));
            $contractorMeta = $this->contractors->resolve(
                $planCost,
                $event,
                $point,
                $invoicesByCostId,
                $settlementDocuments,
            );

            $row = [
                'cost_id' => (int) $planCost->id,
                'finance_group_id' => $groupId,
                'name' => (string) ($planCost->name ?: 'Pozycja'),
                'contractor' => $contractorMeta['contractor'],
                'contractor_id' => $contractorMeta['contractor_id'],
                'contractor_details' => $contractorMeta['contractor_details'],
                'contractor_nip' => $contractorMeta['contractor_nip'],
                'contractor_email' => $contractorMeta['contractor_email'],
                'contractor_phone' => $contractorMeta['contractor_phone'],
                'contractor_source' => $contractorMeta['contractor_source'],
                'contractor_source_label' => $contractorMeta['contractor_source_label'],
                'contractor_search' => $contractorMeta['contractor_search'],
                'source_type' => $planCost->source_type,
                'source_label' => EventSettlementCost::$sourceTypeLabels[$planCost->source_type] ?? (string) $planCost->source_type,
                'paid_by' => $paidBy,
                'paid_by_label' => EventSettlementCost::$paidByOptions[$paidBy] ?? $paidBy,
                'calculation_pln' => $calc,
                'planned_pln' => $planned,
                'paid_pln' => $paid,
                'remaining_pln' => $remainingPln,
                'savings_pln' => $savingsPln,
                'overpayment_pln' => $overpaymentPln,
                'invoice_settled' => $invoiceSettled,
                'overpayment_approved' => (bool) ($eval['overpayment_approved'] ?? false),
                'needs_overpayment_approval' => $uiStatus === 'review',
                'calculation_label' => $calcLabel,
                'planned_label' => $plannedLabel,
                'paid_label' => $paymentMeta['paid_label'] ?? MoneyFormatter::format($paid, 'PLN'),
                'remaining_label' => $this->remainingAmountLabel(
                    $planCost,
                    $remainingPln,
                    $planned,
                    $paid,
                    $paymentMeta['payments'] ?? [],
                    $paymentMeta['paid_foreign'] ?? [],
                ),
                'savings_label' => $savingsPln > SettlementPaymentHealthService::TOLERANCE
                    ? 'Oszczędność '.MoneyFormatter::format($savingsPln, 'PLN')
                    : null,
                'overpayment_label' => $overpaymentPln > SettlementPaymentHealthService::TOLERANCE
                    ? 'Nadpłata '.MoneyFormatter::format($overpaymentPln, 'PLN')
                    : null,
                'pricing_hint' => $pricingHint,
                'coverage_status' => $coverage,
                'ui_status' => $uiStatus,
                'ui_status_label' => self::$uiStatusLabels[$uiStatus] ?? $uiStatus,
                'next_due_date' => $nextDue,
                'next_due_label' => $nextDue?->format('Y-m-d'),
                // documents_count = rzeczywiste pliki (filtr „Bez dokumentu” / kolumna Dok.).
                'documents_count' => $documentMeta['files_count'],
                'files_count' => $documentMeta['files_count'],
                'has_uploaded_file' => $documentMeta['has_uploaded_file'],
                'document_hint' => $documentMeta['hint'],
                'document_status_label' => $documentMeta['status_label'],
                'document_first_url' => $documentMeta['first_file_url'] ?? null,
                'documents' => $documents,
                'payments_count' => $paymentMeta['payments_count'],
                'advance_count' => $paymentMeta['advance_count'],
                'advance_paid_pln' => $paymentMeta['advance_paid_pln'],
                'payment_hint' => $paymentMeta['hint'],
                'payments' => $paymentMeta['payments'],
                'office_paid_pln' => $paymentMeta['office_paid_pln'] ?? 0.0,
                'pilot_paid_pln' => $paymentMeta['pilot_paid_pln'] ?? 0.0,
                'pilot_due_pln' => $paymentMeta['pilot_due_pln'] ?? null,
                'planned_amount' => (float) ($planCost->planned_amount ?? 0),
                'planned_currency_id' => $planCost->planned_currency_id,
                'planned_currency_symbol' => CurrencyAmountDisplay::symbol($planCost->plannedCurrency),
                'planned_convert_to_pln' => (bool) ($planCost->planned_convert_to_pln ?? true),
                'planned_rate' => $planCost->planned_rate,
                'plan_unit_price' => $point ? (float) ($point->unit_price ?? 0) : null,
                'plan_group_size' => $point !== null ? (int) ($point->group_size ?? 1) : null,
                'plan_quantity' => $point ? max(1, (int) ($point->quantity ?? 1)) : null,
                'plan_unit_price_label' => $point
                    ? ProgramPointPricingCalculator::unitPriceLabel($point->group_size).' (szablon)'
                    : null,
                'notes' => $planCost->notes,
                'is_program_point' => $planCost->source_type === 'program_point',
                'supports_reservation' => $this->supportsReservationForSourceType($planCost->source_type),
                'is_insurance' => $planCost->source_type === 'insurance_day',
                'approval_status' => (string) ($planCost->approval_status ?? 'pending'),
                'is_approved' => ($planCost->approval_status ?? 'pending') === 'approved',
                'approval_status_label' => EventSettlementCost::$approvalStatuses[$planCost->approval_status ?? 'pending']
                    ?? (string) ($planCost->approval_status ?? 'pending'),
            ];

            $sumCalc += $calc;
            $sumPlan += $planned;
            $sumPaid += $paid;
            $sumOfficePaid += (float) ($paymentMeta['office_paid_pln'] ?? 0);
            $sumPilotPaid += (float) ($paymentMeta['pilot_paid_pln'] ?? 0);
            $this->accumulateNonConvertedForeign($planCost, $plannedForeignBuckets, $nonConvertedIndicativePln);
            foreach ($paymentMeta['paid_foreign'] ?? [] as $symbol => $amount) {
                $paidForeignBuckets[$symbol] = ($paidForeignBuckets[$symbol] ?? 0) + (float) $amount;
            }

            foreach ($payments as $payment) {
                if (($payment->paid_by ?? '') === 'pilot' && ($payment->payment_method ?? '') === 'cash') {
                    $pilotCashPaid += (float) ($payment->actual_amount_pln ?? 0);
                }
            }

            if (! $this->rowMatchesFilter($row, $filter)) {
                continue;
            }

            // groupFilter NIE obcina wierszy tu — liczniki/sumy grup muszą być pełne.
            // Widoczność sekcji po filtrze grupy jest w blade ($showSection).

            if ($hideZero && $this->isZeroValueRow($row)) {
                $hiddenZeroCount++;

                continue;
            }

            if ($searchKey !== '' && ! $this->rowMatchesSearch($row, $searchKey)) {
                continue;
            }

            $rows[] = $row;
        }

        $rows = $this->sortRows($rows, $sortBy, $sortDir);

        // Suma kalkulacji z wierszy — bez fallbacku do planu (planned_cost_pln).
        $headerCalc = round($sumCalc, 2);
        $clientDue = round((float) ($settlement->participant_due_pln ?? 0), 2);
        $clientPaid = round((float) ($settlement->participant_paid_pln ?? 0), 2);

        $grouped = [];
        foreach ($groupModels as $group) {
            $groupRows = array_values(array_filter(
                $rows,
                fn (array $r): bool => (int) ($r['finance_group_id'] ?? 0) === (int) $group->id,
            ));
            $grouped[] = $this->buildGroupSummary(
                id: (int) $group->id,
                name: (string) $group->name,
                key: (string) $group->key,
                isSystem: (bool) $group->is_system,
                sortOrder: (int) $group->sort_order,
                groupRows: $groupRows,
            );
        }

        $ungroupedRows = array_values(array_filter(
            $rows,
            fn (array $r): bool => empty($r['finance_group_id']),
        ));
        if ($ungroupedRows !== []) {
            $grouped[] = $this->buildGroupSummary(
                id: 0,
                name: 'Bez grupy',
                key: 'ungrouped',
                isSystem: true,
                sortOrder: 9999,
                groupRows: $ungroupedRows,
            );
        }

        $calcPlanDelta = round($headerCalc - $sumPlan, 2);
        $foreignHint = $plannedForeignBuckets === []
            ? null
            : ('Waluty bez przeliczenia do sumy planu: '
                .CurrencyAmountDisplay::formatMixedTotal(0, $plannedForeignBuckets, 2)
                .' (≈ '.MoneyFormatter::format($nonConvertedIndicativePln, 'PLN').')');

        return [
            'settlement_id' => (int) $settlement->id,
            'totals' => [
                'calculation_pln' => round($headerCalc, 2),
                'planned_pln' => round($sumPlan, 2),
                'paid_pln' => round($sumPaid, 2),
                'office_paid_pln' => round($sumOfficePaid, 2),
                'pilot_paid_pln' => round($sumPilotPaid, 2),
                'remaining_pln' => round(max(0, $sumPlan - $sumPaid), 2),
                'client_due_pln' => $clientDue,
                'client_paid_pln' => $clientPaid,
                'calculation_label' => MoneyFormatter::format($headerCalc, 'PLN'),
                'planned_label' => CurrencyAmountDisplay::formatMixedTotal(round($sumPlan, 2), $plannedForeignBuckets, 2),
                'paid_label' => CurrencyAmountDisplay::formatMixedTotal(round($sumPaid, 2), $paidForeignBuckets, 2),
                'office_paid_label' => MoneyFormatter::format(round($sumOfficePaid, 2), 'PLN'),
                'pilot_paid_label' => MoneyFormatter::format(round($sumPilotPaid, 2), 'PLN'),
                'remaining_label' => CurrencyAmountDisplay::formatMixedTotal(
                    round(max(0, $sumPlan - $sumPaid), 2),
                    $this->remainingForeignBuckets($plannedForeignBuckets, $paidForeignBuckets),
                    2,
                ),
                'client_due_label' => MoneyFormatter::format($clientDue, 'PLN'),
                'client_paid_label' => MoneyFormatter::format($clientPaid, 'PLN'),
                'planned_foreign' => $plannedForeignBuckets,
                'paid_foreign' => $paidForeignBuckets,
                'non_converted_indicative_pln' => round($nonConvertedIndicativePln, 2),
                'calc_plan_delta_pln' => $calcPlanDelta,
                'calc_plan_hint' => $foreignHint,
            ],
            'counts' => $this->health->countByStatus($evaluations->values()),
            'pilot_cash_paid' => round($pilotCashPaid, 2),
            'pilot_cash_label' => MoneyFormatter::format($pilotCashPaid, 'PLN'),
            'pilot_cash' => $this->pilotCashSummary($settlement, $pilotCashPaid),
            'rows' => $rows,
            'groups' => $grouped,
            'contractor_rollups' => $this->buildContractorRollups($rows),
            'filter' => $filter,
            'group_filter' => $groupFilter,
            'hide_zero' => $hideZero,
            'hidden_zero_count' => $hiddenZeroCount,
            'search' => $searchKey,
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
        ];
    }

    /**
     * @return array{
     *   settlement_id: null,
     *   totals: array<string, float|string|null>,
     *   counts: array<string, int>,
     *   pilot_cash_paid: float,
     *   rows: list<array<string, mixed>>,
     *   groups: list<array<string, mixed>>,
     *   filter: string,
     *   group_filter: int|string|null,
     *   hide_zero: bool,
     *   hidden_zero_count: int,
     *   search: string,
     *   sort_by: string,
     *   sort_dir: string
     * }
     */
    private function emptyOverview(
        string $filter,
        int|string|null $groupFilter,
        bool $hideZero = true,
        string $search = '',
        string $sortBy = self::SORT_NAME,
        string $sortDir = 'asc',
    ): array {
        $zeroLabel = MoneyFormatter::format(0, 'PLN');

        return [
            'settlement_id' => null,
            'totals' => [
                'calculation_pln' => 0.0,
                'planned_pln' => 0.0,
                'paid_pln' => 0.0,
                'office_paid_pln' => 0.0,
                'pilot_paid_pln' => 0.0,
                'remaining_pln' => 0.0,
                'client_due_pln' => 0.0,
                'client_paid_pln' => 0.0,
                'calculation_label' => $zeroLabel,
                'planned_label' => $zeroLabel,
                'paid_label' => $zeroLabel,
                'office_paid_label' => $zeroLabel,
                'pilot_paid_label' => $zeroLabel,
                'remaining_label' => $zeroLabel,
                'client_due_label' => $zeroLabel,
                'client_paid_label' => $zeroLabel,
                'planned_foreign' => [],
                'non_converted_indicative_pln' => 0.0,
                'calc_plan_delta_pln' => 0.0,
                'calc_plan_hint' => null,
            ],
            'counts' => $this->health->countByStatus(collect()),
            'pilot_cash_paid' => 0.0,
            'pilot_cash_label' => $zeroLabel,
            'pilot_cash' => $this->emptyPilotCashSummary($zeroLabel),
            'rows' => [],
            'groups' => [],
            'contractor_rollups' => [],
            'filter' => $filter,
            'group_filter' => $groupFilter,
            'hide_zero' => $hideZero,
            'hidden_zero_count' => 0,
            'search' => mb_strtolower(trim($search)),
            'sort_by' => in_array($sortBy, self::$sortableColumns, true) ? $sortBy : self::SORT_NAME,
            'sort_dir' => $sortDir === 'desc' ? 'desc' : 'asc',
        ];
    }

    /**
     * Ile gotówki należy przygotować dla pilota (z PilotCashPreparation)
     * vs ile już rozliczono gotówką w kosztach.
     *
     * @return array{
     *   needed_pln: float,
     *   needed_label: string,
     *   spent_pln: float,
     *   spent_label: string,
     *   lines: list<array{code: string, amount_label: string, pln_label: string}>,
     *   has_pilot_costs: bool
     * }
     */
    private function pilotCashSummary(EventSettlement $settlement, float $spentCashPln): array
    {
        $zeroLabel = MoneyFormatter::format(0, 'PLN');

        if (! Schema::hasTable('pilot_cash_preparations')) {
            return $this->emptyPilotCashSummary($zeroLabel, $spentCashPln);
        }

        // Tylko odczyt przygotowań — przeliczanie jest na saved/deleted kosztów.
        $settlement->loadMissing('pilotCashPreparations.currency');

        $lines = [];
        $neededPln = 0.0;
        $plnPart = 0.0;

        foreach ($settlement->pilotCashPreparations as $cash) {
            $calculated = (float) ($cash->calculated_amount ?? 0);
            $pln = (float) ($cash->pln_equivalent ?? 0);

            if ($calculated <= SettlementPaymentHealthService::TOLERANCE && $pln <= SettlementPaymentHealthService::TOLERANCE) {
                continue;
            }

            $currency = $cash->currency;
            $code = strtoupper((string) ($currency?->symbol ?? $currency?->code ?? 'PLN'));
            $neededPln += $pln > SettlementPaymentHealthService::TOLERANCE
                ? $pln
                : ($code === 'PLN' ? $calculated : 0.0);

            if ($code === 'PLN') {
                $amountPln = $calculated > 0 ? $calculated : $pln;
                $plnPart += $amountPln;
                $lines[] = [
                    'code' => 'PLN',
                    'amount' => $amountPln,
                    'amount_label' => MoneyFormatter::format($amountPln, 'PLN'),
                    'pln_label' => MoneyFormatter::format($amountPln, 'PLN'),
                    'display_label' => MoneyFormatter::format($amountPln, 'PLN'),
                ];

                continue;
            }

            $rate = $calculated > 0 && $pln > 0 ? ($pln / $calculated) : (float) ($currency?->exchange_rate ?? 1);
            $display = CurrencyAmountDisplay::formatIndicative($calculated, $currency, $rate);

            $lines[] = [
                'code' => $code,
                'amount' => $calculated,
                'amount_label' => MoneyFormatter::format($calculated, $code),
                'pln_label' => MoneyFormatter::format($pln > 0 ? $pln : round($calculated * max($rate, 0.0001), 2), 'PLN'),
                'display_label' => $display,
            ];
        }

        $pilotPlanCosts = $settlement->costs
            ->filter(fn (EventSettlementCost $cost): bool => ($cost->paid_by ?? '') === 'pilot'
                && ($cost->payment_status ?? '') !== 'cancelled'
                && $this->health->isEvaluablePlanCost($cost));

        // Fallback: gdy brak preparations — zbuduj składowe z kosztów Pilot.
        if ($lines === [] && $pilotPlanCosts->isNotEmpty()) {
            foreach ($pilotPlanCosts as $cost) {
                $amount = (float) ($cost->planned_amount ?? 0);
                if ($amount <= SettlementPaymentHealthService::TOLERANCE) {
                    continue;
                }

                $symbol = CurrencyAmountDisplay::symbol($cost->plannedCurrency);
                $rate = (float) ($cost->planned_rate ?? ($cost->plannedCurrency?->exchange_rate ?? 1));

                if ($symbol === 'PLN') {
                    $plnPart += $amount;
                    $neededPln += $amount;
                    $lines[] = [
                        'code' => 'PLN',
                        'amount' => $amount,
                        'amount_label' => MoneyFormatter::format($amount, 'PLN'),
                        'pln_label' => MoneyFormatter::format($amount, 'PLN'),
                        'display_label' => MoneyFormatter::format($amount, 'PLN'),
                    ];

                    continue;
                }

                $indicative = round($amount * max($rate, 0.0001), 2);
                $neededPln += $indicative;
                $lines[] = [
                    'code' => $symbol,
                    'amount' => $amount,
                    'amount_label' => MoneyFormatter::format($amount, $symbol),
                    'pln_label' => MoneyFormatter::format($indicative, 'PLN'),
                    'display_label' => CurrencyAmountDisplay::formatIndicative($amount, $cost->plannedCurrency, $rate),
                ];
            }
        }

        $composedLabel = $this->composePilotCashLabel($lines, $plnPart, $neededPln);
        $foreignLines = array_values(array_filter(
            $lines,
            fn (array $line): bool => ($line['code'] ?? 'PLN') !== 'PLN',
        ));

        return [
            'needed_pln' => round($neededPln, 2),
            'needed_pln_part' => round($plnPart, 2),
            // Główna etykieta: „1 200,00 PLN + 693,00 EUR (≈ 3 014,55 PLN)”
            'needed_label' => $composedLabel,
            'needed_detail_label' => $composedLabel,
            'spent_pln' => round($spentCashPln, 2),
            'spent_label' => MoneyFormatter::format($spentCashPln, 'PLN'),
            'lines' => $lines,
            'foreign_lines' => $foreignLines,
            'has_pilot_costs' => $pilotPlanCosts->isNotEmpty() || $lines !== [],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function composePilotCashLabel(array $lines, float $plnPart, float $neededPlnTotal): string
    {
        $parts = [];

        if ($plnPart > SettlementPaymentHealthService::TOLERANCE) {
            $parts[] = MoneyFormatter::format($plnPart, 'PLN');
        }

        foreach ($lines as $line) {
            if (($line['code'] ?? 'PLN') === 'PLN') {
                continue;
            }
            $parts[] = (string) ($line['display_label'] ?? $line['amount_label'] ?? '');
        }

        if ($parts !== []) {
            return implode(' + ', array_filter($parts));
        }

        return MoneyFormatter::format($neededPlnTotal, 'PLN');
    }

    /**
     * @return array{
     *   needed_pln: float,
     *   needed_label: string,
     *   spent_pln: float,
     *   spent_label: string,
     *   lines: list<array{code: string, amount_label: string, pln_label: string}>,
     *   has_pilot_costs: bool
     * }
     */
    private function emptyPilotCashSummary(string $zeroLabel, float $spentCashPln = 0.0): array
    {
        return [
            'needed_pln' => 0.0,
            'needed_pln_part' => 0.0,
            'needed_label' => $zeroLabel,
            'needed_detail_label' => $zeroLabel,
            'spent_pln' => round($spentCashPln, 2),
            'spent_label' => MoneyFormatter::format($spentCashPln, 'PLN'),
            'lines' => [],
            'foreign_lines' => [],
            'has_pilot_costs' => false,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $groupRows
     * @return array<string, mixed>
     */
    private function buildGroupSummary(
        int $id,
        string $name,
        string $key,
        bool $isSystem,
        int $sortOrder,
        array $groupRows,
    ): array {
        $gPlan = array_sum(array_column($groupRows, 'planned_pln'));
        $gPaid = array_sum(array_column($groupRows, 'paid_pln'));
        $gCalc = array_sum(array_column($groupRows, 'calculation_pln'));
        $gRemaining = max(0, round($gPlan - $gPaid, 2));
        $foreign = [];
        $paidForeign = [];
        foreach ($groupRows as $row) {
            if (empty($row['planned_convert_to_pln'])) {
                $amount = (float) ($row['planned_amount'] ?? 0);
                $symbol = (string) ($row['planned_currency_symbol'] ?? 'PLN');
                if ($amount > 0 && $symbol !== 'PLN') {
                    $foreign[$symbol] = ($foreign[$symbol] ?? 0) + $amount;
                }
            }
            foreach ($row['payments'] ?? [] as $payment) {
                $symbol = (string) ($payment['currency_symbol'] ?? 'PLN');
                $amountRaw = (float) ($payment['amount'] ?? 0);
                $convert = (bool) ($payment['convert_to_pln'] ?? false);
                if ($symbol !== 'PLN' && ! $convert && $amountRaw > 0) {
                    $paidForeign[$symbol] = ($paidForeign[$symbol] ?? 0) + $amountRaw;
                }
            }
        }

        return [
            'id' => $id,
            'name' => $name,
            'key' => $key,
            'is_system' => $isSystem,
            'sort_order' => $sortOrder,
            'rows' => $groupRows,
            'count' => count($groupRows),
            'calculation_pln' => round($gCalc, 2),
            'planned_pln' => round($gPlan, 2),
            'paid_pln' => round($gPaid, 2),
            'remaining_pln' => $gRemaining,
            'calculation_label' => MoneyFormatter::format($gCalc, 'PLN'),
            'planned_label' => CurrencyAmountDisplay::formatMixedTotal(round($gPlan, 2), $foreign, 2),
            'paid_label' => CurrencyAmountDisplay::formatMixedTotal(round($gPaid, 2), $paidForeign, 2),
            'remaining_label' => CurrencyAmountDisplay::formatMixedTotal(
                $gRemaining,
                $this->remainingForeignBuckets($foreign, $paidForeign),
                2,
            ),
        ];
    }

    /**
     * Sumy plan / zapłacono / pozostało po kontrahencie (wszystkie kategorie kosztów).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function buildContractorRollups(array $rows): array
    {
        /** @var array<int, array<string, mixed>> $byContractor */
        $byContractor = [];

        foreach ($rows as $row) {
            $contractorId = (int) ($row['contractor_id'] ?? 0);
            if ($contractorId <= 0) {
                continue;
            }

            if (! isset($byContractor[$contractorId])) {
                $byContractor[$contractorId] = [
                    'contractor_id' => $contractorId,
                    'contractor' => (string) ($row['contractor'] ?? 'Kontrahent'),
                    'planned_pln' => 0.0,
                    'paid_pln' => 0.0,
                    'remaining_pln' => 0.0,
                    'cost_count' => 0,
                    'cost_ids' => [],
                    'costs' => [],
                ];
            }

            $planned = round((float) ($row['planned_pln'] ?? 0), 2);
            $paid = round((float) ($row['paid_pln'] ?? 0), 2);
            $remaining = round((float) ($row['remaining_pln'] ?? max(0, $planned - $paid)), 2);

            $byContractor[$contractorId]['planned_pln'] += $planned;
            $byContractor[$contractorId]['paid_pln'] += $paid;
            $byContractor[$contractorId]['remaining_pln'] += $remaining;
            $byContractor[$contractorId]['cost_count']++;
            $byContractor[$contractorId]['cost_ids'][] = (int) ($row['cost_id'] ?? 0);
            $byContractor[$contractorId]['costs'][] = [
                'cost_id' => (int) ($row['cost_id'] ?? 0),
                'name' => (string) ($row['name'] ?? 'Pozycja'),
                'source_label' => (string) ($row['source_label'] ?? ''),
                'planned_pln' => $planned,
                'paid_pln' => $paid,
                'remaining_pln' => $remaining,
                'planned_label' => (string) ($row['planned_label'] ?? MoneyFormatter::format($planned, 'PLN')),
                'paid_label' => (string) ($row['paid_label'] ?? MoneyFormatter::format($paid, 'PLN')),
                'remaining_label' => (string) ($row['remaining_label'] ?? MoneyFormatter::format($remaining, 'PLN')),
                'ui_status' => (string) ($row['ui_status'] ?? ''),
                'ui_status_label' => (string) ($row['ui_status_label'] ?? ''),
            ];
        }

        $rollups = [];
        foreach ($byContractor as $rollup) {
            $planned = round((float) $rollup['planned_pln'], 2);
            $paid = round((float) $rollup['paid_pln'], 2);
            $remaining = round((float) $rollup['remaining_pln'], 2);
            $rollups[] = [
                'contractor_id' => (int) $rollup['contractor_id'],
                'contractor' => (string) $rollup['contractor'],
                'planned_pln' => $planned,
                'paid_pln' => $paid,
                'remaining_pln' => $remaining,
                'cost_count' => (int) $rollup['cost_count'],
                'cost_ids' => array_values(array_filter(array_map('intval', $rollup['cost_ids']))),
                'costs' => $rollup['costs'],
                'planned_label' => MoneyFormatter::format($planned, 'PLN'),
                'paid_label' => MoneyFormatter::format($paid, 'PLN'),
                'remaining_label' => MoneyFormatter::format($remaining, 'PLN'),
            ];
        }

        usort(
            $rollups,
            fn (array $a, array $b): int => mb_strtolower((string) $a['contractor']) <=> mb_strtolower((string) $b['contractor'])
        );

        return $rollups;
    }

    /**
     * @param  array<string, float>  $buckets
     */
    private function accumulateNonConvertedForeign(
        EventSettlementCost $planCost,
        array &$buckets,
        float &$indicativePln,
    ): void {
        if ((bool) ($planCost->planned_convert_to_pln ?? true)) {
            return;
        }

        $amount = (float) ($planCost->planned_amount ?? 0);
        if ($amount <= 0) {
            return;
        }

        $currency = $planCost->plannedCurrency;
        $symbol = CurrencyAmountDisplay::symbol($currency);
        if ($symbol === 'PLN') {
            return;
        }

        $buckets[$symbol] = ($buckets[$symbol] ?? 0) + $amount;
        $rate = (float) ($planCost->planned_rate ?? ($currency?->exchange_rate ?? 1));
        if ($rate <= 0) {
            $rate = 1.0;
        }
        $indicativePln += round($amount * $rate, 2);
    }

    /**
     * @return array{0: float, 1: ?string}
     */
    public function calculationWithHintForPlanCost(EventSettlementCost $planCost, Event $event): array
    {
        [$pln, $hint] = $this->calculationDisplayForPlanCost($planCost, $event);

        return [$pln, $hint];
    }

    /**
     * @return array{0: float, 1: ?string, 2: string}
     */
    public function calculationDisplayForPlanCost(
        EventSettlementCost $planCost,
        Event $event,
        ?EventProgramPoint $preloadedPoint = null,
    ): array {
        if ($planCost->source_type === 'program_point' && $planCost->source_id) {
            $point = $preloadedPoint ?? EventProgramPoint::query()->with('currency')->find($planCost->source_id);
            if ($point) {
                // Zawsze na żywo: 1za1 / XzaY + headcount (płacący + gratis).
                // Nie używamy stale calculated_price (często w walucie źródłowej / bez gratisów).
                $breakdown = ProgramPointCostPricing::breakdown($point, $event);
                $rate = (float) ($point->currency?->exchange_rate ?? 1);
                $convert = (bool) ($breakdown['convert_to_pln'] ?? false);
                $total = (float) $breakdown['total'];
                // Etykieta respektuje convert_to_pln; total_pln_finance zostaje
                // ekwiwalentem do porównań plan ↔ kosztorys (także bez przeliczenia).
                $label = $convert
                    ? CurrencyAmountDisplay::formatIndicative($total, $point->currency, $rate)
                    : CurrencyAmountDisplay::format($total, $point->currency, convertToPln: false);

                return [$breakdown['total_pln_finance'], $breakdown['hint'], $label];
            }
        }

        $pln = $this->calculationPlnForPlanCost($planCost, $event, $preloadedPoint);

        return [$pln, null, MoneyFormatter::format($pln, 'PLN')];
    }

    public function plannedAmountLabel(EventSettlementCost $planCost, float $plannedPlnFallback): string
    {
        $amount = (float) ($planCost->planned_amount ?? 0);
        $currency = $planCost->relationLoaded('plannedCurrency')
            ? $planCost->plannedCurrency
            : $planCost->plannedCurrency()->first();

        $symbol = CurrencyAmountDisplay::symbol($currency instanceof Currency ? $currency : null);
        $rate = (float) ($planCost->planned_rate ?? ($currency?->exchange_rate ?? 1));
        $convertToPln = (bool) ($planCost->planned_convert_to_pln ?? true);

        if ($amount > 0) {
            // convert_to_pln steruje widokiem ≈ PLN; suma PLN i tak pomija pozycje bez przeliczenia.
            if ($symbol !== 'PLN' && ! $convertToPln) {
                return CurrencyAmountDisplay::format($amount, $currency instanceof Currency ? $currency : null, false);
            }

            if ($symbol !== 'PLN') {
                return CurrencyAmountDisplay::formatIndicative($amount, $currency, $rate);
            }

            return CurrencyAmountDisplay::format($amount, $currency instanceof Currency ? $currency : null, false);
        }

        if ($plannedPlnFallback > 0) {
            return MoneyFormatter::format($plannedPlnFallback, 'PLN');
        }

        return '—';
    }

    /**
     * @param  array<string, float>  $planned
     * @param  array<string, float>  $paid
     * @return array<string, float>
     */
    private function remainingForeignBuckets(array $planned, array $paid): array
    {
        $remaining = [];
        foreach ($planned as $symbol => $amount) {
            $left = round(max(0, (float) $amount - (float) ($paid[$symbol] ?? 0)), 2);
            if ($left > 0) {
                $remaining[$symbol] = $left;
            }
        }

        return $remaining;
    }

    private function remainingAmountLabel(
        EventSettlementCost $planCost,
        float $remainingPln,
        float $plannedPln,
        float $paidPln,
        array $payments = [],
        array $paidForeignBuckets = [],
    ): string {
        $amount = (float) ($planCost->planned_amount ?? 0);
        $currency = $planCost->plannedCurrency;
        $symbol = CurrencyAmountDisplay::symbol($currency);
        $rate = (float) ($planCost->planned_rate ?? ($currency?->exchange_rate ?? 1));
        if ($rate <= 0) {
            $rate = 1.0;
        }

        // Obca waluta planu: reszta w walucie źródłowej; ≈ PLN tylko gdy przeliczamy.
        if ($amount > 0 && $symbol !== 'PLN') {
            $convertToPln = (bool) ($planCost->planned_convert_to_pln ?? true);
            $paidForeign = (float) ($paidForeignBuckets[$symbol] ?? 0);
            if ($paidForeign <= 0.009 && $convertToPln && $rate > 0) {
                // Legacy: wpłaty przeliczone — szacuj z PLN.
                $paidForeign = round($paidPln / $rate, 2);
            } elseif ($paidForeign <= 0.009) {
                foreach ($payments as $payment) {
                    $paySymbol = (string) ($payment['currency_symbol'] ?? '');
                    if ($paySymbol === $symbol) {
                        $paidForeign += (float) ($payment['amount'] ?? 0);
                    }
                }
                $paidForeign = round($paidForeign, 2);
            }
            $remainingForeign = max(0, round($amount - $paidForeign, 2));

            return $convertToPln
                ? CurrencyAmountDisplay::formatIndicative($remainingForeign, $currency, $rate)
                : CurrencyAmountDisplay::format($remainingForeign, $currency, false);
        }

        if ($remainingPln <= 0 && $plannedPln <= 0) {
            return '—';
        }

        return MoneyFormatter::format($remainingPln, 'PLN');
    }

    public function calculationPlnForPlanCost(
        EventSettlementCost $planCost,
        Event $event,
        ?EventProgramPoint $preloadedPoint = null,
    ): float {
        if ($planCost->source_type === 'program_point' && $planCost->source_id) {
            $point = $preloadedPoint ?? EventProgramPoint::query()->with('currency')->find($planCost->source_id);
            if ($point) {
                return ProgramPointCostPricing::totalPlnForFinance($point, $event);
            }
        }

        if (in_array($planCost->source_type, ['transport', TransportContractorSettlementSync::SOURCE_CONTRACTOR], true)) {
            try {
                $calculator = new EventTransportCostCalculator($event);
                $fromBus = round($calculator->busCalculatedTransportCost(), 2);
                // Przy ryczałcie bez autokaru nie ma pierwotnego kosztorysu z km — pokaż ryczałt.
                // Gdy jest autokar: zawsze pierwotna kalkulacja (ryczałt żyje w Planie).
                if ($fromBus > 0.009) {
                    // Przy grupowaniu po przewoźniku pełna kalkulacja trafia tylko na grupę główną.
                    if (
                        $planCost->source_type === TransportContractorSettlementSync::SOURCE_CONTRACTOR
                        && $planCost->source_id
                        && ! app(TransportContractorSettlementSync::class)
                            ->isPrimaryContractor($event, (int) $planCost->source_id)
                    ) {
                        return $this->health->plannedPlnForCost($planCost);
                    }

                    return $fromBus;
                }

                return round($calculator->effectiveTransportCost(), 2);
            } catch (\Throwable) {
                // fallback poniżej
            }
        }

        if ($planCost->source_type === 'accommodation') {
            try {
                return round((float) app(SettlementAggregateFinanceService::class)
                    ->resolveReferenceTotalPln($event, (string) $planCost->source_type), 2);
            } catch (\Throwable) {
                // fallback poniżej
            }
        }

        if ($planCost->source_type === 'accommodation_hotel' && $planCost->source_id) {
            return app(HotelStaySettlementSync::class)
                ->referenceTotalPlnForContractor($event, (int) $planCost->source_id);
        }

        if ($planCost->source_type === 'accommodation_hotel_stay' && $planCost->source_id) {
            $stay = EventHotelStay::query()->with('roomLines.currency')->find((int) $planCost->source_id);

            return $stay
                ? app(HotelStaySettlementSync::class)->referenceTotalPlnForStay($event, $stay)
                : $this->health->plannedPlnForCost($planCost);
        }

        return $this->health->plannedPlnForCost($planCost);
    }

    /**
     * @param  Collection<int, EventSettlementCost>  $payments
     * @return list<array<string, mixed>>
     */
    private function documentsForPlanCost(
        EventSettlementCost $planCost,
        EventSettlement $settlement,
        Collection $payments,
    ): array {
        $ids = collect([$planCost->id])->merge($payments->pluck('id'))->map(fn ($id) => (int) $id)->all();
        $docs = collect($settlement->documents ?? []);

        return $docs
            ->filter(function ($doc) use ($ids): bool {
                $linked = collect($doc->linked_cost_ids ?? [])->map(fn ($id) => (int) $id)->all();

                return count(array_intersect($ids, $linked)) > 0;
            })
            ->map(function ($doc): array {
                $files = collect($doc->files ?? [])
                    ->filter(fn ($path) => is_string($path) && $path !== '')
                    ->map(fn (string $path): array => [
                        'path' => $path,
                        'name' => basename($path),
                        'url' => \Illuminate\Support\Facades\Storage::disk('public')->url($path),
                    ])
                    ->values()
                    ->all();

                return [
                    'id' => (int) $doc->id,
                    'type' => $doc->document_type,
                    'type_label' => EventSettlementDocument::$documentTypes[$doc->document_type] ?? (string) $doc->document_type,
                    'number' => $doc->document_number,
                    'notes' => $doc->notes,
                    'attach_to_pilot_pdf' => (bool) ($doc->attach_to_pilot_pdf ?? false),
                    'attach_to_hotel_pdf' => (bool) ($doc->attach_to_hotel_pdf ?? false),
                    'attach_to_driver_pdf' => (bool) ($doc->attach_to_driver_pdf ?? false),
                    'attach_to_folder_pdf' => (bool) ($doc->attach_to_folder_pdf ?? false),
                    'files' => $files,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     * @param  Collection<int, EventSettlementCost>  $payments
     * @return array{
     *   files_count: int,
     *   has_uploaded_file: bool,
     *   hint: string,
     *   status_label: string,
     *   first_file_url: string|null
     * }
     */
    private function documentMetaForPlanCost(
        EventSettlementCost $planCost,
        EventSettlement $settlement,
        Collection $payments,
        array $documents,
    ): array {
        $fileEntries = collect();
        foreach ($documents as $doc) {
            if (! is_array($doc)) {
                continue;
            }

            foreach (($doc['files'] ?? []) as $file) {
                if (! is_array($file) || blank($file['url'] ?? null)) {
                    continue;
                }

                $fileEntries->push($file);
            }
        }

        $filesCount = $fileEntries->count();
        $firstFileUrl = $filesCount > 0 ? (string) ($fileEntries->first()['url'] ?? '') : null;
        $firstFileUrl = $firstFileUrl !== '' ? $firstFileUrl : null;
        $numbers = $payments
            ->map(fn (EventSettlementCost $p): ?string => $p->document_number ?: $p->invoice_number)
            ->filter(fn (?string $n): bool => filled($n))
            ->unique()
            ->values();

        if ($filesCount > 0) {
            $first = (string) ($fileEntries->first()['name'] ?? 'plik');
            $type = (string) (($documents[0]['type_label'] ?? null) ?: 'Plik');
            $number = (string) (($documents[0]['number'] ?? null) ?: ($numbers->first() ?? ''));
            $hint = $number !== ''
                ? $type.': nr '.$number
                : ($filesCount === 1
                    ? $type
                    : $type.' ('.$filesCount.' pl.)');
            $status = 'Faktura / dokument wgrany: '.$hint
                .($number === '' ? ' · '.$first : '');

            return [
                'files_count' => $filesCount,
                'has_uploaded_file' => true,
                'hint' => $hint,
                'status_label' => $status,
                'first_file_url' => $firstFileUrl,
            ];
        }

        if ($numbers->isNotEmpty()) {
            $joined = $numbers->take(2)->implode(', ');

            return [
                'files_count' => 0,
                'has_uploaded_file' => false,
                'hint' => 'Nr '.$joined.' (bez pliku)',
                'status_label' => 'Brak wgranego pliku — jest numer: '.$joined,
                'first_file_url' => null,
            ];
        }

        return [
            'files_count' => 0,
            'has_uploaded_file' => false,
            'hint' => 'Brak pliku',
            'status_label' => 'Brak wgranego pliku faktury / dowodu',
            'first_file_url' => null,
        ];
    }

    /**
     * @param  Collection<int, EventSettlementCost>  $payments
     * @return array{
     *   payments_count: int,
     *   advance_count: int,
     *   advance_paid_pln: float,
     *   paid_label: string,
     *   hint: ?string,
     *   payments: list<array<string, mixed>>
     * }
     */
    private function paymentMetaForPlanCost(Collection $payments, EventSettlementCost $planCost): array
    {
        if (Schema::hasColumn('event_settlement_costs', 'reservation_id')
            && method_exists($payments, 'loadMissing')) {
            $payments->loadMissing('reservation');
        }
        $planCurrency = $planCost->plannedCurrency;
        $planRate = (float) ($planCost->planned_rate ?? ($planCurrency?->exchange_rate ?? 1));

        $planSymbol = CurrencyAmountDisplay::symbol($planCurrency);
        $planIsForeign = $planSymbol !== 'PLN';

        $mapped = $payments->map(function (EventSettlementCost $p) use ($planCurrency, $planRate, $planIsForeign, $planCost): array {
            $convertToPln = (bool) ($p->planned_convert_to_pln ?? false);
            $amountPln = (float) ($p->actual_amount_pln ?? 0);
            $amountRaw = (float) ($p->actual_amount ?? 0);
            // Zaplanowana wpłata (termin bez paid_at): kwota siedzi w advance_amount / planned_amount.
            if ($amountRaw <= 0.009) {
                $amountRaw = (float) ($p->advance_amount ?? $p->planned_amount ?? 0);
            }
            $currencyId = $p->actual_currency_id ?? $p->planned_currency_id ?? $planCost->planned_currency_id;
            $isForeign = CurrencyAmountDisplay::isForeignCurrency($currencyId);
            if ($amountPln <= 0.009 && $amountRaw > 0.009) {
                $rowRate = (float) ($p->actual_rate ?? $p->planned_rate ?? $planRate);
                if ($rowRate <= 0) {
                    $rowRate = $planRate > 0 ? $planRate : 1.0;
                }
                if (! $isForeign) {
                    $amountPln = round($amountRaw, 2);
                } elseif ($convertToPln) {
                    // Tylko przy świadomym przeliczeniu — inaczej zostaje 0 (waluta osobno).
                    $amountPln = round($amountRaw * $rowRate, 2);
                }
            }
            $currency = $p->relationLoaded('actualCurrency') ? $p->actualCurrency : ($p->actualCurrency ?: $planCurrency);
            if (! $currency && $amountRaw > 0.009 && blank($p->actual_amount)) {
                $currency = $p->relationLoaded('plannedCurrency') ? $p->plannedCurrency : ($p->plannedCurrency ?: $planCurrency);
            }
            $rate = (float) ($p->actual_rate ?? $p->planned_rate ?? $planRate);
            $symbol = CurrencyAmountDisplay::symbol($currency instanceof Currency ? $currency : $planCurrency);

            $amountLabel = ($symbol !== 'PLN' && $amountRaw > 0 && ($planIsForeign || $isForeign || abs($amountRaw - $amountPln) > 0.009))
                ? ($convertToPln
                    ? CurrencyAmountDisplay::formatIndicative($amountRaw, $currency instanceof Currency ? $currency : $planCurrency, $rate)
                    : CurrencyAmountDisplay::format($amountRaw, $currency instanceof Currency ? $currency : $planCurrency, false))
                : MoneyFormatter::format($amountPln > 0 ? $amountPln : $amountRaw, 'PLN');

            return [
                'id' => (int) $p->id,
                'name' => (string) ($p->name ?? ''),
                'amount' => $amountRaw,
                'amount_pln' => $amountPln,
                'rate' => $rate,
                'currency_id' => $currencyId ? (int) $currencyId : null,
                'currency_symbol' => $symbol,
                'convert_to_pln' => $convertToPln,
                'amount_label' => $amountLabel,
                'method' => $p->payment_method,
                'method_label' => EventSettlementCost::$paymentMethods[$p->payment_method] ?? ($p->payment_method ?: '—'),
                'paid_by' => $p->paid_by ?? 'office',
                'paid_by_label' => EventSettlementCost::$paidByOptions[$p->paid_by ?? 'office'] ?? 'Biuro',
                'paid_at' => $p->paid_at?->format('Y-m-d'),
                'due_date' => $p->advance_due_date?->format('Y-m-d'),
                'advance_type' => $p->advance_type,
                'advance_type_label' => EventSettlementCost::advanceTypeLabel($p->advance_type),
                'is_advance' => EventSettlementCost::isAdvancePaymentType($p->advance_type)
                    || in_array($p->payment_status, ['advance_paid', 'advance_required'], true),
                'notes' => $p->notes,
                'document_number' => $p->document_number ?: $p->invoice_number,
                'status' => $p->payment_status,
                'status_label' => EventSettlementCost::$paymentStatuses[$p->payment_status] ?? ($p->payment_status ?: '—'),
                'reservation_id' => $p->reservation_id ? (int) $p->reservation_id : null,
                'reservation_label' => $p->reservation
                    ? (string) ($p->reservation->booking_reference ?: ('#'.$p->reservation->id))
                    : null,
            ];
        })->values();

        $advance = $mapped->filter(fn (array $p): bool => (bool) ($p['is_advance'] ?? false));
        $advanceSum = round((float) $advance->sum('amount_pln'), 2);
        $paidSum = round((float) $mapped->sum('amount_pln'), 2);
        $count = $mapped->count();

        $paidPlnPart = 0.0;
        /** @var array<string, float> $paidForeignBuckets */
        $paidForeignBuckets = [];
        /** @var array<string, array{amount: float, pln: float}> $convertedForeign */
        $convertedForeign = [];
        foreach ($mapped as $paymentRow) {
            $symbol = (string) ($paymentRow['currency_symbol'] ?? 'PLN');
            $amountRaw = (float) ($paymentRow['amount'] ?? 0);
            $amountPln = (float) ($paymentRow['amount_pln'] ?? 0);
            $convert = (bool) ($paymentRow['convert_to_pln'] ?? false);

            if ($symbol === 'PLN') {
                $paidPlnPart += $amountPln > 0 ? $amountPln : $amountRaw;
            } elseif ($convert && $amountRaw > 0) {
                $convertedForeign[$symbol] ??= ['amount' => 0.0, 'pln' => 0.0];
                $convertedForeign[$symbol]['amount'] += $amountRaw;
                $convertedForeign[$symbol]['pln'] += $amountPln > 0 ? $amountPln : 0.0;
                $paidPlnPart += $amountPln > 0 ? $amountPln : 0.0;
            } elseif ($amountRaw > 0) {
                $paidForeignBuckets[$symbol] = ($paidForeignBuckets[$symbol] ?? 0) + $amountRaw;
            }
        }

        $paidParts = [];
        if ($paidPlnPart > 0.009 && $convertedForeign === []) {
            $paidParts[] = MoneyFormatter::format(round($paidPlnPart, 2), 'PLN');
        } elseif ($paidPlnPart > 0.009 && $convertedForeign !== []) {
            // PLN z wpłat w złotówkach (bez przeliczonych walut — te poniżej z ≈).
            $plnOnly = $paidPlnPart;
            foreach ($convertedForeign as $data) {
                $plnOnly -= (float) $data['pln'];
            }
            if ($plnOnly > 0.009) {
                $paidParts[] = MoneyFormatter::format(round($plnOnly, 2), 'PLN');
            }
        }
        foreach ($convertedForeign as $symbol => $data) {
            $avgRate = $data['amount'] > 0 ? ($data['pln'] / $data['amount']) : 1.0;
            $currency = Currency::query()->where('symbol', $symbol)->orWhere('code', $symbol)->first();
            $paidParts[] = CurrencyAmountDisplay::formatIndicative((float) $data['amount'], $currency, $avgRate);
        }
        foreach ($paidForeignBuckets as $symbol => $amount) {
            if ($amount > 0) {
                $paidParts[] = number_format($amount, 2, ',', ' ').' '.$symbol;
            }
        }
        $paidLabel = $paidParts !== [] ? implode(' + ', $paidParts) : MoneyFormatter::format(0, 'PLN');

        $hint = null;
        if ($count > 0) {
            $parts = $mapped
                ->map(function (array $payment): string {
                    $typeLabel = (string) ($payment['advance_type_label'] ?? 'Wpłata');
                    $payerLabel = (string) ($payment['paid_by_label'] ?? 'Biuro');
                    $amountLabel = (string) ($payment['amount_label'] ?? '');
                    $paidAt = (string) ($payment['paid_at'] ?? '');

                    return trim($typeLabel.' '.$payerLabel.' '.$amountLabel.($paidAt !== '' ? ' ('.$paidAt.')' : ''));
                })
                ->filter(fn (string $line): bool => $line !== '')
                ->values()
                ->all();

            $planPaidBy = (string) ($planCost->paid_by ?? 'office');
            $plannedPln = (float) ($planCost->planned_amount_pln ?? $planCost->planned_amount ?? 0);
            $officePaidPln = round((float) $mapped
                ->filter(fn (array $p): bool => ($p['paid_by'] ?? 'office') === 'office')
                ->sum('amount_pln'), 2);

            if ($planPaidBy === 'pilot' && $plannedPln > 0.009 && $officePaidPln > 0.009) {
                $pilotDue = max(0.0, round($plannedPln - $officePaidPln, 2));
                $parts[] = 'do pilota '.MoneyFormatter::format($pilotDue, 'PLN');
            }

            $hint = implode(' · ', $parts);
        }

        $officePaidPln = round((float) $mapped
            ->filter(fn (array $p): bool => ($p['paid_by'] ?? 'office') === 'office')
            ->sum('amount_pln'), 2);
        $pilotPaidPln = round((float) $mapped
            ->filter(fn (array $p): bool => ($p['paid_by'] ?? '') === 'pilot')
            ->sum('amount_pln'), 2);
        $plannedPln = (float) ($planCost->planned_amount_pln ?? $planCost->planned_amount ?? 0);
        $planPaidBy = (string) ($planCost->paid_by ?? 'office');
        $pilotDuePln = $planPaidBy === 'pilot'
            ? max(0.0, round($plannedPln - $officePaidPln, 2))
            : null;

        return [
            'payments_count' => $count,
            'advance_count' => $advance->count(),
            'advance_paid_pln' => $advanceSum,
            'paid_label' => $paidLabel,
            'paid_foreign' => $paidForeignBuckets,
            'hint' => $hint,
            'payments' => $mapped->all(),
            'office_paid_pln' => $officePaidPln,
            'pilot_paid_pln' => $pilotPaidPln,
            'pilot_due_pln' => $pilotDuePln,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isZeroValueRow(array $row): bool
    {
        $tol = SettlementPaymentHealthService::TOLERANCE;

        return (float) ($row['planned_pln'] ?? 0) <= $tol
            && (float) ($row['calculation_pln'] ?? 0) <= $tol
            && (float) ($row['paid_pln'] ?? 0) <= $tol;
    }

    private function mapUiStatus(
        string $coverage,
        float $planned,
        float $paid,
        EventSettlementCost $planCost,
        ?array $eval = null,
    ): string {
        // $planned = ekwiwalent do statusu (także orientacyjne PLN dla walut bez przeliczenia).
        if ($planned <= SettlementPaymentHealthService::TOLERANCE
            && (float) ($planCost->planned_amount ?? 0) <= SettlementPaymentHealthService::TOLERANCE) {
            return 'n/a';
        }
        if ($coverage === SettlementPaymentHealthService::STATUS_OVERPAYMENT_REVIEW) {
            return 'review';
        }
        if ($coverage === SettlementPaymentHealthService::STATUS_OVERDUE) {
            return 'overdue';
        }
        if ($coverage === SettlementPaymentHealthService::STATUS_OK
            || ((bool) ($eval['invoice_settled'] ?? false) && $paid > SettlementPaymentHealthService::TOLERANCE)
            || SettlementPaymentHealthService::isFullyPaid($paid, $planned)) {
            return 'paid';
        }
        if (SettlementPaymentHealthService::isPartiallyPaid($paid, $planned)) {
            if (in_array($planCost->payment_status, ['advance_required', 'advance_paid'], true)
                || $planCost->advance_type === 'advance') {
                return 'advance';
            }

            return 'partial';
        }
        if ($coverage === SettlementPaymentHealthService::STATUS_DUE
            || $coverage === SettlementPaymentHealthService::STATUS_SHORTFALL) {
            return 'due';
        }

        return $paid > SettlementPaymentHealthService::TOLERANCE ? 'partial' : 'due';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowMatchesSearch(array $row, string $searchKey): bool
    {
        $haystack = mb_strtolower(trim(implode(' ', array_filter([
            (string) ($row['name'] ?? ''),
            (string) ($row['contractor'] ?? ''),
            (string) ($row['contractor_search'] ?? ''),
            (string) ($row['contractor_nip'] ?? ''),
            (string) ($row['contractor_email'] ?? ''),
            (string) ($row['contractor_phone'] ?? ''),
            (string) ($row['source_label'] ?? ''),
            (string) ($row['contractor_source_label'] ?? ''),
        ]))));

        return $haystack !== '' && str_contains($haystack, $searchKey);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sortRows(array $rows, string $sortBy, string $sortDir): array
    {
        $sorted = collect($rows)->sortBy(function (array $row) use ($sortBy): mixed {
            return match ($sortBy) {
                self::SORT_CONTRACTOR => mb_strtolower((string) ($row['contractor'] ?? '')),
                self::SORT_PLANNED => (float) ($row['planned_pln'] ?? 0),
                self::SORT_PAID => (float) ($row['paid_pln'] ?? 0),
                self::SORT_STATUS => (string) ($row['ui_status_label'] ?? $row['ui_status'] ?? ''),
                self::SORT_DUE => $row['next_due_date'] instanceof Carbon
                    ? $row['next_due_date']->timestamp
                    : PHP_INT_MAX,
                default => mb_strtolower((string) ($row['name'] ?? '')),
            };
        }, SORT_NATURAL);

        if ($sortDir === 'desc') {
            $sorted = $sorted->reverse();
        }

        return $sorted->values()->all();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowMatchesFilter(array $row, string $filter): bool
    {
        return match ($filter) {
            self::FILTER_OFFICE => ($row['paid_by'] ?? '') === 'office',
            self::FILTER_PILOT => ($row['paid_by'] ?? '') === 'pilot',
            self::FILTER_OVERDUE => ($row['coverage_status'] ?? '') === SettlementPaymentHealthService::STATUS_OVERDUE
                || ($row['ui_status'] ?? '') === 'overdue'
                || ($row['ui_status'] ?? '') === 'review',
            self::FILTER_NO_DOCUMENT => ! (bool) ($row['has_uploaded_file'] ?? false)
                && (int) ($row['files_count'] ?? $row['documents_count'] ?? 0) === 0,
            default => true,
        };
    }

    private function supportsReservationForSourceType(?string $sourceType): bool
    {
        return in_array($sourceType, [
            'program_point',
            HotelStaySettlementSync::SOURCE_HOTEL,
            HotelStaySettlementSync::SOURCE_STAY,
            TransportContractorSettlementSync::SOURCE_CONTRACTOR,
            'transport',
        ], true);
    }
}
