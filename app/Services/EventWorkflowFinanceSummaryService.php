<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventSettlement;
use App\Support\AdminPanelUrls;
use App\Support\CurrencyAmountDisplay;
use App\Support\MoneyFormatter;
use Illuminate\Support\Facades\Schema;

final class EventWorkflowFinanceSummaryService
{
    public function __construct(
        private EventFinanceOverviewService $financeOverview,
        private EventClientPriceComparisonService $clientPriceComparison,
        private ParticipantPaymentBalanceService $participantPaymentBalance,
        private PilotAdvanceService $pilotAdvance,
        private EventPriceSummaryService $priceSummary,
    ) {}

    /**
     * @return array{
     *     price_per_person: string,
     *     price_per_person_hint: string,
     *     calculation: string,
     *     planned: string,
     *     paid: string,
     *     paid_office: string,
     *     paid_pilot: string,
     *     remaining: string,
     *     client_due: string,
     *     client_paid: string,
     *     client_remaining: string,
     *     client_remaining_tone: string,
     *     pilot_cash: string,
     *     pilot_cash_paid: string,
     *     pilot_cash_lines: list<array{label: string, value: string}>,
     *     offer_ready: bool,
     *     offer_base: string,
     *     offer_markup: string,
     *     offer_markup_percent: float,
     *     offer_tax: string,
     *     offer_tax_hint: string|null,
     *     offer_net_profit: string,
     *     offer_total: string,
     *     calc_plan_hint: ?string,
     *     labels: array<string, string>,
     *     settlement_url: string
     * }|null
     */
    public function forEvent(Event $event): ?array
    {
        if (! Schema::hasTable('event_settlements')) {
            return null;
        }

        $settlement = EventSettlement::findActiveForEvent($event);
        $financeUrl = AdminPanelUrls::eventFinance($event);
        $zero = MoneyFormatter::format(0, 'PLN');
        $labels = [
            'price_per_person' => 'Cena za osobę (umowa / szablon)',
            'calculation' => 'Koszty (szablon)',
            'planned' => 'Koszty (planowane)',
            'paid' => 'Zapłacono',
            'paid_office' => 'Zapłacono przez biuro',
            'paid_pilot' => 'Zapłacono przez pilota',
            'remaining' => 'Do zapłaty dostawcom',
            'client_due' => 'Należne od klientów',
            'client_paid' => 'Wpłacono od klientów',
            'client_remaining' => 'Do dopłaty od klientów',
            'pilot_cash' => 'Gotówka pilota (planowane)',
            'pilot_cash_paid' => 'Wypłacono pilotowi',
            'offer_base' => 'Baza oferty',
            'offer_markup' => 'Narzut (zysk)',
            'offer_tax' => 'Podatki',
            'offer_net_profit' => 'Narzut (zysk)',
            'offer_total' => 'Suma oferty',
        ];

        $priceComparison = $this->clientPriceComparison->forEvent($event);
        $pricePerPerson = $priceComparison['label'];
        $priceHint = match ($priceComparison['source']) {
            'contract' => 'Lewa strona: cena z umowy/aneksu/szablonu indywidualnego (PLN + waluty). Prawa: bieżący szablon programu.',
            'manual' => 'Lewa strona: cena ręczna imprezy. Prawa: bieżący szablon programu (per waluta).',
            default => 'Brak ceny z umowy — lewa strona „—”. Prawa: bieżący szablon programu (per waluta).',
        };

        $clientTotals = $this->clientSettlementTotals($event, $settlement);
        $pilotCashPaid = $this->pilotAdvance->formatOfficePayoutLabel($event);
        $offer = $this->offerSlice($event);

        [$clientRemaining, $clientRemainingTone] = $this->formatClientRemaining(
            round($clientTotals['due_pln'] - $clientTotals['paid_pln'], 2),
            [
                'due' => $clientTotals['due_foreign'],
                'paid' => $clientTotals['paid_foreign'],
            ],
        );
        $labels['client_remaining'] = $this->clientRemainingLabel($clientRemainingTone);

        if (! $settlement) {
            return array_merge([
                'price_per_person' => $pricePerPerson,
                'price_per_person_hint' => $priceHint,
                'calculation' => $zero,
                'planned' => $zero,
                'paid' => $zero,
                'paid_office' => $zero,
                'paid_pilot' => $zero,
                'remaining' => $zero,
                'client_due' => CurrencyAmountDisplay::formatMixedTotal(
                    $clientTotals['due_pln'],
                    $clientTotals['due_foreign'],
                    2,
                ),
                'client_paid' => CurrencyAmountDisplay::formatMixedTotal(
                    $clientTotals['paid_pln'],
                    $clientTotals['paid_foreign'],
                    2,
                ),
                'client_remaining' => $clientRemaining,
                'client_remaining_tone' => $clientRemainingTone,
                'pilot_cash' => $zero,
                'pilot_cash_paid' => $pilotCashPaid,
                'pilot_cash_lines' => [],
                'calc_plan_hint' => null,
                'labels' => $labels,
                'settlement_url' => $financeUrl,
            ], $offer);
        }

        $overview = $this->financeOverview->forEvent($event);
        $totals = $overview['totals'] ?? [];
        $pilot = $overview['pilot_cash'] ?? [];

        return array_merge([
            'price_per_person' => $pricePerPerson,
            'price_per_person_hint' => $priceHint,
            'calculation' => (string) ($totals['calculation_label'] ?? $zero),
            'planned' => (string) ($totals['planned_label'] ?? $zero),
            'paid' => (string) ($totals['paid_label'] ?? $zero),
            'paid_office' => (string) ($totals['office_paid_label'] ?? $zero),
            'paid_pilot' => (string) ($totals['pilot_paid_label'] ?? $zero),
            'remaining' => (string) ($totals['remaining_label'] ?? $zero),
            'client_due' => CurrencyAmountDisplay::formatMixedTotal(
                $clientTotals['due_pln'],
                $clientTotals['due_foreign'],
                2,
            ),
            'client_paid' => CurrencyAmountDisplay::formatMixedTotal(
                $clientTotals['paid_pln'],
                $clientTotals['paid_foreign'],
                2,
            ),
            'client_remaining' => $clientRemaining,
            'client_remaining_tone' => $clientRemainingTone,
            // Już złożone: „1 200,00 PLN + 693,00 EUR (≈ 3 014,55 PLN)”
            'pilot_cash' => (string) ($pilot['needed_label'] ?? $zero),
            'pilot_cash_paid' => $pilotCashPaid,
            'pilot_cash_lines' => [],
            'calc_plan_hint' => $totals['calc_plan_hint'] ?? null,
            'labels' => $labels,
            'settlement_url' => $financeUrl,
        ], $offer);
    }

