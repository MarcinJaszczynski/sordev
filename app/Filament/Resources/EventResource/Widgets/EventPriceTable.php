<?php

namespace App\Filament\Resources\EventResource\Widgets;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventSettlementResource;
use App\Models\Event;
use App\Models\EventPricePerPerson;
use App\Models\EventSettlement;
use App\Services\EventManualPricePerPersonService;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class EventPriceTable extends Widget
{
    protected static string $view = 'filament.resources.event-resource.widgets.event-price-table';

    public ?Event $record = null;

    protected int|string|array $columnSpan = 'full';

    public $calculations = [];

    public $programPoints;

    public $costsByDay;

    public $transportCost = 0;

    public ?float $eventTransportKm = null;

    public $detailedCalculations = [];

    public array $eventOnlyPointsForDetails = [];

    public $qtyVariants = [];

    public $priceRows = [];

    public ?array $currentVariant = null;

    public array $nearestVariants = [];

    public $editingPrice = null; // holds EventPricePerPerson model data for inline editing

    public bool $useManualPricePerPerson = false;

    public ?float $manualPricePerPerson = null;

    /** Autorytatywna kalkulacja (jeden wspólny kalkulator: każdy koszt raz). */
    public array $authoritativeCalc = [];

    public function mount()
    {
        if ($this->record) {
            $this->loadCalculations();
        }
    }

    public function loadCalculations()
    {
        $snapshot = app(\App\Services\EventCalculationSnapshotBuilder::class)->build($this->record);

        $this->programPoints = $snapshot['program_points'];
        $this->costsByDay = $snapshot['costs_by_day'];
        $this->transportCost = $snapshot['transport_cost'];
        $this->eventTransportKm = $snapshot['event_transport_km'];
        $this->calculations = $snapshot['calculations'];
        $this->priceRows = $snapshot['price_rows'];
        $this->detailedCalculations = $snapshot['detailed_calculations'];
        $this->qtyVariants = $snapshot['qty_variants'];
        $this->currentVariant = $snapshot['current_variant'];
        $this->nearestVariants = $snapshot['nearest_variants'];
        $this->eventOnlyPointsForDetails = $snapshot['event_only_points_for_details'];

        $this->syncManualPricePerPersonState();

        $this->loadAuthoritativeCalc();
    }

    /**
     * Autorytatywna kalkulacja (źródło prawdy): każdy koszt raz, marża, podatki, cena/os.
     */
    protected function loadAuthoritativeCalc(): void
    {
        try {
            $calculator = \App\Services\EventCostCalculator::for($this->record);
            $count = max(1, (int) ($this->record->participant_count ?? 1));

            $variants = $this->record->qtyVariants()
                ->orderBy('qty')
                ->pluck('qty')
                ->map(fn ($q) => (int) $q)
                ->filter(fn ($q) => $q > 0)
                ->unique()
                ->values();

            if ($variants->isEmpty()) {
                $variants = collect([$count]);
            }

            $byVariant = [];
            foreach ($variants as $q) {
                $byVariant[$q] = $calculator->calculate($q);
            }

            $this->authoritativeCalc = [
                'current_count' => $count,
                'current' => $byVariant[$count] ?? $calculator->calculate($count),
                'variants' => $byVariant,
            ];
        } catch (\Throwable $e) {
            report($e);
            $this->authoritativeCalc = [];
        }
    }

    /**
     * Zsynchronizuj stan przełącznika ręcznej ceny za osobę z zapisaną pozycją is_manual.
     */
    protected function syncManualPricePerPersonState(): void
    {
        $state = app(EventManualPricePerPersonService::class)->formState($this->record);

        $this->useManualPricePerPerson = $state['use_manual_price_per_person'];

        // Wyciągnij cenę PLN z listy linii, lub pierwszą dostępną
        $lines = $state['manual_price_per_person_lines'] ?? [];
        $this->manualPricePerPerson = null;

        if (! empty($lines)) {
            // Załaduj waluty aby znaleźć PLN
            $currencies = \App\Models\Currency::query()
                ->whereIn('id', array_column($lines, 'currency_id'))
                ->get()
                ->keyBy('id');

            // Najpierw szukaj PLN (po kodzie waluty)
            foreach ($lines as $line) {
                $currencyId = (int) ($line['currency_id'] ?? 0);
                $currency = $currencies[$currencyId] ?? null;
                if ($currency && strtoupper($currency->code ?? '') === 'PLN') {
                    $this->manualPricePerPerson = $line['amount'] ?? null;
                    break;
                }
            }
            // Jeśli nie znaleźliśmy PLN, bierz pierwszą linię
            if ($this->manualPricePerPerson === null) {
                $this->manualPricePerPerson = $lines[0]['amount'] ?? null;
            }
        }
    }

    /**
     * Zapisz (lub usuń) ręczną cenę za osobę — analogicznie do ręcznego kosztu transportu.
     * Reużywa mechanizmu event_price_per_person.is_manual, więc jest jednym źródłem prawdy.
     */
    public function saveManualPricePerPerson(): void
    {
        if (! $this->record) {
            return;
        }

        if (! $this->useManualPricePerPerson) {
            app(EventManualPricePerPersonService::class)->sync($this->record, false, []);

            $this->dispatch('toast', type: 'success', message: 'Przywrócono cenę z kalkulacji.');
            $this->refreshCalculations();

            return;
        }

        $amount = $this->manualPricePerPerson;
        if ($amount === null || ! is_numeric($amount) || (float) $amount < 0) {
            $this->dispatch('toast', type: 'error', message: 'Podaj prawidłową cenę za osobę.');

            return;
        }

        // Znajdź currency_id dla PLN
        $plnCurrency = \App\Models\Currency::query()
            ->where('code', 'PLN')
            ->orWhere('symbol', 'PLN')
            ->orWhere('symbol', 'zł')
            ->first();

        if (! $plnCurrency) {
            $this->dispatch('toast', type: 'error', message: 'Nie znaleziono waluty PLN w systemie.');

            return;
        }

        app(EventManualPricePerPersonService::class)->sync(
            $this->record,
            true,
            [['amount' => round((float) $amount, 2), 'currency_id' => $plnCurrency->id]],
        );

        $this->dispatch('toast', type: 'success', message: 'Zapisano ręczną cenę za osobę. Koszty przeniesiono z kalkulacji.');
        $this->refreshCalculations();
    }

    protected function buildPricePayload(array $data, bool $requirePricePerPerson = false): array
    {
        $errors = [];
        $payload = [];

        $integerFields = [
            'event_template_qty_id' => 'Wariant ilościowy',
            'currency_id' => 'Waluta',
            'start_place_id' => 'Miejsce startu',
        ];

        foreach ($integerFields as $field => $label) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];

            if ($value === '' || $value === null) {
                $payload[$field] = null;

                continue;
            }

            if (! is_numeric($value) || (int) $value < 0) {
                $errors[] = $label.' musi być liczbą całkowitą.';

                continue;
            }

            $payload[$field] = (int) $value;
        }

        $numericFields = [
            'price_per_person' => 'Cena za osobę',
            'transport_cost' => 'Koszt transportu',
            'price_base' => 'Cena bazowa',
            'markup_amount' => 'Narzut',
            'tax_amount' => 'Podatek',
            'price_with_tax' => 'Cena z podatkiem',
        ];

        foreach ($numericFields as $field => $label) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];

            if ($value === '' || $value === null) {
                $payload[$field] = null;

                continue;
            }

            if (! is_numeric($value)) {
                $errors[] = $label.' musi być liczbą.';

                continue;
            }

            $payload[$field] = round((float) $value, 2);
        }

        if ($requirePricePerPerson && (($payload['price_per_person'] ?? null) === null)) {
            $errors[] = 'Cena za osobę jest wymagana.';
        }

        if (array_key_exists('tax_breakdown', $data)) {
            $taxBreakdown = $data['tax_breakdown'];

            if ($taxBreakdown === '' || $taxBreakdown === null) {
                $payload['tax_breakdown'] = null;
            } elseif (is_array($taxBreakdown)) {
                $payload['tax_breakdown'] = $taxBreakdown;
            } elseif (is_string($taxBreakdown)) {
                $decoded = json_decode($taxBreakdown, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $payload['tax_breakdown'] = $decoded;
                } else {
                    $errors[] = 'Nieprawidłowy format JSON dla rozbicia VAT.';
                }
            } else {
                $errors[] = 'Nieprawidłowy format rozbicia VAT.';
            }
        }

        return [$payload, $errors];
    }

    public function refreshCalculations()
    {
        $this->record->refresh();
        $this->record->calculateTotalCost();
        $this->loadCalculations();
    }

    #[On('event-price-table-refresh')]
    public function refreshCalculationsFromForm(): void
    {
        if ($this->record) {
            $this->refreshCalculations();
        }
    }

    public function settleProgramPoint(int $programPointId)
    {
        if (! $this->record) {
            $this->dispatch('toast', type: 'error', message: 'Brak aktywnej imprezy.');

            return;
        }

        $programPoint = $this->record
            ->programPoints()
            ->with(['templatePoint', 'currency'])
            ->find($programPointId);

        if (! $programPoint) {
            $this->dispatch('toast', type: 'error', message: 'Nie znaleziono punktu programu.');

            return;
        }

        $settlement = EventSettlement::findOrCreateActiveForEvent($this->record);
        $settlement->upsertCostFromProgramPoint($programPoint);

        return redirect(EventSettlementResource::getEventFinanceUrlForSettlement($settlement)
            ?? EventResource::getUrl('finance', ['record' => $this->record]));
    }

    // --- Price editing helpers (can be called from front-end Livewire actions) ---
    public function editPrice(int $id)
    {
        $price = EventPricePerPerson::find($id);
        if (! $price || $price->event_id !== $this->record->id) {
            $this->dispatch('toast', type: 'error', message: 'Nie znaleziono ceny.');

            return;
        }

        $this->editingPrice = $price->toArray();

        if (is_array($this->editingPrice['tax_breakdown'] ?? null)) {
            $this->editingPrice['tax_breakdown'] = json_encode($this->editingPrice['tax_breakdown'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    public function saveEditingPrice($data = null)
    {
        // allow calling without params when modal is bound to $this->editingPrice
        if (is_null($data)) {
            $data = $this->editingPrice ?? [];
        }

        if (empty($data['id'])) {
            $this->dispatch('toast', type: 'error', message: 'Brak identyfikatora ceny.');

            return;
        }

        $price = EventPricePerPerson::find($data['id']);
        if (! $price || $price->event_id !== $this->record->id) {
            $this->dispatch('toast', type: 'error', message: 'Nieprawidłowy rekord ceny.');

            return;
        }

        [$payload, $errors] = $this->buildPricePayload($data);

        if (! empty($errors)) {
            $this->dispatch('toast', type: 'error', message: implode(' ', $errors));

            return;
        }

        $price->fill($payload);
        $price->is_manual = true;
        $price->save();

        $this->dispatch('toast', type: 'success', message: 'Cena zapisana (ręczna — nie zostanie nadpisana przy przeliczaniu)');
        $this->editingPrice = null;
        $this->refreshCalculations();
    }

    public function deletePrice(int $id)
    {
        $price = EventPricePerPerson::find($id);
        if (! $price || $price->event_id !== $this->record->id) {
            $this->dispatch('toast', type: 'error', message: 'Nie znaleziono ceny.');

            return;
        }

        $price->delete();
        $this->dispatch('toast', type: 'success', message: 'Cena usunięta');
        $this->refreshCalculations();
    }

    public function createManualPrice(): void
    {
        if (! $this->record) {
            return;
        }

        $participantCount = max(1, (int) ($this->record->participant_count ?? 1));
        $variant = $this->record->qtyVariants()
            ->orderByRaw('ABS(qty - ?)', [$participantCount])
            ->first();

        $price = EventPricePerPerson::create([
            'event_id' => $this->record->id,
            'event_template_qty_id' => $variant?->id,
            'currency_id' => null,
            'start_place_id' => $this->record->start_place_id,
            'price_per_person' => $this->record->resolvedPricePerPerson($participantCount),
            'transport_cost' => 0,
            'price_base' => null,
            'markup_amount' => null,
            'tax_amount' => null,
            'price_with_tax' => null,
            'tax_breakdown' => null,
            'is_manual' => true,
        ]);

        $this->editPrice($price->id);
        $this->dispatch('toast', type: 'success', message: 'Dodano pozycję do ręcznej edycji ceny.');
    }
}
