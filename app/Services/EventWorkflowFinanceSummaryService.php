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
    ) {}

    /**
     * @return array{
     *     price_per_person: string,
     *     price_per_person_hint: string,
     *     calculation: string,
     *     planned: string,
     *     paid: string,
     *     remaining: string,
     *     client_due: string,
     *     client_paid: string,
     *     pilot_cash: string,
     *     pilot_cash_lines: list<array{label: string, value: string}>,
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
            'price_per_person' => 'Cena za osobę (umowa / kalkulacja)',
            'calculation' => 'Koszty (kalkulacja)',
            'planned' => 'Koszty (plan)',
            'paid' => 'Zapłacone dostawcom',
            'remaining' => 'Do zapłaty dostawcom',
            'client_due' => 'Należne od klientów',
            'client_paid' => 'Wpłacono od klientów',
            'pilot_cash' => 'Gotówka dla pilota',
        ];

        $priceComparison = $this->clientPriceComparison->forEvent($event);
        $pricePerPerson = $priceComparison['label'];
        $priceHint = match ($priceComparison['source']) {
            'contract' => 'Lewa strona: cena z umowy/aneksu/szablonu indywidualnego (PLN + waluty). Prawa: bieżąca kalkulacja programu.',
            'manual' => 'Lewa strona: cena ręczna imprezy. Prawa: bieżąca kalkulacja programu (per waluta).',
            default => 'Brak ceny z umowy — lewa strona „—”. Prawa: bieżąca kalkulacja programu (per waluta).',
        };

        $clientForeign = $this->clientForeignBuckets($event);

        if (! $settlement) {
            return [
                'price_per_person' => $pricePerPerson,
                'price_per_person_hint' => $priceHint,
                'calculation' => $zero,
                'planned' => $zero,
                'paid' => $zero,
                'remaining' => $zero,
                'client_due' => CurrencyAmountDisplay::formatMixedTotal(0, $clientForeign['due'], 2),
                'client_paid' => CurrencyAmountDisplay::formatMixedTotal(0, $clientForeign['paid'], 2),
                'pilot_cash' => $zero,
                'pilot_cash_lines' => [],
                'calc_plan_hint' => null,
                'labels' => $labels,
                'settlement_url' => $financeUrl,
            ];
        }

        $overview = $this->financeOverview->forEvent($event);
        $totals = $overview['totals'] ?? [];
        $pilot = $overview['pilot_cash'] ?? [];

        $clientDuePln = round((float) ($totals['client_due_pln'] ?? 0), 2);
        $clientPaidPln = round((float) ($totals['client_paid_pln'] ?? 0), 2);

        return [
            'price_per_person' => $pricePerPerson,
            'price_per_person_hint' => $priceHint,
            'calculation' => (string) ($totals['calculation_label'] ?? $zero),
            'planned' => (string) ($totals['planned_label'] ?? $zero),
            'paid' => (string) ($totals['paid_label'] ?? $zero),
            'remaining' => (string) ($totals['remaining_label'] ?? $zero),
            'client_due' => CurrencyAmountDisplay::formatMixedTotal($clientDuePln, $clientForeign['due'], 2),
            'client_paid' => CurrencyAmountDisplay::formatMixedTotal($clientPaidPln, $clientForeign['paid'], 2),
            // Już złożone: „1 200,00 PLN + 693,00 EUR (≈ 3 014,55 PLN)”
            'pilot_cash' => (string) ($pilot['needed_label'] ?? $zero),
            'pilot_cash_lines' => [],
            'calc_plan_hint' => $totals['calc_plan_hint'] ?? null,
            'labels' => $labels,
            'settlement_url' => $financeUrl,
        ];
    }

    /**
     * @return array{due: array<string, float>, paid: array<string, float>}
     */
    private function clientForeignBuckets(Event $event): array
    {
        $due = [];
        $paid = [];

        try {
            $aggregate = $this->participantPaymentBalance->eventAggregate($event);
        } catch (\Throwable $e) {
            report($e);

            return ['due' => $due, 'paid' => $paid];
        }

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
                $due[$code] = ($due[$code] ?? 0.0) + $dueAmount;
            }
            if ($paidAmount > 0.009) {
                $paid[$code] = ($paid[$code] ?? 0.0) + $paidAmount;
            }
        }

        return ['due' => $due, 'paid' => $paid];
    }
}
