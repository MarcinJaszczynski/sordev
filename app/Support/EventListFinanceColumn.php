<?php

namespace App\Support;

use App\Models\Event;
use App\Services\EventManualPricePerPersonService;
use App\Services\EventPriceSummaryService;
use App\Services\ParticipantPaymentBalanceService;
use App\Services\SettlementPaymentHealthService;
use Illuminate\Support\Collection;

final class EventListFinanceColumn
{
    /** @var array<int, bool> */
    private static array $overdueByEventId = [];

    /**
     * Cache podsumowania na stronę listy.
     *
     * @var array<int, array{
     *   due_pln: float,
     *   base_pln: float,
     *   price_per_person_label: string
     * }>
     */
    private static array $priceSummaryByEventId = [];

    public static function resolveCurrencyCode(mixed $livewire): string
    {
        if (! is_object($livewire) || ! property_exists($livewire, 'tableFilters')) {
            return 'PLN';
        }

        $code = data_get($livewire->tableFilters, 'finance_display_currency.code');

        if (! is_string($code) || $code === '') {
            return 'PLN';
        }

        return strtoupper($code);
    }

    /**
     * @param  Collection<int, Event>|iterable<Event>  $records
     */
    public static function warmForPage(iterable $records): void
    {
        $events = Collection::make($records)->filter(fn ($record) => $record instanceof Event);

        if ($events->isEmpty()) {
            return;
        }

        $balanceService = app(ParticipantPaymentBalanceService::class);

        foreach ($events as $event) {
            $eventId = (int) $event->getKey();

            if (! array_key_exists($eventId, self::$overdueByEventId)) {
                try {
                    $aggregate = $balanceService->eventAggregate($event);
                    self::$overdueByEventId[$eventId] = ($aggregate['count'] ?? 0) > 0
                        && ($aggregate['coverage_status'] ?? '') === SettlementPaymentHealthService::STATUS_OVERDUE;
                } catch (\Throwable) {
                    self::$overdueByEventId[$eventId] = false;
                }
            }

            if (! array_key_exists($eventId, self::$priceSummaryByEventId)) {
                self::$priceSummaryByEventId[$eventId] = self::resolveClientPriceSummary($event);
            }
        }
    }

    public static function resetWarmCache(): void
    {
        self::$overdueByEventId = [];
        self::$priceSummaryByEventId = [];
    }

    public static function html(Event $record, string $currencyCode = 'PLN'): string
    {
        $currencyCode = strtoupper($currencyCode);
        $fmt = fn ($v) => e(MoneyFormatter::format($v, $currencyCode));

        $summary = self::clientPriceSummaryFor($record);
        $dueAmount = (float) $summary['due_pln'];
        $baseAmount = (float) $summary['base_pln'];
        $priceLabel = $summary['price_per_person_label'] !== ''
            ? $summary['price_per_person_label']
            : '—';

        $paidAmount = self::resolvePaidAmount($record, $currencyCode);
        $paymentsColor = self::resolveClientPaymentsColor($record, $paidAmount, $dueAmount);
        $paymentsDisplay = $fmt($paidAmount).' / '.$fmt($dueAmount);

        $row = fn (string $label, string $value, string $vColor = '#111827') => '<tr>'
            .'<td style="padding:1px 8px 1px 0;color:#9ca3af;font-size:0.72rem;white-space:nowrap">'.$label.'</td>'
            .'<td style="color:'.$vColor.';font-size:0.78rem;font-weight:600;white-space:nowrap">'.$value.'</td>'
            .'</tr>';

        return '<table style="border-collapse:collapse" title="Cena klienta z kalkulatora imprezy; koszt bazowy bez marży/podatków">'
            .$row('Wpłaty klienta:', $paymentsDisplay, $paymentsColor)
            .$row('Cena za os.:', e($priceLabel), '#1f2937')
            .$row('Koszt bazowy:', $baseAmount > 0 ? $fmt($baseAmount) : '—', '#4b5563')
            .'</table>';
    }

