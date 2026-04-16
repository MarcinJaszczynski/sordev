<?php

namespace App\Services;

use App\Filament\Resources\EventTemplateResource\Widgets\EventTemplatePriceTable;
use App\Models\Currency;
use App\Models\EventTemplate;
use App\Models\EventTemplatePricePerPerson;
use App\Models\EventTemplateQty;
use App\Models\EventTemplateStartingPlaceAvailability;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * UnifiedPriceCalculator
 * --------------------------------------------------------------
 * Cel: Jedno, spójne źródło wyliczania wszystkich cen (PLN + inne waluty)
 * dla kombinacji (event_template, start_place, qty_variant).
 *
 * Główne założenia:
 * 1. Wejściem jest EventTemplate z kompletnymi relacjami (programPoints->children, taxes, markup, bus, hotelDays, dayInsurances, qtyVariants itp.).
 * 2. Dla każdego wariantu qty liczymy: koszt bazowy (punkty + noclegi + transport + ubezpieczenia), markup, podatki (tylko PLN), wynik per-person.
 * 3. Waluty obce NIGDY nie dostają podatków (tax_amount=0), ale dostają markup (ten sam % co PLN, naliczany od kosztu w danej walucie).
 * 4. Rounding stosujemy TYLKO przy zapisie (persist) – korzystając z PriceRoundingService:
 *    - PLN: ceil(X/5)*5
 *    - inne waluty: ceil(X/10)*10
 * 5. Zachowujemy pełne RAW wartości przed roundingiem do ewentualnych porównań/debug.
 * 6. Monotoniczność: cena_per_os powinna nie rosnąć przy wzroście qty – jeśli rośnie, logujemy ostrzeżenie.
 * 7. Transakcja: zapis całego zestawu wariantów dla danej kombinacji (template,start_place) w jednej transakcji.
 * 8. Start places: jeśli są wpisy w event_template_starting_place_availability (available=true) – używamy ich;
 *    w przeciwnym wypadku fallback do wszystkich miejsc oznaczonych starting_place=1 lub (ostatni fallback) null.
 */
/**
 * UnifiedPriceCalculator
 *
 * Adapter korzystający z tych samych obliczeń co widok kalkulacji w panelu
 * (Widget `EventTemplatePriceTable`) w celu ujednolicenia wyników i zapisu
 * do tabeli `event_template_price_per_person`.
 *
 * Dokumentacja wstępna po polsku: patrz `docs/PL/README.md`.
 */
class UnifiedPriceCalculator
{
    private ?EventTemplateCalculationEngine $engine = null;
    private ?\Illuminate\Support\Collection $qtyLookup = null;

    public function __construct(?EventTemplateCalculationEngine $engine = null)
    {
        $this->engine = $engine;
    }

