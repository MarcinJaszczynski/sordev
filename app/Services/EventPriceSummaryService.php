<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Currency;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Support\MoneyFormatter;

/**
 * Szybkie podsumowanie ceny/os. na Create/Edit imprezy.
 *
 * - Create (szablon): pełna kalkulacja grupy bieżącej + porównanie PPP z 2 najbliższymi qty.
 * - Edit (impreza): EventCostCalculator (transport, hotel, ubezpieczenie, marża, podatki, waluty).
 */
final class EventPriceSummaryService
{
    /**
     * @return array{
     *   ready: bool,
     *   message: ?string,
     *   paying: int,
     *   gratis: int,
     *   total_pln: float,
     *   base_pln: float,
     *   markup_pln: float,
     *   tax_pln: float,
     *   price_per_person: float,
     *   price_per_person_rounded: float,
     *   price_per_person_label: string,
     *   foreign_prices: list<array{currency: string, price_per_person: float, label: string}>,
     *   breakdown_lines: list<array{category: string, name: string, cost_pln: float}>,
     *   nearest: list<array{qty: int, gratis: int, price_per_person: float, label: string, currencies: list<string>}>,
     *   source: string
     * }
     */
    public function forTemplate(
        EventTemplate $template,
        int $startPlaceId,
        int $paying,
        int $gratis = 0,
    ): array {
        $paying = max(1, $paying);
        $gratis = max(0, $gratis);

        if ($startPlaceId <= 0) {
            return $this->empty('Wybierz miejsce wyjazdu, aby policzyć cenę.');
        }

        $engine = app(EventTemplateCalculationEngine::class);

        try {
            $exact = $engine->calculateDetailedForCustomGroup(
                $template,
                $paying,
                $gratis,
                $startPlaceId,
                null,
                false
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->empty('Nie udało się policzyć ceny z szablonu.');
        }

        if ($exact === [] || $exact === null) {
            return $this->empty('Brak danych kalkulacji dla tej grupy.');
        }

        $pln = $this->extractPlnBlock($exact);
        $foreign = $this->extractForeignPrices($exact, $paying);
        $ppp = (float) ($pln['price_per_person'] ?? ($exact['price_per_person'] ?? 0));
        $pppRounded = PriceRoundingService::roundPerPerson($ppp, 'PLN');
        $total = (float) ($pln['price_with_tax'] ?? ($exact['price_with_tax'] ?? 0));

        return [
            'ready' => true,
            'message' => null,
            'paying' => $paying,
            'gratis' => $gratis,
            'total_pln' => round($total, 2),
            'base_pln' => round((float) ($pln['price_base'] ?? ($exact['price_base'] ?? 0)), 2),
            'markup_pln' => round((float) ($pln['markup_amount'] ?? ($exact['markup_amount'] ?? 0)), 2),
            'tax_pln' => round((float) ($pln['tax_amount'] ?? ($exact['tax_amount'] ?? 0)), 2),
            'price_per_person' => round($ppp, 2),
            'price_per_person_rounded' => $pppRounded,
            'price_per_person_label' => $this->composePriceLabel($pppRounded, $foreign),
            'foreign_prices' => $foreign,
            'breakdown_lines' => [],
            'nearest' => $this->nearestTemplatePrices($template, $startPlaceId, $paying, $gratis),
            'source' => 'template',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forEvent(Event $event, ?int $paying = null, ?int $gratis = null, bool $includeNearest = true): array
    {
        $paying = max(1, (int) ($paying ?? $event->participant_count ?? 1));
        $gratisResolved = $gratis !== null
            ? max(0, $gratis)
            : max(0, $event->resolveGratisCountForParticipantCount($paying));

        try {
            $calc = EventCostCalculator::for($event)->calculate($paying, $gratisResolved);
        } catch (\Throwable $e) {
            report($e);

            return $this->empty('Nie udało się policzyć ceny imprezy.');
        }

        $foreign = [];
        foreach ($calc['foreign'] ?? [] as $code => $bucket) {
            $ppp = (float) ($bucket['price_per_person'] ?? 0);
            if ($ppp <= 0) {
                continue;
            }
            $foreign[] = [
                'currency' => (string) $code,
                'price_per_person' => $ppp,
                'label' => MoneyFormatter::format($ppp, (string) $code),
            ];
        }

        $pppRounded = (float) ($calc['price_per_person_rounded'] ?? 0);
        $totalPln = round((float) ($calc['total_pln'] ?? 0), 2);
        $basePln = round((float) ($calc['base_pln'] ?? 0), 2);
        $hasMeaningful = $totalPln > 0 || $basePln > 0 || $pppRounded > 0 || $foreign !== [];

        $nearest = [];
        if ($includeNearest && $hasMeaningful) {
            $templateId = (int) ($event->event_template_id ?? 0);
            $startPlaceId = (int) ($event->start_place_id ?? 0);
            if ($templateId > 0 && $startPlaceId > 0) {
                $template = $event->relationLoaded('eventTemplate')
                    ? $event->eventTemplate
                    : EventTemplate::query()->find($templateId);
                if ($template) {
                    $nearest = $this->nearestTemplatePrices($template, $startPlaceId, $paying, $gratisResolved);
                }
            }
        }

        if (! $hasMeaningful) {
            return $this->empty('Brak pozycji do kalkulacji (program / hotel / transport).');
        }

        return [
            'ready' => true,
            'message' => null,
            'paying' => (int) ($calc['paying'] ?? $paying),
            'gratis' => (int) ($calc['gratis'] ?? $gratisResolved),
            'total_pln' => $totalPln,
            'base_pln' => $basePln,
            'markup_pln' => round((float) ($calc['markup_pln'] ?? 0), 2),
            'tax_pln' => round((float) ($calc['tax_pln'] ?? 0), 2),
            'price_per_person' => round((float) ($calc['price_per_person'] ?? 0), 2),
            'price_per_person_rounded' => $pppRounded,
            'price_per_person_label' => $this->composePriceLabel($pppRounded, $foreign),
            'foreign_prices' => $foreign,
            'breakdown_lines' => array_values($calc['lines'] ?? []),
            'nearest' => $nearest,
            'source' => 'event',
        ];
    }

    /**
     * Dwa najbliższe warianty qty z szablonu — tylko ogólna cena/os. do porównania.
     *
     * @return list<array{qty: int, gratis: int, price_per_person: float, label: string, currencies: list<string>}>
     */
    public function nearestTemplatePrices(
        EventTemplate $template,
        int $startPlaceId,
        int $paying,
        int $gratis = 0,
    ): array {
        $paying = max(1, $paying);
        $gratis = max(0, $gratis);

        $variants = $template->qtyVariants()
            ->select([
                'event_template_qties.id',
                'event_template_qties.qty',
                'event_template_qties.gratis',
            ])
            ->get()
            ->unique('id')
            ->map(fn ($v): array => [
                'id' => (int) $v->id,
                'qty' => (int) ($v->qty ?? 0),
                'gratis' => max(0, (int) ($v->gratis ?? 0)),
            ])
            ->filter(fn (array $v): bool => $v['qty'] > 0)
            ->reject(fn (array $v): bool => $v['qty'] === $paying && $v['gratis'] === $gratis)
            ->sortBy(fn (array $v): int => abs($v['qty'] - $paying) + abs($v['gratis'] - $gratis))
            ->take(2)
            ->values();

        if ($variants->isEmpty()) {
            return [];
        }

        $plnIds = Currency::plnIds();
        $rows = $template->pricesPerPerson()
            ->with(['currency', 'eventTemplateQty'])
            ->where(function ($q) use ($startPlaceId) {
                $q->where('start_place_id', $startPlaceId)->orWhereNull('start_place_id');
            })
            ->get();

        return $variants->map(function (array $variant) use ($rows, $plnIds, $startPlaceId): array {
            $matches = $rows->filter(function ($row) use ($variant) {
                $qtyId = (int) ($row->event_template_qty_id ?? 0);
                $qty = (int) (optional($row->eventTemplateQty)->qty ?? 0);
                $g = (int) (optional($row->eventTemplateQty)->gratis ?? 0);

                if ($qtyId === $variant['id'] || ($qty === $variant['qty'] && $g === $variant['gratis'])) {
                    return true;
                }

                return false;
            });

            $preferPlace = $matches->where('start_place_id', $startPlaceId);
            $pool = $preferPlace->isNotEmpty() ? $preferPlace : $matches;

            $plnRow = null;
            if ($plnIds !== []) {
                $plnRow = $pool->first(fn ($row) => in_array((int) $row->currency_id, $plnIds, true));
            }
            $plnRow ??= $pool->first();

            $ppp = $plnRow ? (float) ($plnRow->price_per_person ?? 0) : 0.0;
            $currencyLabels = $pool
                ->map(function ($row) {
                    $code = strtoupper((string) (
                        $row->currency?->code
                        ?: $row->currency?->symbol
                        ?: 'PLN'
                    ));
                    $amount = (float) ($row->price_per_person ?? 0);

                    return $amount > 0
                        ? ['code' => $code, 'label' => MoneyFormatter::format($amount, $code)]
                        : null;
                })
                ->filter()
                ->unique('label')
                ->sortBy(fn (array $row): int => $row['code'] === 'PLN' ? 0 : 1)
                ->pluck('label')
                ->values()
                ->all();

            if ($currencyLabels === [] && $ppp > 0) {
                $currencyLabels = [MoneyFormatter::format($ppp, 'PLN')];
            }

            return [
                'qty' => $variant['qty'],
                'gratis' => $variant['gratis'],
                'price_per_person' => round($ppp, 2),
                'label' => $currencyLabels !== [] ? implode(' + ', $currencyLabels) : '—',
                'currencies' => $currencyLabels,
            ];
        })->all();
    }

    /**
     * @param  array<string, mixed>  $exact
     * @return array<string, mixed>
     */
    private function extractPlnBlock(array $exact): array
    {
        if (isset($exact['currencies']['PLN']['raw']) && is_array($exact['currencies']['PLN']['raw'])) {
            return $exact['currencies']['PLN']['raw'];
        }

        if (isset($exact['PLN']) && is_array($exact['PLN'])) {
            return $exact['PLN'];
        }

        return $exact;
    }

    /**
     * @param  array<string, mixed>  $exact
     * @return list<array{currency: string, price_per_person: float, label: string}>
     */
    private function extractForeignPrices(array $exact, int $paying): array
    {
        $out = [];
        $currencies = $exact['currencies'] ?? [];
        if (! is_array($currencies)) {
            return [];
        }

        foreach ($currencies as $code => $block) {
            $code = strtoupper((string) $code);
            if ($code === 'PLN' || ! is_array($block)) {
                continue;
            }
            $raw = is_array($block['raw'] ?? null) ? $block['raw'] : $block;
            $ppp = (float) ($raw['price_per_person'] ?? 0);
            if ($ppp <= 0 && isset($raw['price_with_tax']) && $paying > 0) {
                $ppp = round(((float) $raw['price_with_tax']) / $paying, 2);
            }
            if ($ppp <= 0) {
                continue;
            }
            $out[] = [
                'currency' => $code,
                'price_per_person' => $ppp,
                'label' => MoneyFormatter::format($ppp, $code),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{currency: string, price_per_person: float, label: string}>  $foreign
     */
    private function composePriceLabel(float $plnRounded, array $foreign): string
    {
        $parts = [];
        if ($plnRounded > 0) {
            $parts[] = MoneyFormatter::format($plnRounded, 'PLN');
        }
        foreach ($foreign as $row) {
            $parts[] = $row['label'];
        }

        return $parts !== [] ? implode(' + ', $parts) : '—';
    }

    /**
     * @return array<string, mixed>
     */
    private function empty(string $message): array
    {
        return [
            'ready' => false,
            'message' => $message,
            'paying' => 0,
            'gratis' => 0,
            'total_pln' => 0.0,
            'base_pln' => 0.0,
            'markup_pln' => 0.0,
            'tax_pln' => 0.0,
            'price_per_person' => 0.0,
            'price_per_person_rounded' => 0.0,
            'price_per_person_label' => '—',
            'foreign_prices' => [],
            'breakdown_lines' => [],
            'nearest' => [],
            'source' => 'none',
        ];
    }
}
