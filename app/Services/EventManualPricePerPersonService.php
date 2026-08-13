<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Event;
use App\Models\EventPricePerPerson;
use App\Support\MoneyFormatter;

class EventManualPricePerPersonService
{
    /**
     * @return array{
     *     use_manual_price_per_person: bool,
     *     manual_price_per_person_lines: list<array{amount: float, currency_id: int|string}>
     * }
     */
    public function formState(Event $event): array
    {
        $manualRows = $event->pricePerPerson()
            ->where('is_manual', true)
            ->with('currency')
            ->orderBy('id')
            ->get();

        return [
            'use_manual_price_per_person' => $manualRows->isNotEmpty(),
            'manual_price_per_person_lines' => $manualRows
                ->map(fn (EventPricePerPerson $row): array => [
                    'amount' => round((float) $row->price_per_person, 2),
                    'currency_id' => (int) ($row->currency_id ?? $this->plnCurrencyId() ?? 0),
                ])
                ->filter(fn (array $line): bool => $line['currency_id'] > 0 && $line['amount'] > 0)
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function calculatedForEvent(Event $event, ?int $participantCount = null, ?int $gratisCount = null): ?array
    {
        $count = max(1, (int) ($participantCount ?? $event->participant_count ?? 1));
        $gratis = $gratisCount !== null
            ? max(0, $gratisCount)
            : max(0, $event->resolveGratisCountForParticipantCount($count));

        try {
            return EventCostCalculator::for($event)->calculate($count, $gratis);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @param  list<array{amount?: mixed, currency_id?: mixed}>  $lines
     */
    public function sync(Event $event, bool $useManual, array $lines = [], ?float $legacySingleAmount = null): void
    {
        EventPricePerPerson::query()
            ->where('event_id', $event->id)
            ->where('is_manual', true)
            ->delete();

        if (! $useManual) {
            return;
        }

        $normalized = $this->normalizeLines($lines, $legacySingleAmount);

        if ($normalized === []) {
            return;
        }

        $count = max(1, (int) ($event->participant_count ?? 1));
        $variant = $event->qtyVariants()
            ->orderByRaw('ABS(qty - ?)', [$count])
            ->first();
        $calcQty = (int) ($variant?->qty ?? $count);
        $calc = EventCostCalculator::for($event)->calculate($calcQty);

        $transportCost = round((float) collect($calc['lines'] ?? [])
            ->where('category', 'transport')
            ->sum('cost_pln'), 2);

        foreach ($normalized as $line) {
            EventPricePerPerson::create([
                'event_id' => $event->id,
                'event_template_qty_id' => $variant?->id,
                'start_place_id' => $event->start_place_id,
                'currency_id' => $line['currency_id'],
                'price_per_person' => $line['amount'],
                'transport_cost' => $transportCost,
                'price_base' => round((float) ($calc['base_pln'] ?? 0), 2),
                'markup_amount' => round((float) ($calc['markup_pln'] ?? 0), 2),
                'tax_amount' => round((float) ($calc['tax_pln'] ?? 0), 2),
                'price_with_tax' => round((float) ($calc['total_pln'] ?? $line['amount']), 2),
                'tax_breakdown' => $calc['tax_breakdown'] ?? null,
                'is_manual' => true,
            ]);
        }
    }

    /**
     * @return list<array{amount: float, currency_code: string}>
     */
    public function manualLinesForEvent(Event $event, ?int $participantCount = null): array
    {
        $count = max(1, (int) ($participantCount ?? $event->participant_count ?? 1));

        return $event->pricePerPerson()
            ->where('is_manual', true)
            ->with('currency')
            ->get()
            ->sortBy(function (EventPricePerPerson $row) use ($count) {
                $qty = (int) ($row->eventTemplateQty->qty ?? $row->event_template_qty_id ?? 0);

                return abs($qty - $count);
            })
            ->map(fn (EventPricePerPerson $row): array => [
                'amount' => round((float) $row->price_per_person, 2),
                'currency_code' => $this->currencyCode($row->currency),
            ])
            ->filter(fn (array $line): bool => $line['amount'] > 0)
            ->unique('currency_code')
            ->values()
            ->all();
    }

    /**
     * @param  list<array{amount: float, currency_code: string}>  $lines
     */
    public function formatLinesLabel(array $lines): ?string
    {
        if ($lines === []) {
            return null;
        }

        return collect($lines)
            ->map(fn (array $line): string => MoneyFormatter::format($line['amount'], $line['currency_code']))
            ->implode(' + ');
    }

    /**
     * @param  list<array{amount?: mixed, currency_id?: mixed}>  $lines
     * @return list<array{amount: float, currency_id: int}>
     */
    protected function normalizeLines(array $lines, ?float $legacySingleAmount = null): array
    {
        $normalized = collect($lines)
            ->map(function (array $line): ?array {
                $amount = $line['amount'] ?? null;
                $currencyId = (int) ($line['currency_id'] ?? 0);

                if ($currencyId <= 0 || $amount === null || $amount === '' || ! is_numeric($amount)) {
                    return null;
                }

                $amount = round((float) $amount, 2);
                if ($amount <= 0) {
                    return null;
                }

                return [
                    'amount' => $amount,
                    'currency_id' => $currencyId,
                ];
            })
            ->filter()
            ->unique('currency_id')
            ->values();

        if ($normalized->isEmpty() && $legacySingleAmount !== null && $legacySingleAmount > 0) {
            $plnId = $this->plnCurrencyId();
            if ($plnId) {
                return [[
                    'amount' => round($legacySingleAmount, 2),
                    'currency_id' => (int) $plnId,
                ]];
            }
        }

        return $normalized->all();
    }

    protected function currencyCode(?Currency $currency): string
    {
        if (! $currency) {
            return 'PLN';
        }

        if (filled($currency->code)) {
            return strtoupper((string) $currency->code);
        }

        if (filled($currency->symbol)) {
            return strtoupper((string) $currency->symbol);
        }

        return strtoupper((string) ($currency->name ?? 'PLN'));
    }

    protected function plnCurrencyId(): ?int
    {
        return Currency::query()
            ->where('code', 'PLN')
            ->orWhere('symbol', 'PLN')
            ->orWhere('symbol', 'zł')
            ->value('id');
    }
}
