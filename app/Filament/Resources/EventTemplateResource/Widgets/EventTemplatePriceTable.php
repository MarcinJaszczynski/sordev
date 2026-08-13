<?php

namespace App\Filament\Resources\EventTemplateResource\Widgets;

use App\Models\EventTemplate;
use App\Models\EventTemplatePricePerPerson;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EventTemplatePriceTable extends Widget
{
    /**
     * Zaokrągla wartość w górę do najbliższych 5 zł
     */
    private function ceilTo5($value): float
    {
        return ceil($value / 5) * 5;
    }

    protected static string $view = 'filament.resources.event-template-resource.widgets.event-template-price-table';

    public ?EventTemplate $record = null;

    public ?\App\Models\Place $startPlace = null;

    public ?int $startPlaceId = null;

    public ?float $transportKm = null;

    /**
     * Nadpisanie autokaru (np. autokar przypisany do imprezy, a nie szablonu).
     */
    public $busOverride = null;

    protected int|string|array $columnSpan = 'full';

    public $prices = [];

    public $detailedCalculations = [];

    public $qtyVariants = [];

    public array $variantOverrides = [];

    public function mount()
    {
        // Pobierz start_place_id z parametru URL lub z właściwości
        if (! $this->startPlaceId) {
            $this->startPlaceId = request()->get('start_place');
        }

        // Wczytaj start place jeśli jest ustawiony
        if ($this->startPlaceId) {
            $this->startPlace = \App\Models\Place::find($this->startPlaceId);
        }

        // Dodaj debugging
        \Illuminate\Support\Facades\Log::info('EventTemplatePriceTable mount - startPlaceId: '.($this->startPlaceId ?? 'NULL').', startPlace: '.($this->startPlace ? $this->startPlace->name : 'NULL'));

        // Wczytaj markup i podatki wraz z rekordem
        if ($this->record) {
            $this->record->load(['markup', 'taxes']);
        }
        $this->prices = $this->getPricesProperty();
        $this->qtyVariants = $this->getQtyVariantsProperty();
        $this->detailedCalculations = $this->getDetailedCalculations();

        // Dodaj dodatkowe debugging
        \Illuminate\Support\Facades\Log::info('EventTemplatePriceTable mount - prices count: '.(is_array($this->prices) ? count($this->prices) : $this->prices->count()));
    }

    public function getPricesProperty()
    {
        if (! $this->record) {
            return collect();
        }

        // Znajdź wszystkie polskie waluty (może być duplikatów)
        $polishCurrencyIds = \App\Models\Currency::where(function ($q) {
            $q->where('name', 'like', '%polski%złoty%')
                ->orWhere('name', 'like', '%złoty%polski%')
                ->orWhere('name', '=', 'Polski złoty')
                ->orWhere('name', '=', 'Złoty polski')
                ->orWhere('code', '=', 'PLN');
        })->pluck('id')->toArray();

        $query = EventTemplatePricePerPerson::with(['eventTemplateQty', 'currency', 'startPlace'])
            ->where('event_template_id', $this->record->id)
            ->whereIn('currency_id', $polishCurrencyIds); // Tylko polskie waluty

        // Jeśli jest wybrany start place, filtruj według niego
        if ($this->startPlaceId) {
            $query->where('start_place_id', $this->startPlaceId);
        } else {
            // Jeśli nie ma start place, pokazuj ceny bez miejsca startowego (backward compatibility)
            $query->whereNull('start_place_id');
        }

        $allPrices = $query->orderBy('event_template_qty_id')
            ->orderBy('currency_id')
            ->get();

        // Grupuj po qty i sumuj ceny z różnych walut PLN (tak jak w EventTemplateTransport::getPricesData())
        $groupedPrices = $allPrices->groupBy('event_template_qty_id');

        $results = collect();
        foreach ($groupedPrices as $qtyId => $pricesForQty) {
            // Sumuj wszystkie ceny dla tej samej ilości uczestników (z różnych walut PLN)
            $totalPriceBase = $pricesForQty->sum('price_base') ?: 0;
            $totalMarkup = $pricesForQty->sum('markup_amount') ?: 0;
            $totalTax = $pricesForQty->sum('tax_amount') ?: 0;
            $totalPriceWithTax = $pricesForQty->sum('price_with_tax') ?: $pricesForQty->sum('price_per_person') ?: 0;
            $totalTransportCost = $pricesForQty->sum('transport_cost') ?: 0;

            // Weź qty i inne dane z pierwszego rekordu
            $firstPrice = $pricesForQty->first();

            // Znajdź najlepszą polską walutę dla tego rekordu
            $bestCurrency = $this->findBestPolishCurrency();

            // Utwórz kombinowany obiekt cenowy
            $combinedPrice = new \stdClass;
            $combinedPrice->id = $firstPrice->id;
            $combinedPrice->event_template_id = $firstPrice->event_template_id;
            $combinedPrice->event_template_qty_id = $qtyId;
            $combinedPrice->start_place_id = $firstPrice->start_place_id;
            $combinedPrice->currency_id = $bestCurrency ? $bestCurrency->id : $firstPrice->currency_id;
            $combinedPrice->price_base = $totalPriceBase;
            $combinedPrice->markup_amount = $totalMarkup;
            $combinedPrice->tax_amount = $totalTax;
            $combinedPrice->price_with_tax = $totalPriceWithTax;
            $combinedPrice->price_per_person = $totalPriceWithTax; // Alias
            $combinedPrice->transport_cost = $totalTransportCost;

            // Zachowaj relacje
            $combinedPrice->eventTemplateQty = $firstPrice->eventTemplateQty;
            $combinedPrice->currency = $bestCurrency ?: $firstPrice->currency;
            $combinedPrice->startPlace = $firstPrice->startPlace;

            // Dodaj podatki breakdown jeśli istnieją
            $taxBreakdown = [];
            foreach ($pricesForQty as $price) {
                if ($price->tax_breakdown && is_array($price->tax_breakdown)) {
                    foreach ($price->tax_breakdown as $tax) {
                        $taxName = $tax['tax_name'] ?? 'Nieznany podatek';
                        if (! isset($taxBreakdown[$taxName])) {
                            $taxBreakdown[$taxName] = 0;
                        }
                        $taxBreakdown[$taxName] += floatval($tax['tax_amount'] ?? 0);
                    }
                }
            }

            // Konwertuj breakdown z powrotem do formatu
            $combinedPrice->tax_breakdown = [];
            foreach ($taxBreakdown as $taxName => $taxAmount) {
                $combinedPrice->tax_breakdown[] = [
                    'tax_name' => $taxName,
                    'tax_amount' => $taxAmount,
                ];
            }

            $results->push($combinedPrice);
        }

        return $results;
    }

    /**
     * Znajdź najlepszą polską walutę w systemie (zabezpieczone przed duplikatami)
     */
    private function findBestPolishCurrency(): ?\App\Models\Currency
    {
        // Najpierw znajdź polskie waluty, które faktycznie mają dane cenowe dla tego szablonu
        $polishCurrenciesWithData = \App\Models\Currency::where(function ($q) {
            $q->where('name', 'like', '%polski%złoty%')
                ->orWhere('name', 'like', '%złoty%polski%')
                ->orWhere('name', '=', 'Polski złoty')
                ->orWhere('name', '=', 'Złoty polski')
                ->orWhere('code', '=', 'PLN');
        })
            ->whereHas('eventTemplatePrices', function ($q) {
                $q->where('event_template_id', $this->record->id);
            })
            ->orderBy('id') // Preferuj najniższe ID
            ->get();

        // Jeśli są waluty z danymi, zwróć pierwszą
        if ($polishCurrenciesWithData->isNotEmpty()) {
            return $polishCurrenciesWithData->first();
        }

        // Fallback: zwróć pierwszą polską walutę (nawet bez danych)
        return \App\Models\Currency::where(function ($q) {
            $q->where('name', 'like', '%polski%złoty%')
                ->orWhere('name', 'like', '%złoty%polski%')
                ->orWhere('name', '=', 'Polski złoty')
                ->orWhere('name', '=', 'Złoty polski')
                ->orWhere('code', '=', 'PLN');
        })
            ->orderBy('id')
            ->first();
    }

    public function getQtyVariantsProperty()
    {
        if (! empty($this->variantOverrides)) {
            $variants = [];
            foreach ($this->variantOverrides as $variant) {
                $qty = (int) ($variant['qty'] ?? 0);
                if ($qty < 1) {
                    continue;
                }

                $variants[$qty] = [
                    'qty' => $qty,
                    'gratis' => max(0, (int) ($variant['gratis'] ?? 0)),
                    'staff' => max(0, (int) ($variant['staff'] ?? 0)),
                    'driver' => max(0, (int) ($variant['driver'] ?? 0)),
                ];
            }

            return $variants;
        }

        // Zwraca tablicę wariantów qty z kluczem qty
        $variants = [];
        foreach (\App\Models\EventTemplateQty::all() as $variant) {
            $variants[$variant->qty] = [
                'qty' => $variant->qty,
                'gratis' => $variant->gratis ?? 0,
                'staff' => $variant->staff ?? 0,
                'driver' => $variant->driver ?? 0,
            ];
        }

        return $variants;
    }

    public function getDetailedCalculations()
    {
        if (! $this->record) {
            return [];
        }

        return app(\App\Services\EventTemplateUiCalculationService::class)->calculate(
            template: $this->record,
            startPlaceId: $this->startPlaceId,
            transportKm: $this->transportKm,
            variantOverrides: is_array($this->variantOverrides) ? $this->variantOverrides : [],
            busOverride: $this->busOverride,
        );
    }

    public function recalculatePrices(): void
    {
        Log::info('Wywołano recalculatePrices przez Livewire');
        $userId = null;
        if (Auth::check()) {
            $userId = Auth::id();
        } elseif (method_exists(filament(), 'auth') && filament()->auth()?->user()) {
            $userId = filament()->auth()->user()->id;
        }

        if (! $userId) {
            Notification::make()
                ->title('Błąd')
                ->body('Nie można pobrać ID użytkownika do powiadomienia.')
                ->danger()
                ->send();

            return;
        }

        // Zainicjuj progres natychmiast (pokazuje 0 z total) — total to liczba wszystkich szablonów
        try {
            \App\Services\PriceRecalcProgress::start($userId, \App\Models\EventTemplate::count());
            // powiadom widgety na stronie, jeśli są (Livewire emit)
            if (method_exists($this, 'emit')) {
                $this->emit('priceRecalcStarted');
            }
        } catch (\Throwable $e) {
            // ignore
        }

        \App\Jobs\RecalculateAllEventTemplatePricesJob::dispatch($userId)->afterResponse();

        Notification::make()
            ->title('Przeliczanie cen zostało zlecone')
            ->body('Proces przeliczania cen został dodany do kolejki i wykona się w tle. Otrzymasz powiadomienie po zakończeniu.')
            ->success()
            ->send();
    }

    /**
     * @deprecated Używaj UnifiedPriceCalculator::removeDuplicatePrices() — logika domenowa poza Filament.
     */
    public static function removeDuplicatePrices(): void
    {
        app(\App\Services\UnifiedPriceCalculator::class)->removeDuplicatePrices();
    }

    /**
     * Pomocnicze metody do kalkulacji cen
     */
    private function getQtyId($qty): int
    {
        $qtyRecord = \App\Models\EventTemplateQty::where('qty', $qty)->first();

        return $qtyRecord ? $qtyRecord->id : 0;
    }

    private function calculateMarkup($basePrice): float
    {
        $markupPercent = $this->getMarkupPercent();

        return $basePrice * ($markupPercent / 100);
    }

    /**
     * Prefer markup percent from related Markup model, then markup_id, then template field, then default markup.
     */
    private function getMarkupPercent(): float
    {
        // If relation loaded
        if (isset($this->record->markup) && $this->record->markup?->percent !== null) {
            return (float) $this->record->markup->percent;
        }

        // If markup_id set, try to resolve
        if (! empty($this->record->markup_id)) {
            $m = \App\Models\Markup::find($this->record->markup_id);
            if ($m && $m->percent !== null) {
                return (float) $m->percent;
            }
        }

        // Legacy field on template
        if (isset($this->record->markup_percent) && $this->record->markup_percent !== null && $this->record->markup_percent !== '') {
            return (float) $this->record->markup_percent;
        }

        // Fallback to default markup record
        $default = \App\Models\Markup::where('is_default', true)->first();

        return (float) ($default?->percent ?? 20);
    }

    // Public helper for diagnostics/tests
    public function debugGetMarkupPercent(): float
    {
        return $this->getMarkupPercent();
    }

    private function calculateTax($basePrice): float
    {
        $taxPercent = 23; // VAT 23%
        $markupAmount = $this->calculateMarkup($basePrice);

        return ($basePrice + $markupAmount) * ($taxPercent / 100);
    }

    private function calculatePriceWithTax($basePrice): float
    {
        return $basePrice + $this->calculateMarkup($basePrice) + $this->calculateTax($basePrice);
    }

    private function calculatePricePerPerson($totalPrice, $qty): float
    {
        return $qty > 0 ? $totalPrice / $qty : 0;
    }

    private function calculateTransportCost($qty): float
    {
        // Algorytm: (dojazd + program + powrót) * 1.1 + 50 km, liczba autobusów, limity km, nadmiarowe km
        $bus = $this->busOverride ?? $this->record->bus;
        if (! $bus) {
            return 0;
        }
        $duration = $this->record->duration_days ?? 1;
        $qtyVariant = \App\Models\EventTemplateQty::where('qty', $qty)->first();
        $totalPeople = $qty;
        if ($qtyVariant) {
            $totalPeople += ($qtyVariant->gratis ?? 0) + ($qtyVariant->staff ?? 0) + ($qtyVariant->driver ?? 0);
        }
        $busCapacity = $bus->capacity > 0 ? $bus->capacity : 50;
        $busCount = (int) ceil($totalPeople / $busCapacity);
        // Suma km: dojazd + program + powrót
        $transferKm = $this->record->transfer_km ?? 0;
        $programKm = $this->record->program_km ?? 0;
        $totalKm = ($transferKm * 2) + $programKm;
        $totalKm = $totalKm * 1.1 + 50;
        $includedKm = $duration * $bus->package_km_per_day;
        $baseCost = $duration * $bus->package_price_per_day;
        if ($totalKm <= $includedKm) {
            return $baseCost * $busCount;
        } else {
            $extraKm = $totalKm - $includedKm;

            return ($baseCost + ($extraKm * $bus->extra_km_price)) * $busCount;
        }
    }

    private function prepareTaxBreakdown(): array
    {
        return [
            ['tax_name' => 'VAT 23%', 'tax_amount' => 23],
        ];
    }

    public function calculatePointCost($qty, $groupSize, $unitPrice)
    {
        return \App\Services\ProgramPointPricingCalculator::totalPrice(
            (float) $unitPrice,
            (int) $qty,
            (int) ($groupSize ?? 0) > 0 ? (int) $groupSize : 1,
        );
    }

    /**
     * Oblicza całkowitą sumę w PLN dla danego wariantu qty
     */
    private function calculateTotalInPLN($qtyCalculation)
    {
        $totalPLN = 0;

        // Dodaj PLN bezpośrednio
        if (isset($qtyCalculation['PLN']['total'])) {
            $totalPLN += $qtyCalculation['PLN']['total'];
        } elseif (isset($qtyCalculation['PLN']) && is_numeric($qtyCalculation['PLN'])) {
            // Obsługa gdy przekazano bezpośrednio wartość
            $totalPLN += $qtyCalculation['PLN'];
        }

        // Przelicz inne waluty na PLN używając kursów z tabeli currencies
        foreach ($qtyCalculation as $currencyCode => $data) {
            if ($currencyCode === 'PLN' || $currencyCode === 'hotel_structure') {
                continue;
            }

            $amount = 0;
            if (is_array($data) && isset($data['total'])) {
                $amount = $data['total'];
            } elseif (is_numeric($data)) {
                $amount = $data;
            }

            if ($amount > 0) {
                // Znajdź kurs dla tej waluty
                $currency = \App\Models\Currency::where('symbol', $currencyCode)->first();
                if ($currency && $currency->exchange_rate) {
                    $totalPLN += $amount * $currency->exchange_rate;
                }
            }
        }

        return $totalPLN;
    }
}