    /**
     * @return array{due_pln: float, base_pln: float, price_per_person_label: string}
     */
    private static function clientPriceSummaryFor(Event $record): array
    {
        $eventId = (int) $record->getKey();

        if ($eventId > 0 && array_key_exists($eventId, self::$priceSummaryByEventId)) {
            return self::$priceSummaryByEventId[$eventId];
        }

        $summary = self::resolveClientPriceSummary($record);

        if ($eventId > 0) {
            self::$priceSummaryByEventId[$eventId] = $summary;
        }

        return $summary;
    }

    /**
     * @return array{due_pln: float, base_pln: float, price_per_person_label: string}
     */
    private static function resolveClientPriceSummary(Event $record): array
    {
        $manual = app(EventManualPricePerPersonService::class);
        $manualLines = $manual->manualLinesForEvent($record);
        $storedBase = round((float) ($record->total_cost ?? 0), 2);

        if ($manualLines !== []) {
            $label = $manual->formatLinesLabel($manualLines) ?? '—';
            $paying = max(1, (int) ($record->participant_count ?? 1));
            $plnPerPerson = collect($manualLines)
                ->first(fn (array $line): bool => strtoupper((string) ($line['currency_code'] ?? '')) === 'PLN');
            $duePln = $plnPerPerson
                ? round((float) $plnPerPerson['amount'] * $paying, 2)
                : 0.0;

            // Lista: bez żywego forEvent — baza z events.total_cost (aktualizowana przy zapisie).
            return [
                'due_pln' => $duePln,
                'base_pln' => $storedBase,
                'price_per_person_label' => $label,
            ];
        }

        try {
            $calc = app(EventPriceSummaryService::class)->forEvent(
                $record,
                includeNearest: false,
            );
            if ($calc['ready'] ?? false) {
                return [
                    'due_pln' => (float) ($calc['total_pln'] ?? 0),
                    'base_pln' => (float) ($calc['base_pln'] ?? $storedBase),
                    'price_per_person_label' => (string) ($calc['price_per_person_label'] ?? '—'),
                ];
            }
        } catch (\Throwable) {
            // fallback poniżej
        }

        return [
            'due_pln' => 0.0,
            'base_pln' => $storedBase,
            'price_per_person_label' => '—',
        ];
    }

    private static function resolveClientPaymentsColor(Event $record, float $paidAmount, float $dueAmount): string
    {
        $tolerance = SettlementPaymentHealthService::TOLERANCE;

        if ($dueAmount <= $tolerance || $paidAmount >= $dueAmount - $tolerance) {
            return '#047857';
        }

        if (self::isClientPaymentsOverdue($record)) {
            return '#dc2626';
        }

        return '#2563eb';
    }

    private static function isClientPaymentsOverdue(Event $record): bool
    {
        $eventId = (int) $record->getKey();

        if (array_key_exists($eventId, self::$overdueByEventId)) {
            return self::$overdueByEventId[$eventId];
        }

        try {
            $aggregate = app(ParticipantPaymentBalanceService::class)->eventAggregate($record);

            self::$overdueByEventId[$eventId] = ($aggregate['count'] ?? 0) > 0
                && ($aggregate['coverage_status'] ?? '') === SettlementPaymentHealthService::STATUS_OVERDUE;
        } catch (\Throwable) {
            self::$overdueByEventId[$eventId] = false;
        }

        return self::$overdueByEventId[$eventId];
    }

    private static function resolvePaidAmount(Event $record, string $currencyCode): float
    {
        try {
            if ($currencyCode === 'PLN') {
                if ($record->agreements_amount_paid_total !== null) {
                    return (float) $record->agreements_amount_paid_total;
                }

                return (float) $record->agreements()->sum('amount_paid');
            }

            if ($record->agreements_amount_paid_filtered !== null) {
                return (float) $record->agreements_amount_paid_filtered;
            }

            return (float) $record->agreements()
                ->whereRaw('UPPER(currency) = ?', [$currencyCode])
                ->sum('amount_paid');
        } catch (\Throwable) {
            return 0.0;
        }
    }
}