    /**
     * @return array{
     *   offer_ready: bool,
     *   offer_base: string,
     *   offer_markup: string,
     *   offer_markup_percent: float,
     *   offer_tax: string,
     *   offer_tax_hint: string|null,
     *   offer_net_profit: string,
     *   offer_total: string
     * }
     */
    private function offerSlice(Event $event): array
    {
        $zero = MoneyFormatter::format(0, 'PLN');
        try {
            $summary = $this->priceSummary->forEvent($event, includeNearest: false);
        } catch (\Throwable) {
            $summary = ['ready' => false];
        }

        if (! ($summary['ready'] ?? false)) {
            return [
                'offer_ready' => false,
                'offer_base' => $zero,
                'offer_markup' => $zero,
                'offer_markup_percent' => 0.0,
                'offer_tax' => $zero,
                'offer_tax_hint' => null,
                'offer_net_profit' => $zero,
                'offer_total' => $zero,
            ];
        }

        $taxHintParts = [];
        foreach ($summary['tax_breakdown'] ?? [] as $tax) {
            if (! is_array($tax)) {
                continue;
            }
            $name = (string) ($tax['name'] ?? 'Podatek');
            $pct = (float) ($tax['percentage'] ?? 0);
            $amount = MoneyFormatter::format((float) ($tax['amount'] ?? 0), 'PLN');
            $taxHintParts[] = $pct > 0
                ? sprintf('%s %s%% (%s)', $name, rtrim(rtrim(number_format($pct, 2, ',', ' '), '0'), ','), $amount)
                : sprintf('%s (%s)', $name, $amount);
        }

        return [
            'offer_ready' => true,
            'offer_base' => (string) ($summary['labels']['base'] ?? $zero),
            'offer_markup' => (string) ($summary['labels']['markup'] ?? $zero),
            'offer_markup_percent' => (float) ($summary['markup_percent'] ?? 0),
            'offer_tax' => (string) ($summary['labels']['tax'] ?? $zero),
            'offer_tax_hint' => $taxHintParts !== [] ? implode(', ', $taxHintParts) : null,
            'offer_net_profit' => (string) ($summary['labels']['net_profit'] ?? $zero),
            'offer_total' => (string) ($summary['labels']['total'] ?? $zero),
        ];
    }