    /**
     * Oblicza szczegółową strukturę (bez zapisu w DB).
     * @param EventTemplate $template
     * @param int|null $startPlaceId
     * @param bool $debug
     * @return array
     * Struktura:
     * [ qty => [
     *     'event_template_qty_id' => int,
     *     'qty' => int,
     *     'currencies' => [
     *         'PLN' => [
     *             'raw' => [ 'price_base','markup_amount','tax_amount','price_with_tax','price_per_person','transport_cost' ],
     *             'final' => [ 'price_per_person' ],
     *         ],
     *         'EUR' => [...],
     *     ],
     *     'debug' => [... opcjonalne ...]
     * ] ]
     */
    public function calculate(EventTemplate $template, ?int $startPlaceId = null, bool $debug = false): array
    {
        $template->loadMissing(['taxes', 'markup']);

        $detailedRows = $this->calculateDetailedRows($template, $startPlaceId);
        if (empty($detailedRows)) {
            return [];
        }

        // Lazy-load qtyLookup once per instance
        if ($this->qtyLookup === null) {
            $this->qtyLookup = EventTemplateQty::all()->keyBy(fn ($variant) => (int) $variant->qty);
        }

        $allowForeignCurrencies = method_exists($template, 'isForeignTrip') ? $template->isForeignTrip() : true;
        $output = [];

        foreach ($detailedRows as $qtyKey => $row) {
            $qty = (int) $qtyKey;
            $qtyModel = $this->qtyLookup->get($qty);
            if (! $qtyModel) {
                continue;
            }

            $currenciesBlock = [];

            // Extract currencies data from the structured format returned by engine
            if (isset($row['currencies']) && is_array($row['currencies'])) {
                foreach ($row['currencies'] as $code => $currencyData) {
                    $upperCode = strtoupper($code);
                    if ($upperCode !== 'PLN' && ! $allowForeignCurrencies) {
                        continue;
                    }

                    // Get price from the raw block or final block
                    $rawData = $currencyData['raw'] ?? [];
                    $finalData = $currencyData['final'] ?? [];

                    $rawPerPerson = $rawData['price_per_person'] ?? $finalData['price_per_person'] ?? null;
                    if ($rawPerPerson === null) {
                        continue;
                    }

                    $finalPerPerson = PriceRoundingService::roundPerPerson((float) $rawPerPerson, $upperCode);
                    $currenciesBlock[$upperCode] = [
                        'raw' => [
                            'price_base' => $rawData['price_base'] ?? null,
                            'markup_amount' => $rawData['markup_amount'] ?? null,
                            'tax_amount' => $rawData['tax_amount'] ?? 0,
                            'price_with_tax' => $rawData['price_with_tax'] ?? null,
                            'price_per_person' => $rawPerPerson,
                            'transport_cost' => $rawData['transport_cost'] ?? null,
                            'tax_breakdown' => $rawData['tax_breakdown'] ?? [],
                        ],
                        'final' => [
                            'price_per_person' => $finalPerPerson,
                        ],
                    ];
                }
            } else {
                // Legacy format handling (for backwards compatibility)
                foreach ($row as $code => $data) {
                    if (! is_array($data) || ! array_key_exists('price_per_person_raw', $data)) {
                        continue;
                    }

                    $upperCode = strtoupper($code);
                    if ($upperCode !== 'PLN' && ! $allowForeignCurrencies) {
                        continue;
                    }

                    $rawPerPerson = $data['price_per_person_raw'] ?? null;
                    if ($rawPerPerson === null) {
                        continue;
                    }

                    $finalPerPerson = PriceRoundingService::roundPerPerson((float) $rawPerPerson, $upperCode);
                    $currenciesBlock[$upperCode] = [
                        'raw' => [
                            'price_base' => $data['total_before_markup'] ?? null,
                            'markup_amount' => $upperCode === 'PLN'
                                ? ($row['markup']['amount'] ?? null)
                                : ($data['markup_amount'] ?? null),
                            'tax_amount' => $upperCode === 'PLN'
                                ? ($row['taxes']['total_amount'] ?? 0)
                                : ($data['tax_amount'] ?? 0),
                            'price_with_tax' => $data['total'] ?? null,
                            'price_per_person' => $rawPerPerson,
                            'transport_cost' => null,
                            'tax_breakdown' => $upperCode === 'PLN' ? ($row['taxes']['breakdown'] ?? []) : [],
                        ],
                        'final' => [
                            'price_per_person' => $finalPerPerson,
                        ],
                    ];
                }
            }

            if (empty($currenciesBlock)) {
                continue;
            }

            $topPrice = $currenciesBlock['PLN']['final']['price_per_person'] ?? null;
            if ($topPrice === null) {
                $first = reset($currenciesBlock);
                $topPrice = $first['final']['price_per_person'] ?? null;
            }

            $output[$qty] = [
                'event_template_qty_id' => $qtyModel->id,
                'qty' => $qty,
                'price_per_person' => $topPrice,
                'currencies' => $currenciesBlock,
            ];
        }

        ksort($output);

        return $output;
    }

