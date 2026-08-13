<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Currency;
use App\Models\Event;
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
 * kalkulacja / plan / zapłacone + health + dokumenty.
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
        'paid' => 'Zapłacone',
        'partial' => 'Częściowo',
        'advance' => 'Zaliczka',
        'due' => 'Do zapłaty',
        'overdue' => 'Po terminie',
        'ok' => 'Zapłacone',
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
        // Klucz cache zawiera max(updated_at) kosztów + settlement — touch wystarczy,
        // by kolejny odczyt nie trafił w stary wpis.
        $settlement = EventSettlement::query()->where('event_id', $eventId)->orderByDesc('id')->first();
        $settlement?->touch();
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
        $pilotCashPaid = 0.0;
        $hiddenZeroCount = 0;
        /** @var array<string, float> $plannedForeignBuckets */
        $plannedForeignBuckets = [];
        $nonConvertedIndicativePln = 0.0;

        foreach ($this->health->listPlanCosts($allCosts) as $planCost) {
            /** @var array<string, mixed>|null $eval */
            $eval = $evaluations->get($planCost->id);
            $planned = (float) ($eval['planned_pln'] ?? $this->health->plannedPlnForCost($planCost));
            $statusPlanned = (float) ($eval['status_planned_pln'] ?? $this->health->indicativePlannedPlnForCost($planCost));
            $paid = (float) ($eval['paid_pln'] ?? 0);
            $point = ($planCost->source_type === 'program_point' && $planCost->source_id)
                ? $programPointsById->get((int) $planCost->source_id)
                : null;
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
                'remaining_label' => $this->remainingAmountLabel($planCost, $remainingPln, $planned, $paid),
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
                    ? ProgramPointPricingCalculator::unitPriceLabel($point->group_size)
                    : null,
                'notes' => $planCost->notes,
                'is_program_point' => $planCost->source_type === 'program_point',
                'is_insurance' => $planCost->source_type === 'insurance_day',
                'approval_status' => (string) ($planCost->approval_status ?? 'pending'),
                'is_approved' => ($planCost->approval_status ?? 'pending') === 'approved',
                'approval_status_label' => EventSettlementCost::$approvalStatuses[$planCost->approval_status ?? 'pending']
                    ?? (string) ($planCost->approval_status ?? 'pending'),
            ];

            $sumCalc += $calc;
            $sumPlan += $planned;
            $sumPaid += $paid;
            $this->accumulateNonConvertedForeign($planCost, $plannedForeignBuckets, $nonConvertedIndicativePln);

            foreach ($payments as $payment) {
                if (($payment->paid_by ?? '') === 'pilot' && ($payment->payment_method ?? '') === 'cash') {
                    $pilotCashPaid += (float) ($payment->actual_amount_pln ?? 0);
                }
            }

            if (! $this->rowMatchesFilter($row, $filter)) {
                continue;
            }

            if ($groupFilter === self::FILTER_UNGROUPED && $groupId !== null) {
                continue;
            }
            if (is_numeric($groupFilter) && (int) $groupFilter > 0 && $groupId !== (int) $groupFilter) {
                continue;
            }

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

        // Suma z wierszy; lifecycle (ciężki kalkulator) tylko gdy brak lokalnych kwot.
        $headerCalc = $sumCalc > 0.009
            ? $sumCalc
            : (float) ($settlement->planned_cost_pln ?? 0);
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
                'remaining_pln' => round(max(0, $sumPlan - $sumPaid), 2),
                'client_due_pln' => $clientDue,
                'client_paid_pln' => $clientPaid,
                'calculation_label' => MoneyFormatter::format($headerCalc, 'PLN'),
                'planned_label' => CurrencyAmountDisplay::formatMixedTotal(round($sumPlan, 2), $plannedForeignBuckets, 2),
                'paid_label' => MoneyFormatter::format($sumPaid, 'PLN'),
                'remaining_label' => CurrencyAmountDisplay::formatMixedTotal(
                    round(max(0, $sumPlan - $sumPaid), 2),
                    $plannedForeignBuckets,
                    2,
                ),
                'client_due_label' => MoneyFormatter::format($clientDue, 'PLN'),
                'client_paid_label' => MoneyFormatter::format($clientPaid, 'PLN'),
                'planned_foreign' => $plannedForeignBuckets,
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
                'remaining_pln' => 0.0,
                'client_due_pln' => 0.0,
                'client_paid_pln' => 0.0,
                'calculation_label' => $zeroLabel,
                'planned_label' => $zeroLabel,
                'paid_label' => $zeroLabel,
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
        foreach ($groupRows as $row) {
            if (! empty($row['planned_convert_to_pln'])) {
                continue;
            }
            $amount = (float) ($row['planned_amount'] ?? 0);
            $symbol = (string) ($row['planned_currency_symbol'] ?? 'PLN');
            if ($amount <= 0 || $symbol === 'PLN') {
                continue;
            }
            $foreign[$symbol] = ($foreign[$symbol] ?? 0) + $amount;
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
            'paid_label' => MoneyFormatter::format($gPaid, 'PLN'),
            'remaining_label' => CurrencyAmountDisplay::formatMixedTotal($gRemaining, $foreign, 2),
        ];
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
                $label = CurrencyAmountDisplay::formatIndicative(
                    (float) $breakdown['total'],
                    $point->currency,
                    (float) ($point->currency?->exchange_rate ?? 1),
                );

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

        if ($amount > 0 && $symbol !== 'PLN') {
            return CurrencyAmountDisplay::formatIndicative($amount, $currency, $rate);
        }

        if ($amount > 0 && $symbol === 'PLN') {
            return CurrencyAmountDisplay::formatIndicative($amount, $currency, $rate);
        }

        if ($plannedPlnFallback > 0) {
            return MoneyFormatter::format($plannedPlnFallback, 'PLN');
        }

        return '—';
    }

    private function remainingAmountLabel(
        EventSettlementCost $planCost,
        float $remainingPln,
        float $plannedPln,
        float $paidPln,
    ): string {
        $amount = (float) ($planCost->planned_amount ?? 0);
        $currency = $planCost->plannedCurrency;
        $symbol = CurrencyAmountDisplay::symbol($currency);
        $rate = (float) ($planCost->planned_rate ?? ($currency?->exchange_rate ?? 1));
        if ($rate <= 0) {
            $rate = 1.0;
        }

        // Obca waluta planu: zawsze pokaż resztę w walucie źródłowej (nie samą kwotę PLN).
        if ($amount > 0 && $symbol !== 'PLN') {
            $paidForeign = $rate > 0 ? round($paidPln / $rate, 2) : 0.0;
            $remainingForeign = max(0, round($amount - $paidForeign, 2));

            return CurrencyAmountDisplay::formatIndicative($remainingForeign, $currency, $rate);
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

        if (in_array($planCost->source_type, ['transport', 'accommodation'], true)) {
            try {
                return round((float) app(SettlementAggregateFinanceService::class)
                    ->resolveReferenceTotalPln($event, (string) $planCost->source_type), 2);
            } catch (\Throwable) {
                // fallback poniżej
            }
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
     *   status_label: string
     * }
     */
    private function documentMetaForPlanCost(
        EventSettlementCost $planCost,
        EventSettlement $settlement,
        Collection $payments,
        array $documents,
    ): array {
        $fileNames = collect($documents)
            ->flatMap(fn (array $doc): array => collect($doc['files'] ?? [])
                ->pluck('name')
                ->filter()
                ->all())
            ->values();

        $filesCount = $fileNames->count();
        $numbers = $payments
            ->map(fn (EventSettlementCost $p): ?string => $p->document_number ?: $p->invoice_number)
            ->filter(fn (?string $n): bool => filled($n))
            ->unique()
            ->values();

        if ($filesCount > 0) {
            $first = (string) $fileNames->first();
            $type = (string) (($documents[0]['type_label'] ?? null) ?: 'Plik');
            $number = (string) (($documents[0]['number'] ?? null) ?: ($numbers->first() ?? ''));
            $hint = $number !== ''
                ? trim($type.' '.$number)
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
            ];
        }

        if ($numbers->isNotEmpty()) {
            $joined = $numbers->take(2)->implode(', ');

            return [
                'files_count' => 0,
                'has_uploaded_file' => false,
                'hint' => 'Nr '.$joined.' (bez pliku)',
                'status_label' => 'Brak wgranego pliku — jest numer: '.$joined,
            ];
        }

        return [
            'files_count' => 0,
            'has_uploaded_file' => false,
            'hint' => 'Brak pliku',
            'status_label' => 'Brak wgranego pliku faktury / dowodu',
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
        $planCurrency = $planCost->plannedCurrency;
        $planRate = (float) ($planCost->planned_rate ?? ($planCurrency?->exchange_rate ?? 1));

        $planSymbol = CurrencyAmountDisplay::symbol($planCurrency);
        $planIsForeign = $planSymbol !== 'PLN';

        $mapped = $payments->map(function (EventSettlementCost $p) use ($planCurrency, $planRate, $planIsForeign): array {
            $amountPln = (float) ($p->actual_amount_pln ?? 0);
            $amountRaw = (float) ($p->actual_amount ?? $amountPln);
            $currency = $p->relationLoaded('actualCurrency') ? $p->actualCurrency : ($p->actualCurrency ?: $planCurrency);
            $rate = (float) ($p->actual_rate ?? $planRate);
            $symbol = CurrencyAmountDisplay::symbol($currency instanceof Currency ? $currency : $planCurrency);

            $amountLabel = ($symbol !== 'PLN' && $amountRaw > 0 && ($planIsForeign || abs($amountRaw - $amountPln) > 0.009))
                ? CurrencyAmountDisplay::formatIndicative($amountRaw, $currency instanceof Currency ? $currency : $planCurrency, $rate)
                : MoneyFormatter::format($amountPln, 'PLN');

            return [
                'id' => (int) $p->id,
                'name' => (string) ($p->name ?? ''),
                'amount' => $amountRaw,
                'amount_pln' => $amountPln,
                'rate' => $rate,
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
            ];
        })->values();

        $advance = $mapped->filter(fn (array $p): bool => (bool) ($p['is_advance'] ?? false));
        $advanceSum = round((float) $advance->sum('amount_pln'), 2);
        $paidSum = round((float) $mapped->sum('amount_pln'), 2);
        $paidForeignSum = round((float) $mapped->sum('amount'), 2);
        $count = $mapped->count();

        $paidLabel = MoneyFormatter::format($paidSum, 'PLN');
        if ($planIsForeign && $paidForeignSum > 0) {
            $avgRate = $paidForeignSum > 0 ? ($paidSum / $paidForeignSum) : $planRate;
            $paidLabel = CurrencyAmountDisplay::formatIndicative($paidForeignSum, $planCurrency, $avgRate);
        }

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
}
