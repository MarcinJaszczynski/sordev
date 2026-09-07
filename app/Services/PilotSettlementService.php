<?php

namespace App\Services;

use App\Actions\Finance\DeleteSettlementCostPaymentAction;
use App\Actions\Finance\RecordSettlementCostPaymentAction;
use App\Actions\Finance\UpdateSettlementCostPaymentAction;
use App\Data\RecordSettlementCostPaymentData;
use App\Data\UpdateSettlementCostPaymentData;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventBusCollection;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\EventSettlementDocument;
use App\Models\PilotCashPreparation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PilotSettlementService
{
    public function assertPilotCanAccessEvent(Event $event, ?User $user = null): void
    {
        $user ??= Auth::user();

        if (! $user || ! $user->can('view', $event)) {
            abort(403);
        }
    }

    public function getOrCreateSettlement(Event $event): EventSettlement
    {
        $this->assertPilotCanAccessEvent($event);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        if (! $settlement->pilot_id && $event->assigned_to) {
            $settlement->update(['pilot_id' => $event->assigned_to]);
        }

        if ($settlement->status === 'draft') {
            $settlement->update(['status' => 'active']);
        }

        if ($settlement->costs()->count() === 0) {
            $this->syncTripExpenses($event, $settlement);
        }

        return $settlement->fresh();
    }

    public function syncTripExpenses(Event $event, ?EventSettlement $settlement = null, bool $force = false): EventSettlement
    {
        $settlement ??= $this->getOrCreateSettlement($event);

        if (! $settlement->isEditableByPilot() && $force) {
            throw ValidationException::withMessages([
                'settlement' => 'Rozliczenie jest zamknięte — nie można odświeżyć listy wydatków.',
            ]);
        }

        if ($force || $settlement->costs()->count() === 0) {
            $settlement->importFromEvent();
            $settlement->recalculateTotals();
            $settlement->recalculatePilotCash();
            $this->syncPilotCashSpentFromCosts($settlement);
        }

        return $settlement->fresh();
    }

    /**
     * @return Collection<int, EventSettlementCost>
     */
    public function getExpenseLines(EventSettlement $settlement): Collection
    {
        $allCosts = $settlement->costs()->get();
        $health = app(SettlementPaymentHealthService::class);

        // Tylko pozycje planu / ręczne wydatki — bez wierszy *_payment (te są składowymi wpłat).
        return $settlement->costs()
            ->with(['plannedCurrency', 'actualCurrency', 'contractor'])
            ->where('paid_by', 'pilot')
            ->where('payment_status', '!=', 'cancelled')
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->filter(fn (EventSettlementCost $cost): bool => ! EventSettlementCost::isPaymentSourceType($cost->source_type))
            ->map(function (EventSettlementCost $cost) use ($allCosts, $health): EventSettlementCost {
                $paymentRows = $health->paymentRowsForPlanCost($cost, $allCosts)
                    ->filter(fn (EventSettlementCost $row): bool => SettlementPaymentHealthService::isBookedPaymentStatus($row->payment_status));

                $officeRows = $paymentRows->filter(fn (EventSettlementCost $row): bool => ($row->paid_by ?? '') === 'office');
                $pilotCashRows = $paymentRows->filter(function (EventSettlementCost $row) use ($cost): bool {
                    if (EventSettlementCost::isPaymentSourceType($row->source_type)) {
                        return ($row->paid_by ?? '') === 'pilot'
                            && $this->isPilotCashPaymentMethod($row->payment_method);
                    }

                    return (int) $row->id === (int) $cost->id
                        && ($row->paid_by ?? $cost->paid_by) === 'pilot'
                        && $this->isPilotCashPaymentMethod($row->payment_method ?: $cost->payment_method);
                });

                $planned = round((float) ($cost->planned_amount ?? 0), 2);
                $officePaid = round((float) $officeRows->sum(fn (EventSettlementCost $row): float => (float) ($row->actual_amount ?? 0)), 2);
                $pilotPaid = round((float) $pilotCashRows->sum(fn (EventSettlementCost $row): float => (float) ($row->actual_amount ?? 0)), 2);

                // Legacy: actual na wierszu planu jako gotówka pilota.
                if ($pilotPaid <= 0.009
                    && (float) ($cost->actual_amount ?? 0) > 0.009
                    && SettlementPaymentHealthService::isBookedPaymentStatus($cost->payment_status)
                    && $this->isPilotCashPaymentMethod($cost->payment_method)) {
                    $pilotPaid = round((float) $cost->actual_amount, 2);
                }

                $pilotDue = max(0.0, round($planned - $officePaid, 2)); // dopłata po zaliczce biura
                $remainingCash = max(0.0, round($pilotDue - $pilotPaid, 2));

                $cost->setAttribute('ledger_paid_amount', $pilotPaid > 0.009 ? $pilotPaid : null);
                $cost->setAttribute('ledger_office_paid', $officePaid);
                $cost->setAttribute('ledger_pilot_due', $pilotDue);
                $cost->setAttribute('ledger_remaining_cash', $remainingCash);
                $cost->setAttribute('ledger_is_top_up', $officePaid > 0.009 && $pilotDue > 0.009);
                $cost->setAttribute('ledger_status', $cost->payment_status);

                return $cost;
            })
            ->values();
    }

    /**
     * Sumy ledgera wydatków pilota (per waluta) — bez zaliczek biura w „wydane”.
     *
     * @param  Collection<int, EventSettlementCost>  $lines
     * @return list<array{currency: string, planned: float, office_paid: float, pilot_paid: float, pilot_due: float}>
     */
    public function summarizeExpenseLines(Collection $lines): array
    {
        $buckets = [];

        foreach ($lines as $cost) {
            $currency = $cost->actualCurrency ?? $cost->plannedCurrency;
            $symbol = (string) ($currency?->symbol ?? $currency?->code ?? 'PLN');
            $key = $symbol !== '' ? $symbol : 'PLN';

            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'currency' => $key,
                    'planned' => 0.0,
                    'office_paid' => 0.0,
                    'pilot_paid' => 0.0,
                    'pilot_due' => 0.0,
                ];
            }

            $buckets[$key]['planned'] += round((float) ($cost->planned_amount ?? 0), 2);
            $buckets[$key]['office_paid'] += round((float) ($cost->ledger_office_paid ?? 0), 2);
            $buckets[$key]['pilot_paid'] += round((float) ($cost->ledger_paid_amount ?? 0), 2);
            $buckets[$key]['pilot_due'] += round((float) ($cost->ledger_pilot_due ?? 0), 2);
        }

        return collect($buckets)
            ->map(fn (array $row): array => [
                'currency' => $row['currency'],
                'planned' => round($row['planned'], 2),
                'office_paid' => round($row['office_paid'], 2),
                'pilot_paid' => round($row['pilot_paid'], 2),
                'pilot_due' => round($row['pilot_due'], 2),
            ])
            ->sortBy('currency')
            ->values()
            ->all();
    }

    /**
     * Odświeża „potrzebę gotówki” (calculated) i „wydane” (spent) po mutacji kosztów/wpłat.
     */
    public function refreshCashFromCosts(EventSettlement $settlement): void
    {
        $settlement = $settlement->fresh() ?? $settlement;
        $settlement->recalculatePilotCash();
        $this->syncPilotCashSpentFromCosts($settlement);
    }

    /**
     * @return Collection<int, object{
     *     cash: PilotCashPreparation,
     *     currency_id: int,
     *     currency_name: string,
     *     office_provided: float,
     *     planned_expenses: float,
     *     actual_spent: float,
     *     returned: float,
     *     to_return: float,
     *     to_pay_pilot: float
     * }>
     */
    public function getCashReconciliation(EventSettlement $settlement): Collection
    {
        $this->repairCurrencyExchangeAmounts($settlement);
        $settlement->recalculatePilotCash();
        $this->syncPilotCashSpentFromCosts($settlement);

        return $settlement->pilotCashPreparations()
            ->with('currency')
            ->orderBy('currency_id')
            ->get()
            ->map(function (PilotCashPreparation $cash) use ($settlement) {
                $currencyId = (int) $cash->currency_id;
                $fromOffice = (float) ($cash->provided_amount ?? $cash->approved_amount ?? 0);
                $fromBus = $this->sumBusCollectionsHeldForCurrency($settlement, $currencyId);
                $busPlanned = $this->sumBusCollectionsPlannedForCurrency($settlement, $currencyId);
                $exchangeIn = (float) $settlement->currencyExchanges()->where('to_currency_id', $currencyId)->sum('to_amount');
                $exchangeOut = (float) $settlement->currencyExchanges()->where('from_currency_id', $currencyId)->sum('from_amount');
                $available = round($fromOffice + $fromBus + $exchangeIn - $exchangeOut, 2);
                $plannedExpenses = $this->sumPilotCostsForCurrency($settlement, $currencyId, plannedOnly: true);
                $officeAdvancesOnCosts = $this->sumOfficeAdvancesOnPilotCosts($settlement, $currencyId);
                $actualSpent = $this->sumPilotCostsForCurrency($settlement, $currencyId);
                $returned = (float) ($cash->returned_amount ?? 0);
                $remaining = round($available - $actualSpent - $returned, 2);
                $needed = round((float) ($cash->calculated_amount ?? 0), 2);
                $origin = $this->resolveCashOrigin($fromOffice, $fromBus, $exchangeIn, $exchangeOut);

                return (object) [
                    'cash' => $cash,
                    'currency_id' => $currencyId,
                    'currency_code' => $cash->currency?->code ?: $cash->currency?->symbol ?: '—',
                    'currency_name' => $cash->currency?->name ?? 'Waluta #'.$currencyId,
                    'from_office' => round($fromOffice, 2),
                    'from_bus' => $fromBus,
                    'bus_planned' => $busPlanned,
                    'needed' => $needed,
                    'calculated' => $needed,
                    'plan_total' => $plannedExpenses,
                    'office_advances_on_costs' => $officeAdvancesOnCosts,
                    'is_top_up' => $officeAdvancesOnCosts > 0.009 && $needed > 0.009,
                    'exchange_in' => round($exchangeIn, 2),
                    'exchange_out' => round($exchangeOut, 2),
                    'office_held' => $origin['office_held'],
                    'bus_held' => $origin['bus_held'],
                    'exchange_held' => $origin['exchange_held'],
                    'origin' => $origin['origin'],
                    'origin_label' => $origin['origin_label'],
                    'office_provided' => $available, // dostępne po wymianie (kompatybilność)
                    'available' => $available,
                    'planned_expenses' => $plannedExpenses,
                    'actual_spent' => $actualSpent,
                    'returned' => $returned,
                    'to_return' => max(0, $remaining),
                    'to_pay_pilot' => max(0, -$remaining),
                ];
            });
    }

    /**
     * Narracja zasobów + mini-ledger (wypłaty / wymiany) — warstwa prezentacji nad saldami.
     *
     * @return object{
     *     holdings: \Illuminate\Support\Collection<int, object>,
     *     summary_label: string,
     *     chain_lines: list<string>,
     *     ledger: \Illuminate\Support\Collection<int, object>,
     *     has_movements: bool,
     *     has_exchanges: bool,
     *     exchange_label: string
     * }
     */
    public function getCashResourceStory(EventSettlement $settlement, ?Collection $rows = null): object
    {
        $rows ??= $this->getCashReconciliation($settlement);

        $holdings = $rows
            ->filter(fn ($row) => abs((float) $row->available) > 0.009
                || (float) $row->from_office > 0.009
                || (float) ($row->from_bus ?? 0) > 0.009
                || (float) $row->exchange_in > 0.009
                || (float) $row->exchange_out > 0.009)
            ->values();

        $summaryParts = [];
        foreach ($holdings as $row) {
            if (abs((float) $row->available) <= 0.009) {
                continue;
            }
            $part = $this->formatCashAmount((float) $row->available).' '.$row->currency_code;
            if ($row->origin_label !== '') {
                $part .= ' ('.$row->origin_label.')';
            }
            $summaryParts[] = $part;
        }

        $exchanges = $settlement->currencyExchanges()
            ->with(['fromCurrency', 'toCurrency'])
            ->orderBy('exchanged_at')
            ->orderBy('id')
            ->get();

        $payouts = $settlement->pilotCashPreparations()
            ->with('currency')
            ->whereNotNull('provided_amount')
            ->where('provided_amount', '>', 0)
            ->orderBy('provided_at')
            ->orderBy('currency_id')
            ->get();

        $chainLines = [];
        foreach ($payouts as $cash) {
            $code = $cash->currency?->code ?: $cash->currency?->symbol ?: '—';
            $chainLines[] = 'Wypłata '.$this->formatCashAmount((float) $cash->provided_amount).' '.$code;
        }
        foreach ($exchanges as $ex) {
            $fromCode = $ex->fromCurrency?->code ?: $ex->fromCurrency?->symbol ?: '—';
            $toCode = $ex->toCurrency?->code ?: $ex->toCurrency?->symbol ?: '—';
            $line = 'Wymiana '.$this->formatCashAmount((float) $ex->from_amount).' '.$fromCode
                .' → '.$this->formatCashAmount((float) $ex->to_amount).' '.$toCode;
            if ((float) ($ex->exchange_rate ?? 0) > 0) {
                $line .= ' @ '.$this->formatCashRate((float) $ex->exchange_rate);
            }
            $chainLines[] = $line;
        }

        $ledger = collect();
        foreach ($payouts as $cash) {
            $code = $cash->currency?->code ?: $cash->currency?->symbol ?: '—';
            $at = $cash->provided_at;
            $ledger->push((object) [
                'type' => 'payout',
                'id' => 'payout-'.$cash->currency_id,
                'exchange_id' => null,
                'currency_id' => (int) $cash->currency_id,
                'at' => $at,
                'sort_at' => $at?->timestamp ?? 0,
                'sort_id' => (int) $cash->currency_id,
                'title' => 'Wypłata z biura',
                'label' => $this->formatCashAmount((float) $cash->provided_amount).' '.$code,
                'detail' => $cash->notes ?: null,
            ]);
        }
        foreach ($exchanges as $ex) {
            $fromCode = $ex->fromCurrency?->code ?: $ex->fromCurrency?->symbol ?: '—';
            $toCode = $ex->toCurrency?->code ?: $ex->toCurrency?->symbol ?: '—';
            $at = $ex->exchanged_at;
            $detailParts = [];
            if ((float) ($ex->exchange_rate ?? 0) > 0) {
                $detailParts[] = 'kurs '.$this->formatCashRate((float) $ex->exchange_rate);
            }
            if (filled($ex->notes)) {
                $detailParts[] = (string) $ex->notes;
            }
            $ledger->push((object) [
                'type' => 'exchange',
                'id' => 'exchange-'.$ex->id,
                'exchange_id' => (int) $ex->id,
                'currency_id' => null,
                'at' => $at,
                'sort_at' => $at?->timestamp ?? 0,
                'sort_id' => (int) $ex->id,
                'title' => 'Wymiana walut',
                'label' => $this->formatCashAmount((float) $ex->from_amount).' '.$fromCode
                    .' → '.$this->formatCashAmount((float) $ex->to_amount).' '.$toCode,
                'detail' => $detailParts !== [] ? implode(' · ', $detailParts) : null,
            ]);
        }

        $ledger = $ledger
            ->sortBy([
                ['sort_at', 'asc'],
                ['sort_id', 'asc'],
            ])
            ->values();

        $exchangeLabelParts = [];
        foreach ($exchanges as $ex) {
            $fromCode = $ex->fromCurrency?->code ?: $ex->fromCurrency?->symbol ?: '—';
            $toCode = $ex->toCurrency?->code ?: $ex->toCurrency?->symbol ?: '—';
            $exchangeLabelParts[] = number_format((float) $ex->from_amount, 0, ',', ' ').' '.$fromCode
                .'→'.number_format((float) $ex->to_amount, 0, ',', ' ').' '.$toCode;
        }

        return (object) [
            'holdings' => $holdings,
            'summary_label' => $summaryParts !== [] ? implode(' · ', $summaryParts) : '—',
            'chain_lines' => $chainLines,
            'ledger' => $ledger,
            'has_movements' => $ledger->isNotEmpty(),
            'has_exchanges' => $exchanges->isNotEmpty(),
            'exchange_label' => $exchangeLabelParts !== [] ? implode(' / ', $exchangeLabelParts) : '—',
        ];
    }

    /**
     * Plan źródeł gotówki + instrukcja wypłaty (wymiana PLN ↔ natura).
     *
     * Źródła pokazują pełną zbiórkę (nie ucinają do potrzeby programu).
     * Zbiórka planowana obniża lukę biura. Nadwyżka zbiórki = informacja.
     * Kurs wymiany: orientacyjny z tabeli currencies.
     *
     * @return object{
     *     needed_label: string,
     *     sources_label: string,
     *     bus_label: string,
     *     bus_surplus_label: ?string,
     *     has_bus: bool,
     *     bus_covers_need: bool,
     *     has_office_gap: bool,
     *     payout_exchange_label: string,
     *     payout_natura_label: string,
     *     payout_exchange_detail: ?string,
     *     cash_to_pay_label: string,
     *     cash_to_pay_danger: bool,
     *     lines: \Illuminate\Support\Collection<int, object>
     * }
     */
    public function getCashFundingPlan(EventSettlement $settlement, ?Collection $rows = null): object
    {
        $rows ??= $this->getCashReconciliation($settlement);
        $plnIds = Currency::plnIds();

        $neededParts = [];
        $sourceParts = [];
        $busParts = [];
        $surplusBits = [];
        $surplusNeedBits = [];
        $lines = collect();

        $plnOfficeShare = 0.0;
        $plnGap = 0.0;
        $foreignOfficeShares = []; // list of [code, amount, rate, currency_id]
        $foreignGaps = [];

        foreach ($rows as $row) {
            $needed = round((float) ($row->needed ?? 0), 2);
            $fromOffice = round((float) ($row->from_office ?? 0), 2);
            $fromBus = round((float) ($row->from_bus ?? 0), 2);
            $busPlanned = round((float) ($row->bus_planned ?? 0), 2);
            $exchangeIn = round((float) ($row->exchange_in ?? 0), 2);
            $busCover = round($fromBus + $busPlanned, 2);
            $code = (string) ($row->currency_code ?? '—');
            $currencyId = (int) ($row->currency_id ?? 0);
            $isPln = in_array($currencyId, $plnIds, true);

            if ($needed <= 0.009 && $busCover <= 0.009 && $fromOffice <= 0.009) {
                continue;
            }

            $busShare = round(min(max($needed, 0), $busCover), 2);
            $officeShare = round(max(0, $needed - $busShare), 2);
            $surplus = round(max(0, $busCover - max($needed, 0)), 2);
            // Luka nadal do pokrycia z biura (po wypłacie / zbiórce / wymianie).
            $gap = round(max(0, $needed - $busCover - $fromOffice - $exchangeIn), 2);

            if ($needed > 0.009) {
                $neededParts[] = $this->formatCashAmount($needed).' '.$code;
            }
            if ($officeShare > 0.009) {
                $sourceParts[] = $this->formatCashAmount($officeShare).' '.$code.' z biura';
            }
            if ($busCover > 0.009) {
                $statusHint = $fromBus > 0.009 && $busPlanned <= 0.009
                    ? 'zebrane'
                    : ($fromBus > 0.009 ? 'część zebrana' : 'plan');
                $sourceParts[] = $this->formatCashAmount($busCover).' '.$code.' z autokaru ('.$statusHint.')';
                $busParts[] = $this->formatCashAmount($busCover).' '.$code.' ('.$statusHint.')';
            }
            if ($surplus > 0.009) {
                $surplusBits[] = $this->formatCashAmount($surplus).' '.$code;
                $surplusNeedBits[] = $this->formatCashAmount($needed).' '.$code;
            }

            $rate = $isPln ? 1.0 : $this->resolveCurrencyRate($currencyId);

            $lines->push((object) [
                'currency_id' => $currencyId,
                'currency_code' => $code,
                'is_pln' => $isPln,
                'needed' => $needed,
                'bus_cover' => $busCover,
                'bus_share' => $busShare,
                'office_share' => $officeShare,
                'gap' => $gap,
                'surplus' => $surplus,
                'rate' => $rate,
            ]);

            if ($isPln) {
                $plnOfficeShare = $officeShare;
                $plnGap = $gap;
            } else {
                if ($officeShare > 0.009) {
                    $foreignOfficeShares[] = [
                        'currency_id' => $currencyId,
                        'code' => $code,
                        'amount' => $officeShare,
                        'rate' => $rate,
                        'pln_cost' => round($officeShare * $rate, 2),
                    ];
                }
                if ($gap > 0.009) {
                    $foreignGaps[] = [
                        'currency_id' => $currencyId,
                        'code' => $code,
                        'amount' => $gap,
                        'rate' => $rate,
                        'pln_cost' => round($gap * $rate, 2),
                    ];
                }
            }
        }

        $exchangeGross = $this->buildExchangePayoutLabel($plnOfficeShare, $foreignOfficeShares);
        $naturaGross = $this->buildNaturaPayoutLabel($plnOfficeShare, $foreignOfficeShares);
        $exchangeRemaining = $this->buildExchangePayoutLabel($plnGap, $foreignGaps);
        $naturaRemaining = $this->buildNaturaPayoutLabel($plnGap, $foreignGaps);

        $hasOfficeGap = $plnGap > 0.009 || $foreignGaps !== [];
        $hasBus = $busParts !== [];
        $neededLabel = $neededParts !== [] ? implode(' · ', $neededParts) : '—';
        $busCoversNeed = $hasBus && ! $hasOfficeGap && $neededParts !== [];
        $cashToPayLabel = $exchangeRemaining['label'];
        if ($cashToPayLabel === '—' && $naturaRemaining['label'] !== '—') {
            $cashToPayLabel = $naturaRemaining['label'];
        }

        $surplusLabel = null;
        if ($surplusBits !== []) {
            $surplusLabel = 'Zbiórka jest o '.implode(', ', $surplusBits)
                .' wyższa niż potrzeba z programu ('.implode(', ', $surplusNeedBits).').'
                .' Nadwyżka wraca w rozliczeniu — to nie wypłata z biura.';
        }

        return (object) [
            'needed_label' => $neededLabel,
            'sources_label' => $sourceParts !== [] ? implode(' · ', $sourceParts) : '—',
            'bus_label' => $busParts !== [] ? implode(' · ', $busParts) : '—',
            'bus_surplus_label' => $surplusLabel,
            'has_bus' => $hasBus,
            'bus_covers_need' => $busCoversNeed,
            'has_office_gap' => $hasOfficeGap,
            'payout_exchange_label' => $exchangeRemaining['label'],
            'payout_natura_label' => $naturaRemaining['label'],
            'payout_exchange_detail' => $exchangeRemaining['detail'],
            'payout_exchange_gross_label' => $exchangeGross['label'],
            'payout_natura_gross_label' => $naturaGross['label'],
            'cash_to_pay_label' => $cashToPayLabel,
            'cash_to_pay_danger' => $hasOfficeGap,
            'lines' => $lines,
        ];
    }

    /**
     * @param  list<array{currency_id: int, code: string, amount: float, rate: float, pln_cost: float}>  $foreignParts
     * @return array{label: string, detail: ?string}
     */
    protected function buildExchangePayoutLabel(float $plnForSpend, array $foreignParts): array
    {
        $exchangePln = 0.0;
        $detailBits = [];
        foreach ($foreignParts as $part) {
            $exchangePln += $part['pln_cost'];
            $detailBits[] = $this->formatCashAmount($part['pln_cost']).' PLN na zakup '
                .$this->formatCashAmount($part['amount']).' '.$part['code']
                .' (kurs ~'.$this->formatCashRate($part['rate']).')';
        }
        $exchangePln = round($exchangePln, 2);
        $totalPln = round($plnForSpend + $exchangePln, 2);

        if ($totalPln <= 0.009) {
            return ['label' => '—', 'detail' => null];
        }

        $label = $this->formatCashAmount($totalPln).' PLN';
        if ($exchangePln > 0.009 && $plnForSpend > 0.009) {
            $label .= ' (w tym '.$this->formatCashAmount($exchangePln).' na zakup)';
        } elseif ($exchangePln > 0.009 && $plnForSpend <= 0.009) {
            $label .= ' na zakup waluty';
        }

        return [
            'label' => $label,
            'detail' => $detailBits !== [] ? implode('; ', $detailBits) : null,
        ];
    }

    /**
     * @param  list<array{currency_id: int, code: string, amount: float, rate: float, pln_cost: float}>  $foreignParts
     * @return array{label: string, detail: ?string}
     */
    protected function buildNaturaPayoutLabel(float $plnForSpend, array $foreignParts): array
    {
        $parts = [];
        if ($plnForSpend > 0.009) {
            $parts[] = $this->formatCashAmount($plnForSpend).' PLN';
        }
        foreach ($foreignParts as $part) {
            if ($part['amount'] > 0.009) {
                $parts[] = $this->formatCashAmount($part['amount']).' '.$part['code'];
            }
        }

        return [
            'label' => $parts !== [] ? implode(' + ', $parts) : '—',
            'detail' => null,
        ];
    }

    /**
     * Suma zbiórek nadal u pilota (status collected) — zasila saldo gotówki.
     */
    public function sumBusCollectionsHeldForCurrency(EventSettlement $settlement, int $currencyId): float
    {
        if (! Schema::hasTable('event_bus_collections')) {
            return 0.0;
        }

        $query = EventBusCollection::query()
            ->where('currency_id', $currencyId)
            ->whereIn('status', EventBusCollection::HELD_STATUSES);

        if ($settlement->id) {
            $query->where(function ($q) use ($settlement): void {
                $q->where('settlement_id', $settlement->id)
                    ->orWhere('event_id', $settlement->event_id);
            });
        } else {
            $query->where('event_id', $settlement->event_id);
        }

        return round((float) $query->sum('amount'), 2);
    }

    /**
     * Suma planów zbiórek (jeszcze nie zebranych) — informacyjnie, bez wpływu na saldo.
     */
    public function sumBusCollectionsPlannedForCurrency(EventSettlement $settlement, int $currencyId): float
    {
        if (! Schema::hasTable('event_bus_collections')) {
            return 0.0;
        }

        $query = EventBusCollection::query()
            ->where('currency_id', $currencyId)
            ->where('status', EventBusCollection::STATUS_PLANNED);

        if ($settlement->id) {
            $query->where(function ($q) use ($settlement): void {
                $q->where('settlement_id', $settlement->id)
                    ->orWhere('event_id', $settlement->event_id);
            });
        } else {
            $query->where('event_id', $settlement->event_id);
        }

        return round((float) $query->sum('amount'), 2);
    }

    /**
     * @return array{
     *     origin: string,
     *     origin_label: string,
     *     office_held: float,
     *     bus_held: float,
     *     exchange_held: float
     * }
     */
    protected function resolveCashOrigin(
        float $fromOffice,
        float $fromBus,
        float $exchangeIn,
        float $exchangeOut,
    ): array {
        $officeHeld = round(max(0, $fromOffice - $exchangeOut), 2);
        $busHeld = round(max(0, $fromBus), 2);
        $exchangeHeld = round(max(0, $exchangeIn), 2);
        $hasOffice = $officeHeld > 0.009 || ($fromOffice > 0.009 && $exchangeOut <= 0.009);
        $hasBus = $busHeld > 0.009;
        $hasExchange = $exchangeHeld > 0.009;

        $sources = array_filter([
            $hasOffice ? 'biuro' : null,
            $hasBus ? 'autokar' : null,
            $hasExchange ? 'wymiana' : null,
        ]);

        if (count($sources) > 1) {
            $origin = 'mixed';
            $label = 'mieszane';
        } elseif ($hasBus) {
            $origin = 'bus';
            $label = 'z autokaru';
        } elseif ($hasExchange) {
            $origin = 'exchange';
            $label = 'z wymiany';
        } elseif ($hasOffice) {
            $origin = 'office';
            $label = 'z biura';
        } else {
            $origin = 'none';
            $label = '';
        }

        return [
            'origin' => $origin,
            'origin_label' => $label,
            'office_held' => $officeHeld,
            'bus_held' => $busHeld,
            'exchange_held' => $exchangeHeld,
        ];
    }

    protected function formatCashAmount(float $amount): string
    {
        return number_format($amount, 2, ',', ' ');
    }

    protected function formatCashRate(float $rate): string
    {
        return rtrim(rtrim(number_format($rate, 5, ',', ' '), '0'), ',');
    }

    /**
     * @param  array{
     *     pilot_report_notes?: string|null,
     *     reported_participant_count?: int|null,
     *     odometer_start?: int|null,
     *     odometer_end?: int|null,
     *     submit_to_office?: bool
     * }  $data
     */
    public function saveReport(Event $event, array $data): EventSettlement
    {
        $settlement = $this->getOrCreateSettlement($event);

        if (! $settlement->isEditableByPilot()) {
            throw ValidationException::withMessages([
                'settlement' => 'Rozliczenie jest zamknięte i nie można go edytować.',
            ]);
        }

        $odometerStart = isset($data['odometer_start']) && $data['odometer_start'] !== ''
            ? (int) $data['odometer_start']
            : null;
        $odometerEnd = isset($data['odometer_end']) && $data['odometer_end'] !== ''
            ? (int) $data['odometer_end']
            : null;

        if ($odometerStart !== null && $odometerEnd !== null && $odometerEnd < $odometerStart) {
            throw ValidationException::withMessages([
                'odometer_end' => 'Stan licznika końcowy nie może być mniejszy od początkowego.',
            ]);
        }

        $participantCount = isset($data['reported_participant_count']) && $data['reported_participant_count'] !== ''
            ? (int) $data['reported_participant_count']
            : null;

        if ($participantCount !== null && $participantCount < 0) {
            throw ValidationException::withMessages([
                'reported_participant_count' => 'Liczba osób nie może być ujemna.',
            ]);
        }

        $payload = [
            'pilot_report_notes' => $data['pilot_report_notes'] ?? null,
            'reported_participant_count' => $participantCount,
            'odometer_start' => $odometerStart,
            'odometer_end' => $odometerEnd,
            'pilot_report_updated_at' => now(),
        ];

        if (! empty($data['submit_to_office']) && $settlement->status !== 'closed') {
            $payload['status'] = 'pilot_settled';
        }

        $settlement->update($payload);

        if (! empty($data['submit_to_office'])) {
            $this->notifyOfficeOfUpdate($settlement);
        }

        return $settlement->fresh();
    }

    /**
     * @param  array{
     *     name: string,
     *     actual_amount: float|int|string,
     *     actual_currency_id?: int|null,
     *     notes?: string|null,
     *     payment_method?: string|null,
     *     paid_by?: string|null,
     *     invoice_number?: string|null,
     *     document_number?: string|null
     * }  $data
     */
    public function addExpense(Event $event, array $data): EventSettlementCost
    {
        $settlement = $this->getOrCreateSettlement($event);
        $user = Auth::user();
        $canOfficeEdit = $user && (
            $user->can('manageFinance', $event)
            || $user->can('update', $event)
        );

        if (! $settlement->isEditableByPilot() && ! $canOfficeEdit) {
            throw ValidationException::withMessages([
                'settlement' => 'Rozliczenie jest zamknięte i nie można dodawać wydatków.',
            ]);
        }

        $currencyId = $data['actual_currency_id'] ?? $this->defaultPlnCurrencyId();
        $amount = (float) $data['actual_amount'];
        $rate = $this->resolveCurrencyRate($currencyId);

        $maxOrder = (int) $settlement->costs()->max('order');

        $cost = $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => $data['name'],
            'planned_amount' => $amount,
            'planned_currency_id' => $currencyId,
            'planned_rate' => $rate,
            'planned_amount_pln' => round($amount * $rate, 2),
            'actual_amount' => $amount,
            'actual_currency_id' => $currencyId,
            'actual_rate' => $rate,
            'actual_amount_pln' => round($amount * $rate, 2),
            'paid_by' => 'pilot',
            'advance_type' => 'full',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'paid_at' => now(),
            'paid_by_user_id' => Auth::id(),
            'notes' => $data['notes'] ?? null,
            'invoice_number' => filled($data['invoice_number'] ?? null) ? $data['invoice_number'] : null,
            'receipt_number' => filled($data['receipt_number'] ?? null) ? $data['receipt_number'] : null,
            'document_number' => filled($data['invoice_number'] ?? $data['document_number'] ?? null)
                ? ($data['invoice_number'] ?? $data['document_number'])
                : null,
            'order' => $maxOrder + 1,
            'approval_status' => 'pending',
        ]);

        $settlement->recalculateTotals();
        $this->refreshCashFromCosts($settlement);

        return $cost->fresh();
    }

    /**
     * @param  array{
     *     paid_by?: string|null,
     *     actual_amount?: float|int|string|null,
     *     actual_currency_id?: int|null,
     *     notes?: string|null,
     *     payment_method?: string|null,
     *     payment_status?: string|null
     * }  $data
     */
    public function updateExpenseLine(Event $event, EventSettlementCost $cost, array $data): EventSettlementCost
    {
        $settlement = $this->getOrCreateSettlement($event);
        $user = Auth::user();
        $canOfficeEdit = $user && (
            $user->can('manageFinance', $event)
            || $user->can('update', $event)
        );

        if (! $settlement->isEditableByPilot() && ! $canOfficeEdit) {
            throw ValidationException::withMessages([
                'settlement' => 'Rozliczenie jest zamknięte i nie można edytować wydatków.',
            ]);
        }

        if ((int) $cost->settlement_id !== (int) $settlement->id) {
            abort(404);
        }

        $meta = [];
        if (array_key_exists('notes', $data)) {
            $meta['notes'] = $data['notes'] ?: null;
        }
        if (array_key_exists('document_number', $data)) {
            $meta['document_number'] = filled($data['document_number']) ? $data['document_number'] : null;
        }
        if (array_key_exists('invoice_number', $data)) {
            $meta['invoice_number'] = filled($data['invoice_number']) ? $data['invoice_number'] : null;
            $meta['document_number'] = $meta['invoice_number'];
        }
        if (array_key_exists('receipt_number', $data)) {
            $meta['receipt_number'] = filled($data['receipt_number']) ? $data['receipt_number'] : null;
        }

        // Pilot zawsze płaci gotówką.
        $meta['paid_by'] = 'pilot';
        $meta['payment_method'] = 'cash';

        $hasAmount = array_key_exists('actual_amount', $data)
            && $data['actual_amount'] !== ''
            && $data['actual_amount'] !== null;

        // Pozycje planu → stos *_payment (SSoT jak na Finanse).
        if ($hasAmount && EventSettlementCost::isPlanSourceType($cost->source_type)) {
            if ($meta !== []) {
                $cost->update($meta + [
                    'actual_amount' => null,
                    'actual_currency_id' => null,
                    'actual_rate' => null,
                    'actual_amount_pln' => null,
                ]);
            }

            $amount = (float) $data['actual_amount'];
            $all = $settlement->fresh(['costs'])?->costs ?? $settlement->costs()->get();
            $existingPilotCashPayments = app(SettlementPaymentHealthService::class)
                ->paymentRowsForPlanCost($cost->fresh(), $all)
                ->filter(fn (EventSettlementCost $row): bool => EventSettlementCost::isPaymentSourceType($row->source_type)
                    && ($row->paid_by ?? '') === 'pilot'
                    && $this->isPilotCashPaymentMethod($row->payment_method))
                ->sortByDesc('id')
                ->values();

            // Kwota 0 = cofnięcie wydania gotówką (usuń wpłaty pilota), nie Update z zerem.
            if ($amount <= 0) {
                foreach ($existingPilotCashPayments as $payment) {
                    app(DeleteSettlementCostPaymentAction::class)($payment);
                }

                return $cost->fresh();
            }

            $currencyId = $data['actual_currency_id']
                ?? $cost->actual_currency_id
                ?? $cost->planned_currency_id
                ?? $this->defaultPlnCurrencyId();
            $rate = $this->resolveCurrencyRate((int) $currencyId);
            $amountPln = round($amount * $rate, 2);

            $existing = $existingPilotCashPayments->first();

            if ($existing) {
                app(UpdateSettlementCostPaymentAction::class)(new UpdateSettlementCostPaymentData(
                    payment: $existing,
                    amountPln: $amountPln,
                    paymentMethod: 'cash',
                    paidBy: 'pilot',
                    advanceType: $existing->advance_type ?: 'full',
                    paidAt: now(),
                    documentNumber: $meta['document_number'] ?? $existing->document_number,
                    notes: $meta['notes'] ?? $existing->notes,
                    amount: $amount,
                    rate: $rate,
                    currencyId: (int) $currencyId,
                ));
            } else {
                app(RecordSettlementCostPaymentAction::class)(new RecordSettlementCostPaymentData(
                    planCost: $cost->fresh(['plannedCurrency']),
                    amountPln: $amountPln,
                    paymentMethod: 'cash',
                    paidBy: 'pilot',
                    advanceType: 'full',
                    paidAt: now(),
                    documentNumber: $meta['document_number'] ?? null,
                    notes: $meta['notes'] ?? null,
                    paidByUserId: Auth::id(),
                    amount: $amount,
                    rate: $rate,
                    currencyId: (int) $currencyId,
                ));
            }

            return $cost->fresh();
        }

        // Manual / legacy: zapis actual na tym samym wierszu.
        $payload = $meta;
        if ($hasAmount) {
            $currencyId = $data['actual_currency_id'] ?? $cost->actual_currency_id ?? $cost->planned_currency_id ?? $this->defaultPlnCurrencyId();
            $amount = (float) $data['actual_amount'];
            $rate = $this->resolveCurrencyRate((int) $currencyId);

            $payload['actual_amount'] = $amount;
            $payload['actual_currency_id'] = $currencyId;
            $payload['actual_rate'] = $rate;
            $payload['actual_amount_pln'] = round($amount * $rate, 2);
            $payload['payment_status'] = $data['payment_status'] ?? ($amount > 0 ? 'paid' : 'planned');
            $payload['paid_at'] = $amount > 0 ? now() : null;
            $payload['paid_by_user_id'] = Auth::id();
        }

        if ($payload !== []) {
            $cost->update($payload);
            $settlement->recalculateTotals();
            $this->refreshCashFromCosts($settlement);
        }

        return $cost->fresh();
    }

    public function deleteExpense(Event $event, EventSettlementCost $cost): void
    {
        $settlement = $this->getOrCreateSettlement($event);

        if (! $settlement->isEditableByPilot()) {
            abort(403);
        }

        if ((int) $cost->settlement_id !== (int) $settlement->id) {
            abort(404);
        }

        if ($cost->source_type !== 'manual') {
            throw ValidationException::withMessages([
                'cost' => 'Można usuwać tylko wydatki nieprzewidziane dodane ręcznie.',
            ]);
        }

        $cost->delete();
        $settlement->recalculateTotals();
        $settlement->recalculatePilotCash();
        $this->syncPilotCashSpentFromCosts($settlement);
    }

    /**
     * Kurs = ile jednostek waluty źródłowej za 1 jednostkę docelową.
     * Przy podanym kursie from_amount = to_amount × rate (nie „kwota oddana” z formularza).
     *
     * @return array{0: float, 1: float, 2: float} [from_amount, to_amount, exchange_rate]
     */
    public static function normalizeCurrencyExchangeAmounts(
        float|int|string $fromAmount,
        float|int|string $toAmount,
        float|int|string|null $exchangeRate = null,
    ): array {
        $to = round((float) str_replace(',', '.', (string) $toAmount), 2);
        $from = round((float) str_replace(',', '.', (string) $fromAmount), 2);

        $rateProvided = $exchangeRate !== null && $exchangeRate !== '';
        $rate = $rateProvided
            ? round((float) str_replace(',', '.', (string) $exchangeRate), 5)
            : 0.0;

        if ($rateProvided && $rate > 0 && $to > 0) {
            $from = round($to * $rate, 2);
        } elseif ($from > 0 && $to > 0) {
            // Kurs = jednostki źródłowe za 1 jednostkę docelową (from / to).
            $rate = round($from / $to, 5);
        }

        return [$from, $to, $rate];
    }

    /**
     * @param  array{
     *     from_currency_id: int,
     *     to_currency_id: int,
     *     from_amount: float|int|string,
     *     to_amount: float|int|string,
     *     exchange_rate?: float|int|string|null,
     *     exchanged_at?: \DateTimeInterface|string|null,
     *     notes?: string|null,
     * }  $data
     */
    public function recordCurrencyExchange(Event $event, array $data): \App\Models\PilotCurrencyExchange
    {
        $settlement = $this->getOrCreateSettlement($event);
        $this->assertCurrencyExchangeMutable($settlement, $event);

        $payload = $this->validatedCurrencyExchangePayload($data);
        $this->assertSufficientCashForExchange($settlement, $payload);

        $exchange = $settlement->currencyExchanges()->create([
            ...$payload,
            'exchanged_at' => $data['exchanged_at'] ?? now(),
            'notes' => $data['notes'] ?? null,
            'created_by' => Auth::id(),
        ]);

        $settlement->recalculatePilotCash();

        return $exchange;
    }

    /**
     * @param  array{
     *     from_currency_id: int,
     *     to_currency_id: int,
     *     from_amount: float|int|string,
     *     to_amount: float|int|string,
     *     exchange_rate?: float|int|string|null,
     *     exchanged_at?: \DateTimeInterface|string|null,
     *     notes?: string|null,
     * }  $data
     */
    public function updateCurrencyExchange(
        Event $event,
        \App\Models\PilotCurrencyExchange $exchange,
        array $data,
    ): \App\Models\PilotCurrencyExchange {
        $settlement = $this->getOrCreateSettlement($event);
        $this->assertCurrencyExchangeMutable($settlement, $event);

        if ((int) $exchange->settlement_id !== (int) $settlement->id) {
            abort(404);
        }

        $payload = $this->validatedCurrencyExchangePayload($data);
        $this->assertSufficientCashForExchange($settlement, $payload, (int) $exchange->id);

        $exchange->update([
            ...$payload,
            'exchanged_at' => $data['exchanged_at'] ?? $exchange->exchanged_at ?? now(),
            'notes' => array_key_exists('notes', $data) ? ($data['notes'] ?: null) : $exchange->notes,
        ]);

        $settlement->recalculatePilotCash();

        return $exchange->fresh(['fromCurrency', 'toCurrency']) ?? $exchange;
    }

    public function deleteCurrencyExchange(Event $event, \App\Models\PilotCurrencyExchange $exchange): void
    {
        $settlement = $this->getOrCreateSettlement($event);
        $this->assertCurrencyExchangeMutable($settlement, $event);

        if ((int) $exchange->settlement_id !== (int) $settlement->id) {
            abort(404);
        }

        $exchange->delete();
        $settlement->recalculatePilotCash();
    }

    /**
     * Naprawia historyczne wymiany: przy podanym kursie from = to × rate.
     */
    public function repairCurrencyExchangeAmounts(EventSettlement $settlement): void
    {
        if (! Schema::hasTable('pilot_currency_exchanges')) {
            return;
        }

        foreach ($settlement->currencyExchanges()->get() as $exchange) {
            $rate = (float) ($exchange->exchange_rate ?? 0);
            $to = (float) ($exchange->to_amount ?? 0);
            if ($rate <= 0 || $to <= 0) {
                continue;
            }

            $expectedFrom = round($to * $rate, 2);
            if (abs($expectedFrom - (float) $exchange->from_amount) <= 0.009) {
                continue;
            }

            $exchange->update(['from_amount' => $expectedFrom]);
        }
    }

    /**
     * @param  array{from_currency_id: int, from_amount: float, to_currency_id?: int, to_amount?: float, exchange_rate?: float}  $payload
     */
    protected function assertSufficientCashForExchange(
        EventSettlement $settlement,
        array $payload,
        ?int $ignoreExchangeId = null,
    ): void {
        $fromCurrencyId = (int) $payload['from_currency_id'];
        $fromAmount = (float) $payload['from_amount'];

        $cash = $settlement->pilotCashPreparations()->where('currency_id', $fromCurrencyId)->first();
        $fromOffice = (float) ($cash?->provided_amount ?? $cash?->approved_amount ?? 0);

        $exchanges = $settlement->currencyExchanges()->get();
        if ($ignoreExchangeId) {
            $exchanges = $exchanges->where('id', '!=', $ignoreExchangeId);
        }

        $exchangeIn = (float) $exchanges->where('to_currency_id', $fromCurrencyId)->sum('to_amount');
        $exchangeOut = (float) $exchanges->where('from_currency_id', $fromCurrencyId)->sum('from_amount');
        $available = round($fromOffice + $exchangeIn - $exchangeOut, 2);

        if ($fromAmount > $available + 0.009) {
            $symbol = Currency::query()->find($fromCurrencyId)?->symbol
                ?: Currency::query()->find($fromCurrencyId)?->code
                ?: '#'.$fromCurrencyId;

            throw ValidationException::withMessages([
                'exchange' => sprintf(
                    'Brak gotówki w %s do wymiany (dostępne %s, próba %s). Najpierw wypłać gotówkę z biura albo zmniejsz kwotę wymiany.',
                    $symbol,
                    number_format($available, 2, ',', ' '),
                    number_format($fromAmount, 2, ',', ' '),
                ),
            ]);
        }
    }

    protected function assertCurrencyExchangeMutable(EventSettlement $settlement, Event $event): void
    {
        $user = Auth::user();
        $canOfficeEdit = $user && (
            $user->can('manageFinance', $event)
            || $user->can('update', $event)
        );

        if (! $settlement->isEditableByPilot() && ! $canOfficeEdit) {
            throw ValidationException::withMessages([
                'exchange' => 'Rozliczenie jest zamknięte — nie można zmieniać wymiany walut.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     from_currency_id: int,
     *     to_currency_id: int,
     *     from_amount: float,
     *     to_amount: float,
     *     exchange_rate: float
     * }
     */
    protected function validatedCurrencyExchangePayload(array $data): array
    {
        $fromCurrencyId = (int) ($data['from_currency_id'] ?? 0);
        $toCurrencyId = (int) ($data['to_currency_id'] ?? 0);

        if ($fromCurrencyId <= 0 || $toCurrencyId <= 0 || $fromCurrencyId === $toCurrencyId) {
            throw ValidationException::withMessages([
                'exchange' => 'Wybierz dwie różne waluty.',
            ]);
        }

        [$fromAmount, $toAmount, $rate] = self::normalizeCurrencyExchangeAmounts(
            $data['from_amount'] ?? 0,
            $data['to_amount'] ?? 0,
            $data['exchange_rate'] ?? null,
        );

        if ($fromAmount <= 0 || $toAmount <= 0) {
            throw ValidationException::withMessages([
                'exchange' => 'Podaj poprawne kwoty wymiany.',
            ]);
        }

        return [
            'from_currency_id' => $fromCurrencyId,
            'to_currency_id' => $toCurrencyId,
            'from_amount' => $fromAmount,
            'to_amount' => $toAmount,
            'exchange_rate' => $rate,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\PilotCurrencyExchange>
     */
    public function currencyExchanges(Event $event): Collection
    {
        $settlement = $this->getOrCreateSettlement($event);
        $this->repairCurrencyExchangeAmounts($settlement);

        return $settlement->currencyExchanges()
            ->with(['fromCurrency', 'toCurrency'])
            ->orderByDesc('exchanged_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  array<int, array{currency_id: int, returned_amount?: float|int|string|null, notes?: string|null}>  $rows
     */
    public function saveCashReporting(Event $event, array $rows): EventSettlement
    {
        $settlement = $this->getOrCreateSettlement($event);

        if (! $settlement->isEditableByPilot()) {
            throw ValidationException::withMessages([
                'settlement' => 'Rozliczenie jest zamknięte i nie można edytować rozliczenia gotówki.',
            ]);
        }

        foreach ($rows as $row) {
            $currencyId = (int) ($row['currency_id'] ?? 0);

            if ($currencyId <= 0) {
                continue;
            }

            $cash = $settlement->pilotCashPreparations()->firstOrCreate(
                ['currency_id' => $currencyId],
                ['calculated_amount' => 0, 'status' => 'calculated']
            );

            $updates = [];

            if (array_key_exists('returned_amount', $row) && $row['returned_amount'] !== '' && $row['returned_amount'] !== null) {
                $updates['returned_amount'] = (float) $row['returned_amount'];
            }

            if (array_key_exists('notes', $row)) {
                $updates['notes'] = $row['notes'] ?: null;
            }

            if ($updates !== []) {
                $cash->update($updates);
            }
        }

        $this->syncPilotCashSpentFromCosts($settlement);

        return $settlement->fresh();
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function uploadDocument(Event $event, array $data, array $files = [], ?EventSettlementCost $cost = null): EventSettlementDocument
    {
        $settlement = $this->getOrCreateSettlement($event);
        $this->assertSettlementDocumentsMutable($settlement, $event);

        if ($cost && (int) $cost->settlement_id !== (int) $settlement->id) {
            abort(404);
        }

        $storedFiles = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $validation = FileSecurityService::validateDocumentUpload($file);
            if (! $validation['safe']) {
                throw ValidationException::withMessages([
                    'files' => 'Niebezpieczny plik: '.implode('; ', $validation['errors'] ?? ['odrzucono']),
                ]);
            }

            $path = $file->store('event-settlement-documents', 'public');
            $storedFiles[] = $path;
        }

        $linkedCostIds = $cost ? [(int) $cost->id] : ($data['linked_cost_ids'] ?? []);

        $document = $settlement->documents()->create([
            'document_type' => $data['document_type'] ?? 'receipt',
            'document_number' => $data['document_number'] ?? null,
            'vendor_name' => $data['vendor_name'] ?? null,
            'total_amount' => $data['total_amount'] ?? null,
            'currency_id' => $data['currency_id'] ?? $this->defaultPlnCurrencyId(),
            'issue_date' => $data['issue_date'] ?? now()->toDateString(),
            'payment_method' => $data['payment_method'] ?? 'cash',
            'payer_scope' => 'pilot',
            'files' => $storedFiles,
            'linked_cost_ids' => array_values(array_filter($linkedCostIds)),
            'notes' => $data['notes'] ?? null,
            'attach_to_pilot_pdf' => true,
            'attach_to_folder_pdf' => true,
            'approval_status' => 'pending',
            'created_by' => Auth::id(),
        ]);

        $this->notifyOfficeOfUpdate($settlement);

        return $document->fresh();
    }

    public function deleteDocument(Event $event, EventSettlementDocument $document): void
    {
        $settlement = $this->getOrCreateSettlement($event);
        $this->assertSettlementDocumentsMutable($settlement, $event);

        if ((int) $document->settlement_id !== (int) $settlement->id) {
            abort(404);
        }

        $this->assertCanManageSettlementDocument($event, $document);

        foreach ($document->files ?? [] as $path) {
            Storage::disk('public')->delete($path);
        }

        $document->delete();
    }

    public function deleteDocumentFile(Event $event, EventSettlementDocument $document, int $fileIndex): void
    {
        $settlement = $this->getOrCreateSettlement($event);
        $this->assertSettlementDocumentsMutable($settlement, $event);

        if ((int) $document->settlement_id !== (int) $settlement->id) {
            abort(404);
        }

        $this->assertCanManageSettlementDocument($event, $document);

        $files = array_values($document->files ?? []);
        if (! array_key_exists($fileIndex, $files)) {
            throw ValidationException::withMessages([
                'document' => 'Nie znaleziono pliku w dokumencie.',
            ]);
        }

        Storage::disk('public')->delete($files[$fileIndex]);
        unset($files[$fileIndex]);
        $files = array_values($files);

        if ($files === []) {
            $document->delete();

            return;
        }

        $document->update(['files' => $files]);
    }

    protected function assertSettlementDocumentsMutable(EventSettlement $settlement, Event $event): void
    {
        $user = Auth::user();
        $canOfficeEdit = $user && (
            $user->can('manageFinance', $event)
            || $user->can('update', $event)
        );

        if (! $settlement->isEditableByPilot() && ! $canOfficeEdit) {
            throw ValidationException::withMessages([
                'document' => 'Rozliczenie jest zamknięte — nie można zmieniać dokumentów.',
            ]);
        }
    }

    protected function assertCanManageSettlementDocument(Event $event, EventSettlementDocument $document): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(403);
        }

        if ($user->hasRole(['admin', 'super_admin'])) {
            return;
        }

        if ($user->can('manageFinance', $event) || $user->can('update', $event)) {
            return;
        }

        if ((int) $document->created_by === (int) $user->id) {
            return;
        }

        throw ValidationException::withMessages([
            'document' => 'Można usuwać tylko własne dokumenty.',
        ]);
    }

    public function syncPilotCashSpentFromCosts(EventSettlement $settlement): void
    {
        foreach ($settlement->pilotCashPreparations()->get() as $cash) {
            $spent = $this->sumPilotCostsForCurrency($settlement, (int) $cash->currency_id);
            $cash->update(['spent_amount' => $spent]);
        }

        foreach ($settlement->pilotCashPreparations()->get() as $cash) {
            $currencyId = (int) $cash->currency_id;
            $exchangeIn = 0.0;
            $exchangeOut = 0.0;
            if (Schema::hasTable('pilot_currency_exchanges')) {
                $exchangeIn = (float) $settlement->currencyExchanges()->where('to_currency_id', $currencyId)->sum('to_amount');
                $exchangeOut = (float) $settlement->currencyExchanges()->where('from_currency_id', $currencyId)->sum('from_amount');
            }
            $received = (float) ($cash->provided_amount ?? $cash->approved_amount ?? $cash->calculated_amount ?? 0);
            $received = round($received + $exchangeIn - $exchangeOut, 2);
            $spent = (float) ($cash->spent_amount ?? 0);
            $returned = (float) ($cash->returned_amount ?? 0);
            $cash->updateQuietly([
                'balance' => round($received - $spent - $returned, 2),
            ]);
        }
    }

    /**
     * SSoT „Wydane” (gotówka pilota): zaksięgowane wpłaty paid_by=pilot + payment_method=cash,
     * plus legacy actual na planie tylko gdy brak osobnych wierszy *_payment.
     * Nigdy nie traktuje planned_amount jako wydane.
     */
    /**
     * Suma zaliczek/wpłat biura na pozycjach planu z płatnikiem Pilot (w walucie planu).
     */
    protected function sumOfficeAdvancesOnPilotCosts(EventSettlement $settlement, int $currencyId): float
    {
        $fallbackPlnCurrencyId = $this->defaultPlnCurrencyId();
        $allCosts = $settlement->costs()->get();
        $health = app(SettlementPaymentHealthService::class);
        $totalCents = 0;

        foreach ($allCosts as $cost) {
            if (($cost->paid_by ?? '') !== 'pilot' || ($cost->payment_status ?? '') === 'cancelled') {
                continue;
            }
            if (EventSettlementCost::isPaymentSourceType($cost->source_type)) {
                continue;
            }

            $costCurrencyId = (int) ($cost->planned_currency_id ?: $fallbackPlnCurrencyId);
            if ($costCurrencyId !== $currencyId) {
                continue;
            }

            $officePaid = $health->paymentRowsForPlanCost($cost, $allCosts)
                ->filter(fn (EventSettlementCost $row): bool => SettlementPaymentHealthService::isBookedPaymentStatus($row->payment_status)
                    && ($row->paid_by ?? '') === 'office')
                ->sum(fn (EventSettlementCost $row): int => SettlementPaymentHealthService::toCents((float) ($row->actual_amount ?? 0)));

            $totalCents += (int) $officePaid;
        }

        return round($totalCents / 100, 2);
    }

    protected function sumPilotCostsForCurrency(EventSettlement $settlement, int $currencyId, bool $plannedOnly = false): float
    {
        $fallbackPlnCurrencyId = $this->defaultPlnCurrencyId();
        $allCosts = $settlement->costs()->get();
        $health = app(SettlementPaymentHealthService::class);

        if ($plannedOnly) {
            $cents = (int) $allCosts
                ->filter(function (EventSettlementCost $cost) use ($currencyId, $fallbackPlnCurrencyId): bool {
                    if (($cost->paid_by ?? '') !== 'pilot') {
                        return false;
                    }
                    if (($cost->payment_status ?? '') === 'cancelled') {
                        return false;
                    }
                    if (EventSettlementCost::isPaymentSourceType($cost->source_type)) {
                        return false;
                    }
                    $costCurrencyId = (int) ($cost->planned_currency_id ?: $fallbackPlnCurrencyId);

                    return $costCurrencyId === $currencyId;
                })
                ->sum(fn (EventSettlementCost $cost): int => SettlementPaymentHealthService::toCents((float) ($cost->planned_amount ?? 0)));

            return round($cents / 100, 2);
        }

        $spentCents = 0;

        foreach ($allCosts as $cost) {
            if (($cost->paid_by ?? '') !== 'pilot') {
                continue;
            }
            if (($cost->payment_status ?? '') === 'cancelled') {
                continue;
            }

            $costCurrencyId = (int) ($cost->actual_currency_id ?: $cost->planned_currency_id ?: $fallbackPlnCurrencyId);
            if ($costCurrencyId !== $currencyId) {
                continue;
            }

            if (EventSettlementCost::isPaymentSourceType($cost->source_type)) {
                if (! SettlementPaymentHealthService::isBookedPaymentStatus($cost->payment_status)) {
                    continue;
                }
                if (! $this->isPilotCashPaymentMethod($cost->payment_method)) {
                    continue;
                }
                $spentCents += SettlementPaymentHealthService::toCents((float) ($cost->actual_amount ?? 0));

                continue;
            }

            // Legacy: actual na planie tylko gdy brak osobnych wierszy *_payment.
            $separatePayments = $health->paymentRowsForPlanCost($cost, $allCosts)
                ->filter(fn (EventSettlementCost $row): bool => EventSettlementCost::isPaymentSourceType($row->source_type));

            if ($separatePayments->isNotEmpty()) {
                continue;
            }

            if (! SettlementPaymentHealthService::isBookedPaymentStatus($cost->payment_status)) {
                continue;
            }
            if ($cost->actual_amount === null) {
                continue;
            }
            if (! $this->isPilotCashPaymentMethod($cost->payment_method)) {
                continue;
            }

            $spentCents += SettlementPaymentHealthService::toCents((float) $cost->actual_amount);
        }

        return round($spentCents / 100, 2);
    }

    protected function isPilotCashPaymentMethod(?string $method): bool
    {
        // Pilot płaci wyłącznie gotówką; puste = cash (legacy).
        $method = $method ?: 'cash';

        return $method === 'cash';
    }

    public function notifyOfficeOfUpdate(EventSettlement $settlement): void
    {
        // Auto-taski z rozliczenia pilota wyłączone — zaśmiecały skrzynkę biura.
        // Biuro widzi update w Finanse / Pilot; ręczne zadanie można dodać z belki.
    }

    /**
     * Biuro potwierdza i zamyka rozliczenie pilota (po weryfikacji gotówki + wynagrodzenia).
     */
    public function confirmOfficeClose(Event $event): EventSettlement
    {
        $settlement = $event->latestSettlement
            ?? EventSettlement::findOrCreateActiveForEvent($event);

        if ($settlement->status === 'closed') {
            return $settlement;
        }

        $settlement->update([
            'status' => 'closed',
        ]);

        return $settlement->fresh();
    }

    /**
     * Biuro otwiera ponownie zamknięte rozliczenie (korekta).
     */
    public function reopenOfficeSettlement(Event $event): EventSettlement
    {
        $settlement = $event->settlements()
            ->where('status', 'closed')
            ->latest('id')
            ->first();

        if (! $settlement) {
            throw new \InvalidArgumentException('Brak zamkniętego rozliczenia do otwarcia.');
        }

        $settlement->update([
            'status' => 'active',
        ]);

        return $settlement->fresh();
    }

    protected function resolveCurrencyRate(?int $currencyId): float
    {
        if (! $currencyId) {
            return 1.0;
        }

        return (float) (Currency::find($currencyId)?->exchange_rate ?? 1);
    }

    protected function defaultPlnCurrencyId(): ?int
    {
        return Currency::query()
            ->where('code', 'PLN')
            ->orWhere('name', 'like', '%PLN%')
            ->orWhere('name', 'like', '%zł%')
            ->value('id');
    }
}