    /**
     * Należne = ledger (z rabatami) + wolne miejsca × cena umowy;
     * wpłaty = realne kwoty z rozliczenia / ledgeru.
     *
     * @return array{
     *     due_pln: float,
     *     paid_pln: float,
     *     due_foreign: array<string, float>,
     *     paid_foreign: array<string, float>
     * }
     */
    private function clientSettlementTotals(Event $event, ?EventSettlement $settlement): array
    {
        $dueForeign = [];
        $paidForeign = [];
        $duePln = 0.0;
        $paidPln = 0.0;

        try {
            $aggregate = $this->participantPaymentBalance->eventAggregate($event);
            $duePln = round((float) ($aggregate['expected_due_pln'] ?? 0), 2);
            $paidPln = round((float) ($aggregate['total_paid'] ?? 0), 2);

            foreach ($aggregate['expected_foreign'] ?? [] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $code = strtoupper(trim((string) ($row['currency'] ?? '')));
                if ($code === '' || $code === 'PLN') {
                    continue;
                }
                $dueAmount = round((float) ($row['amount'] ?? 0), 2);
                $paidAmount = round((float) ($row['paid'] ?? 0), 2);
                if ($dueAmount > 0.009) {
                    $dueForeign[$code] = ($dueForeign[$code] ?? 0.0) + $dueAmount;
                }
                if ($paidAmount > 0.009) {
                    $paidForeign[$code] = ($paidForeign[$code] ?? 0.0) + $paidAmount;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // Preferuj skumulowane wpłaty z settlement (zgodne z ledgerem + ewentualnymi umowami grupowymi).
        if ($settlement !== null) {
            $paidPln = round((float) ($settlement->participant_paid_pln ?? $paidPln), 2);
        }

        return [
            'due_pln' => $duePln,
            'paid_pln' => $paidPln,
            'due_foreign' => $dueForeign,
            'paid_foreign' => $paidForeign,
        ];
    }

    /**
     * @param  array{due: array<string, float>, paid: array<string, float>}  $clientForeign
     * @return array{0: string, 1: string}
     */
    private function formatClientRemaining(float $remainingPln, array $clientForeign): array
    {
        $remainingForeign = [];
        $codes = array_unique(array_merge(
            array_keys($clientForeign['due'] ?? []),
            array_keys($clientForeign['paid'] ?? []),
        ));

        foreach ($codes as $code) {
            $diff = round((float) (($clientForeign['due'][$code] ?? 0) - ($clientForeign['paid'][$code] ?? 0)), 2);
            if (abs($diff) > 0.009) {
                $remainingForeign[$code] = $diff;
            }
        }

        $hasForeign = $remainingForeign !== [];
        if (abs($remainingPln) <= 0.009 && ! $hasForeign) {
            return [MoneyFormatter::format(0, 'PLN').' · rozliczone', 'ok'];
        }

        $tone = $remainingPln < -0.009 ? 'over' : 'due';
        foreach ($remainingForeign as $amount) {
            if ($amount < -0.009) {
                $tone = 'over';
                break;
            }
        }

        $amountLabel = CurrencyAmountDisplay::formatMixedTotal(abs($remainingPln), array_map('abs', $remainingForeign), 2);

        if ($tone === 'over') {
            return ['nadpłata '.$amountLabel, 'over'];
        }

        return [$amountLabel, 'due'];
    }

    private function clientRemainingLabel(string $tone): string
    {
        return match ($tone) {
            'over' => 'Nadpłata od klientów',
            'ok' => 'Saldo klientów',
            default => 'Do dopłaty od klientów',
        };
    }
}