    /**
     * Oblicza i natychmiast zapisuje (upsert) ceny dla pojedynczego startPlaceId.
     * Zapisuje WSZYSTKIE waluty (PLN + foreign) zastosowawszy rounding do price_per_person.
     */
    public function calculateAndPersist(EventTemplate $template, ?int $startPlaceId = null, bool $deleteExisting = false): void
    {
        $data = $this->calculate($template, $startPlaceId, false);
        if (empty($data)) {
            Log::warning("[UnifiedPriceCalculator] Brak danych kalkulacji (template={$template->id}, start_place=" . ($startPlaceId ?? 'null') . ")");
            return;
        }

    $allowForeignCurrencies = method_exists($template, 'isForeignTrip') ? $template->isForeignTrip() : true;

    DB::transaction(function () use ($data, $template, $startPlaceId, $deleteExisting, $allowForeignCurrencies) {
            if ($deleteExisting) {
                EventTemplatePricePerPerson::where('event_template_id', $template->id)
                    ->when($startPlaceId !== null, fn($q) => $q->where('start_place_id', $startPlaceId))
                    ->delete();
            }

            foreach ($data as $qty => $row) {
                $qtyId = $row['event_template_qty_id'] ?? null;
                if (!$qtyId) continue; // zabezpieczenie
                $currencies = $row['currencies'] ?? [];
                foreach ($currencies as $code => $cdata) {
                    if ($code !== 'PLN' && !$allowForeignCurrencies) {
                        continue;
                    }
                    $currency = Currency::where('symbol', $code)->orWhere('code', $code)->first();
                    if (!$currency) {
                        Log::warning("[UnifiedPriceCalculator] Nie znaleziono waluty code={$code} – pomijam zapis.");
                        continue;
                    }
                    $raw = $cdata['raw'] ?? [];
                    $final = $cdata['final'] ?? [];
                    $pricePerPerson = $final['price_per_person'] ?? $raw['price_per_person'] ?? null;
                    // Zapisujemy także waluty obce nawet jeśli nie mają price_base (historyczny wymóg UI).
                    // W takich przypadkach pola base/markup/tax pozostaną puste lub 0, ale price_per_person będzie dostępne.
                    try {
                        EventTemplatePricePerPerson::updateOrCreate([
                            'event_template_id' => $template->id,
                            'event_template_qty_id' => $qtyId,
                            'currency_id' => $currency->id,
                            'start_place_id' => $startPlaceId,
                        ], [
                            'price_per_person' => $pricePerPerson,
                            'price_base' => $raw['price_base'] ?? null,
                            'markup_amount' => $raw['markup_amount'] ?? null,
                            'tax_amount' => $raw['tax_amount'] ?? null,
                            'transport_cost' => $raw['transport_cost'] ?? null,
                            'price_with_tax' => $raw['price_with_tax'] ?? null,
                            'tax_breakdown' => $raw['tax_breakdown'] ?? [],
                        ]);
                    } catch (\Throwable $e) {
                        Log::error("[UnifiedPriceCalculator] Błąd zapisu: " . $e->getMessage(), [
                            'template_id' => $template->id,
                            'qty_id' => $qtyId,
                            'currency_code' => $code,
                            'start_place_id' => $startPlaceId,
                        ]);
                    }
                }
            }
        });
    }

    /**
     * Recalculates and persists for all relevant start places.
     * available=true w availability => użyj tego zestawu; fallback: wszystkie Place::starting_place.
     */
    public function recalculateForTemplate(EventTemplate $template, bool $deleteExisting = false): void
    {
        $availability = EventTemplateStartingPlaceAvailability::where('event_template_id', $template->id)
            ->where('available', true)
            ->pluck('start_place_id')
            ->filter()
            ->unique()
            ->values();

        if ($availability->isEmpty()) {
            // fallback: wszystkie starting places
            $availability = \App\Models\Place::where('starting_place', true)->pluck('id');
        }

        foreach ($availability as $spid) {
            $this->calculateAndPersist($template, (int)$spid, $deleteExisting);
        }
    }

    /* =============================== */
    /* Poniżej sekcja prywatnych metod */
    /* =============================== */

    protected function calculateDetailedRows(EventTemplate $template, ?int $startPlaceId = null): array
    {
        try {
            // Use injected engine if available (for testing); otherwise use widget
            if ($this->engine !== null) {
                $rawData = $this->engine->calculateDetailed($template, $startPlaceId, null, false);
                // Transform engine output to expected format if needed
                return $rawData ?? [];
            }

            $widget = app(EventTemplatePriceTable::class);
            $widget->record = $template;
            $widget->startPlaceId = $startPlaceId;
            $widget->mount();

            $rows = $widget->detailedCalculations ?? [];

            if ($rows instanceof \Illuminate\Support\Collection) {
                return $rows->toArray();
            }

            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            Log::error('[UnifiedPriceCalculator] Widget calculations failed: ' . $e->getMessage(), [
                'template_id' => $template->id,
                'start_place_id' => $startPlaceId,
            ]);

            return [];
        }
    }
}
