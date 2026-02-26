<?php

namespace App\Support;

use App\Models\Currency;
use App\Models\EventTemplate;
use App\Services\UnifiedPriceCalculator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PriceDisplay
{
    protected static array $engineCache = [];

    /**
     * Zbiera warianty cenowe (PLN + inne waluty) dla danego szablonu oraz miejsca startu.
     * Zwraca listę wariantów, wariant podstawowy oraz gotowe zakresy dla tabeli cennika.
     */
    public static function collectVariants(EventTemplate $template, ?int $startPlaceId = null): array
    {
        if (!method_exists($template, 'pricesPerPerson')) {
            return static::fallbackOrEmpty($template, $startPlaceId);
        }

        $template->loadMissing([
            'pricesPerPerson.currency',
            'pricesPerPerson.eventTemplateQty',
        ]);

        /** @var Collection $prices */
        $prices = $template->pricesPerPerson ?? collect();
        if ($prices->isEmpty()) {
            return static::fallbackOrEmpty($template, $startPlaceId);
        }

        $validPrices = $prices->where('price_per_person', '>', 0);
        if ($validPrices->isEmpty()) {
            return static::fallbackOrEmpty($template, $startPlaceId);
        }

        if ($startPlaceId !== null) {
            $filtered = $validPrices->filter(fn($row) => (int) $row->start_place_id === (int) $startPlaceId);
            if ($filtered->isEmpty()) {
                $filtered = $validPrices->whereNull('start_place_id');
            }
        } else {
            $filtered = $validPrices->whereNull('start_place_id');
            if ($filtered->isEmpty()) {
                $filtered = $validPrices;
            }
        }

        if ($filtered->isEmpty()) {
            return static::fallbackOrEmpty($template, $startPlaceId);
        }

        $allowExtras = true;
        if (method_exists($template, 'isForeignTrip')) {
            $allowExtras = $template->isForeignTrip();
        }

        $variants = $filtered->groupBy('event_template_qty_id')->map(function (Collection $group) use ($allowExtras) {
            $first = $group->first();
            $qtyValue = optional($first->eventTemplateQty)->qty;

            $plnRow = static::findPlnRow($group);
            $plnRaw = $plnRow ? (float) $plnRow->price_per_person : null;
            $plnRounded = static::roundPln($plnRaw);

            $extras = $group
                ->filter(function ($row) {
                    $currency = $row->currency;
                    if (!$currency) {
                        return false;
                    }
                    return !static::isPlnCurrency($currency);
                })
                ->map(function ($row) {
                    $currency = $row->currency;
                    return [
                        'code' => static::currencyLabel($currency),
                        'value' => round((float) $row->price_per_person, 2),
                        'exchange_rate' => (float) ($currency->exchange_rate ?? 0),
                    ];
                })
                ->values();

            if (!$allowExtras) {
                $extras = collect();
            }

            if ($plnRounded === null && $extras->isEmpty()) {
                return null;
            }

            $sortValue = $plnRaw ?? 0.0;
            foreach ($extras as $extra) {
                $rate = $extra['exchange_rate'];
                if ($rate > 0) {
                    $sortValue += $extra['value'] * $rate;
                } else {
                    $sortValue += 100000; // brak kursu – przepchnij na koniec listy
                }
            }

            $display = static::buildDisplayString($plnRounded, $extras);

            return [
                'qty_id' => $first->event_template_qty_id,
                'qty_value' => $qtyValue,
                'pln_price' => $plnRounded,
                'pln_raw' => $plnRaw,
                'extras' => $extras,
                'sort_value' => $sortValue,
                'display' => $display,
            ];
        })->filter()->values();

        if ($variants->isEmpty()) {
            return static::fallbackOrEmpty($template, $startPlaceId);
        }

        $primary = $variants->sortBy('sort_value')->first();
        $ranges = static::buildRanges($variants);

        return [
            'variants' => $variants,
            'primary' => $primary,
            'ranges' => $ranges,
        ];
    }

    protected static function findPlnRow(Collection $group)
    {
        return $group->first(function ($row) {
            $currency = $row->currency;
            if (!$currency) {
                return false;
            }
            return static::isPlnCurrency($currency);
        });
    }

    protected static function isPlnCurrency($currency): bool
    {
        $symbol = strtoupper((string) ($currency->symbol ?? ''));
        if ($symbol === 'PLN') {
            return true;
        }

        $code = strtoupper((string) ($currency->code ?? ''));
        if ($code === 'PLN') {
            return true;
        }

        $name = strtolower((string) ($currency->name ?? ''));
        return str_contains($name, 'złot');
    }

    protected static function currencyLabel($currency): string
    {
        return $currency->symbol ?: ($currency->code ?: ($currency->name ?: ''));
    }

    protected static function roundPln(?float $value): ?int
    {
        if ($value === null || $value <= 0) {
            return null;
        }

        return (int) (ceil($value / 5) * 5);
    }

    protected static function buildDisplayString(?int $plnRounded, Collection $extras): string
    {
        $parts = collect();

        if ($plnRounded !== null) {
            $parts->push(static::formatNumber($plnRounded, true) . ' PLN');
        }

        foreach ($extras as $extra) {
            $parts->push(static::formatNumber($extra['value']) . ' ' . $extra['code']);
        }

        return $parts->implode(' + ');
    }

    protected static function formatNumber(float $value, bool $forceInteger = false): string
    {
        $value = round($value, 2);
        $decimals = $forceInteger ? 0 : ((abs($value - round($value)) < 0.01) ? 0 : 2);
        $formatted = number_format($value, $decimals, ',', ' ');

        if ($decimals > 0) {
            $formatted = rtrim(rtrim($formatted, '0'), ',');
        }

        return $formatted;
    }

    protected static function buildRanges(Collection $variants): Collection
    {
        $sorted = $variants
            ->filter(fn($variant) => $variant['qty_value'] !== null)
            ->sortBy('qty_value')
            ->values();

        if ($sorted->isEmpty()) {
            return collect();
        }

        $ranges = [];
        $count = $sorted->count();
        for ($i = 0; $i < $count; $i++) {
            $variant = $sorted[$i];
            $start = (int) $variant['qty_value'];
            $nextQty = $sorted[$i + 1]['qty_value'] ?? null;
            $end = $nextQty ? ((int) $nextQty - 1) : 55;
            if ($end < $start) {
                $end = $start;
            }

            $display = $variant['display'];
            if (!empty($display)) {
                $ranges[] = [
                    'from' => $start,
                    'to' => $end,
                    'display' => $display,
                    'variant' => $variant,
                ];
            }
        }

        return collect(array_reverse($ranges));
    }

    protected static function fallbackOrEmpty(EventTemplate $template, ?int $startPlaceId): array
    {
        $engineResult = static::collectUsingEngine($template, $startPlaceId);
        return $engineResult ?? static::emptyResult();
    }

    protected static function emptyResult(): array
    {
        return [
            'variants' => collect(),
            'primary' => null,
            'ranges' => collect(),
        ];
    }

    protected static function collectUsingEngine(EventTemplate $template, ?int $startPlaceId): ?array
    {
        $templateId = $template->id ?? spl_object_id($template);
        $cacheKey = $templateId . ':' . ($startPlaceId ?? 'null');

        if (array_key_exists($cacheKey, static::$engineCache)) {
            return static::$engineCache[$cacheKey] ?: null;
        }

        try {
            $calculator = app(UnifiedPriceCalculator::class);
            $data = $calculator->calculate($template, $startPlaceId);
        } catch (\Throwable $e) {
            Log::warning('PriceDisplay: UnifiedPriceCalculator error', [
                'template_id' => $template->id ?? null,
                'start_place_id' => $startPlaceId,
                'message' => $e->getMessage(),
            ]);
            static::$engineCache[$cacheKey] = null;
            return null;
        }

        if (empty($data)) {
            static::$engineCache[$cacheKey] = null;
            return null;
        }

        $allowExtras = method_exists($template, 'isForeignTrip') ? $template->isForeignTrip() : true;
        $transformed = static::transformEngineData($data, $allowExtras);

        static::$engineCache[$cacheKey] = $transformed;

        if ($transformed) {
            Log::debug('PriceDisplay: using engine fallback data', [
                'template_id' => $template->id ?? null,
                'start_place_id' => $startPlaceId,
            ]);
        }

        return $transformed;
    }

    protected static function transformEngineData(array $engineData, bool $allowExtras): ?array
    {
        $variants = collect();
        $currencyCache = [];

        foreach ($engineData as $row) {
            $qtyId = $row['event_template_qty_id'] ?? null;
            $qtyValue = $row['qty'] ?? null;
            $currencies = $row['currencies'] ?? [];
            $plnBlock = $currencies['PLN'] ?? null;
            $plnFinal = $plnBlock['final']['price_per_person'] ?? null;
            $plnRaw = $plnBlock['raw']['price_per_person'] ?? $plnFinal;

            $extras = collect();
            if ($allowExtras) {
                foreach ($currencies as $code => $currencyRow) {
                    if ($code === 'PLN') {
                        continue;
                    }
                    $finalValue = $currencyRow['final']['price_per_person'] ?? null;
                    if ($finalValue === null || $finalValue <= 0) {
                        continue;
                    }

                    if (!array_key_exists($code, $currencyCache)) {
                        $currencyCache[$code] = Currency::where('symbol', $code)
                            ->orWhere('code', $code)
                            ->first();
                    }
                    $currencyModel = $currencyCache[$code];

                    $extras->push([
                        'code' => $currencyModel?->symbol ?: ($currencyModel?->code ?: $code),
                        'value' => round((float) $finalValue, 2),
                        'exchange_rate' => (float) ($currencyModel->exchange_rate ?? 0),
                    ]);
                }
            }

            if ($plnFinal === null && $extras->isEmpty()) {
                continue;
            }

            $plnRounded = $plnFinal !== null ? (int) round($plnFinal) : null;
            $sortValue = static::computeSortValue($plnRaw, $plnRounded, $extras);

            $variants->push([
                'qty_id' => $qtyId,
                'qty_value' => $qtyValue,
                'pln_price' => $plnRounded,
                'pln_raw' => $plnRaw,
                'extras' => $extras,
                'sort_value' => $sortValue,
                'display' => static::buildDisplayString($plnRounded, $extras),
            ]);
        }

        if ($variants->isEmpty()) {
            return null;
        }

        $primary = $variants->sortBy('sort_value')->first();
        $ranges = static::buildRanges($variants);

        return [
            'variants' => $variants,
            'primary' => $primary,
            'ranges' => $ranges,
        ];
    }

    protected static function computeSortValue(?float $plnRaw, ?int $plnRounded, Collection $extras): float
    {
        $value = $plnRaw ?? ($plnRounded ?? 0);
        foreach ($extras as $extra) {
            $rate = $extra['exchange_rate'] ?? 0;
            if ($rate > 0) {
                $value += $extra['value'] * $rate;
            } else {
                $value += 100000;
            }
        }

        return $value;
    }
}
