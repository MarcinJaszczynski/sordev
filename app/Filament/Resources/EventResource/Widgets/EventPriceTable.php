<?php

namespace App\Filament\Resources\EventResource\Widgets;

use Filament\Widgets\Widget;
use App\Models\Event;

class EventPriceTable extends Widget
{
    protected static string $view = 'filament.resources.event-resource.widgets.event-price-table';
    public ?Event $record = null;
    protected int | string | array $columnSpan = 'full';
    
    public $calculations = [];
    public $programPoints;
    public $costsByDay;
    public $transportCost = 0;
    public $detailedCalculations = [];
    public $editingPrice = null; // holds EventPricePerPerson model data for inline editing
    
    public function mount()
    {
        if ($this->record) {
            $this->loadCalculations();
        }
    }

    public function loadCalculations()
    {
        // Załaduj punkty programu z kosztami
        $this->programPoints = $this->record->programPoints()
            ->with('templatePoint')
            ->where('active', true)
            ->orderBy('day')
            ->orderBy('order')
            ->get();

        // Oblicz koszty transportu (podobnie jak w EventTemplate)
        $this->calculateTransportCost();

        // Oblicz koszty według dni
        $this->costsByDay = $this->programPoints
            ->groupBy('day')
            ->map(function ($points) {
                $totalCost = \App\Services\ProgramPointHelper::sumIncluded($points, 'total_price');
                $programCost = $points->where('include_in_program', true)->sum('total_price');
                
                return [
                    'points_count' => $points->count(),
                    'total_cost' => $totalCost,
                    'program_cost' => $programCost,
                    'calculation_points' => $points->filter(function ($p) { return (bool)($p->include_in_calculation ?? true); })->count(),
                    'program_points' => $points->where('include_in_program', true)->count(),
                    'points' => $points,
                ];
            });

        // Oblicz główne kalkulacje
    $totalProgramCost = \App\Services\ProgramPointHelper::sumIncluded($this->programPoints, 'total_price');
        $totalCostWithTransport = $totalProgramCost + $this->transportCost;

        $this->calculations = [
            'total_points' => $this->programPoints->count(),
            'active_points' => $this->programPoints->where('active', true)->count(),
            'calculation_points' => \App\Services\ProgramPointHelper::countIncluded($this->programPoints),
            'program_points' => $this->programPoints->where('include_in_program', true)->count(),
            'total_program_cost' => $totalProgramCost,
            'transport_cost' => $this->transportCost,
            'total_cost' => $totalCostWithTransport,
            'program_cost' => $this->programPoints->where('include_in_program', true)->sum('total_price'),
            'cost_per_person' => $this->record->participant_count > 0 
                ? $totalCostWithTransport / $this->record->participant_count 
                : 0,
            'days_count' => $this->costsByDay->count(),
            'event_data' => [
                'name' => $this->record->name,
                'client_name' => $this->record->client_name,
                'participant_count' => $this->record->participant_count,
                'start_date' => $this->record->start_date,
                'end_date' => $this->record->end_date,
                'duration_days' => $this->record->duration_days,
                'transfer_km' => $this->record->transfer_km,
                'program_km' => $this->record->program_km,
                'status' => $this->record->status,
                'template_name' => $this->record->eventTemplate->name,
                'bus_name' => $this->record->bus?->name,
                'markup_name' => $this->record->markup?->name,
            ],
        ];

        // Oblicz szczegółowe kalkulacje z uwzględnieniem różnych wariantów
        $this->calculateDetailedPricing();
    }

    public function calculateTransportCost()
    {
        $this->transportCost = 0;
        
        if (!$this->record->bus) {
            return;
        }

        $bus = $this->record->bus;
        $transferKm = $this->record->transfer_km ?? 0;
        $programKm = $this->record->program_km ?? 0;
        $duration = $this->record->duration_days ?? 1;

        $totalKm = 2 * $transferKm + $programKm;
        $includedKm = $duration * ($bus->package_km_per_day ?? 0);
        $baseCost = $duration * ($bus->package_price_per_day ?? 0);

        if ($totalKm <= $includedKm) {
            $this->transportCost = $baseCost;
        } else {
            $extraKm = $totalKm - $includedKm;
            $this->transportCost = $baseCost + ($extraKm * ($bus->extra_km_price ?? 0));
        }

        // Przelicz na PLN jeśli autokar ma inną walutę
        if ($bus->currency && $bus->currency !== 'PLN') {
            // Znajdź walutę w tabeli currencies po symbolu
            $currency = \App\Models\Currency::where('symbol', $bus->currency)->first();
            $exchangeRate = $currency?->exchange_rate ?? 1;
            $this->transportCost *= $exchangeRate;
        }
    }

    public function calculateDetailedPricing()
    {
        $this->detailedCalculations = [];

        // Najpierw spróbuj użyć event-scoped pricePerPerson gdy istnieją
        $eventPrices = $this->record->pricePerPerson()->get();

        if ($eventPrices && $eventPrices->count() > 0) {
            foreach ($eventPrices as $ep) {
                $qty = $ep->event_template_qty_id ? null : ($ep->qty ?? null);
                $qtyLabel = $ep->event_template_qty_id ? 'Wariant szablonu' : ( ($ep->qty ?? null) ? $ep->qty . ' osób' : 'Domyślny');

                $this->detailedCalculations[] = [
                    'qty' => $ep->event_template_qty_id ? null : ($ep->qty ?? null),
                    'name' => $qtyLabel,
                    'program_cost' => $ep->price_base ?? 0,
                    'transport_cost' => $ep->transport_cost ?? 0,
                    'total_cost' => $ep->price_with_tax ?? ($ep->price_per_person * ($ep->qty ?? 1)),
                    'cost_per_person' => $ep->price_per_person ?? 0,
                ];
            }

            return;
        }

        // Jeśli event powstał ze szablonu i mamy start_place_id, spróbuj użyć dokładnego engine'u
        try {
            if ($this->record->event_template_id) {
                $engine = new \App\Services\EventTemplateCalculationEngine();
                $detailed = $engine->calculateDetailed($this->record->eventTemplate, $this->record->start_place_id ?? null, $this->record->transfer_km ?? null);

                if (!empty($detailed)) {
                    foreach ($detailed as $qty => $row) {
                        $this->detailedCalculations[] = [
                            'qty' => $row['qty'] ?? $qty,
                            'name' => $row['name'] ?? ($qty ? "{$qty} osób" : 'Domyślny'),
                            'program_cost' => $row['price_base'] ?? ($row['price_per_person'] ?? 0) * ($row['qty'] ?? 1),
                            'transport_cost' => $row['transport_cost'] ?? 0,
                            'total_cost' => $row['price_with_tax'] ?? ($row['price_per_person'] ?? 0) * ($row['qty'] ?? 1),
                            'cost_per_person' => $row['price_per_person'] ?? 0,
                        ];
                    }

                    return;
                }
            }
        } catch (\Throwable $e) {
            // ignoruj i użyj fallbacku
        }

        // Fallback: symulacja wariantów (jak wcześniej)
        $variants = [
            ['qty' => 10, 'name' => '10 osób'],
            ['qty' => 20, 'name' => '20 osób'],
            ['qty' => 30, 'name' => '30 osób'],
            ['qty' => 40, 'name' => '40 osób'],
            ['qty' => 50, 'name' => '50 osób'],
        ];

        foreach ($variants as $variant) {
            $qty = $variant['qty'];
            $totalProgramCost = 0;

            // use qty only for fallback point calculations (exclude gratis and staff)
            $peopleForPoints = $qty;
            // If record has qtyVariants, still keep qty-only semantics per user request

            // Oblicz koszt programu dla danej liczby osób - treat points and children independently
            foreach ($this->programPoints->filter(function ($p) { return (bool)($p->include_in_calculation ?? true); }) as $point) {
                $groupSize = $point->templatePoint->group_size ?? 1;
                $unitPrice = $point->unit_price ?? 0;

                if ($groupSize <= 1) {
                    $cost = $peopleForPoints * $unitPrice;
                } else {
                    $groupsNeeded = ceil($peopleForPoints / $groupSize);
                    $cost = $groupsNeeded * $unitPrice;
                }

                $totalProgramCost += $cost;
            }

            // Oblicz koszt transportu dla danej liczby osób
            $transportCostForQty = $this->transportCost;
            if ($this->record->bus && $this->record->bus->capacity > 0) {
                $busesNeeded = ceil($qty / $this->record->bus->capacity);
                $transportCostForQty = $this->transportCost * $busesNeeded;
            }

            $totalCostForQty = $totalProgramCost + $transportCostForQty;

            $this->detailedCalculations[$qty] = [
                'qty' => $qty,
                'name' => $variant['name'],
                'program_cost' => $totalProgramCost,
                'transport_cost' => $transportCostForQty,
                'total_cost' => $totalCostForQty,
                'cost_per_person' => $totalCostForQty / $qty,
            ];
        }
    }

    public function refreshCalculations()
    {
        $this->record->refresh();
        $this->record->calculateTotalCost();
        $this->loadCalculations();
    }

    // --- Price editing helpers (can be called from front-end Livewire actions) ---
    public function editPrice(int $id)
    {
        $price = \App\Models\EventPricePerPerson::find($id);
        if (!$price || $price->event_id !== $this->record->id) {
            $this->dispatchBrowserEvent('toast', ['type' => 'error', 'message' => 'Nie znaleziono ceny.']);
            return;
        }

        $this->editingPrice = $price->toArray();
    }

    public function saveEditingPrice($data = null)
    {
        // allow calling without params when modal is bound to $this->editingPrice
        if (is_null($data)) {
            $data = $this->editingPrice ?? [];
        }

        if (empty($data['id'])) {
            $this->dispatchBrowserEvent('toast', ['type' => 'error', 'message' => 'Brak identyfikatora ceny.']);
            return;
        }

        $price = \App\Models\EventPricePerPerson::find($data['id']);
        if (!$price || $price->event_id !== $this->record->id) {
            $this->dispatchBrowserEvent('toast', ['type' => 'error', 'message' => 'Nieprawidłowy rekord ceny.']);
            return;
        }

        // Validate basic numeric fields
        $errors = [];
        if (isset($data['price_per_person']) && !is_numeric($data['price_per_person'])) {
            $errors[] = 'Cena za osobę musi być liczbą.';
        }
        if (isset($data['transport_cost']) && !is_numeric($data['transport_cost'])) {
            $errors[] = 'Koszt transportu musi być liczbą.';
        }
        if (isset($data['price_with_tax']) && !is_numeric($data['price_with_tax'])) {
            $errors[] = 'Cena z podatkiem musi być liczbą.';
        }

        // Try to parse tax_breakdown if provided
        if (isset($data['tax_breakdown']) && $data['tax_breakdown'] !== null && $data['tax_breakdown'] !== '') {
            if (is_string($data['tax_breakdown'])) {
                $decoded = json_decode($data['tax_breakdown'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data['tax_breakdown'] = $decoded;
                } else {
                    $errors[] = 'Nieprawidłowy format JSON dla rozbicia VAT.';
                }
            }
        }

        if (!empty($errors)) {
            $this->dispatchBrowserEvent('toast', ['type' => 'error', 'message' => implode(' ', $errors)]);
            return;
        }

        // Zaktualizuj tylko bezpieczne pola
        $price->price_per_person = isset($data['price_per_person']) ? (float)$data['price_per_person'] : $price->price_per_person;
        $price->transport_cost = isset($data['transport_cost']) ? (float)$data['transport_cost'] : $price->transport_cost;
        $price->price_with_tax = isset($data['price_with_tax']) ? (float)$data['price_with_tax'] : $price->price_with_tax;
        $price->tax_breakdown = array_key_exists('tax_breakdown', $data) ? $data['tax_breakdown'] : $price->tax_breakdown;
        $price->save();

        $this->dispatchBrowserEvent('toast', ['type' => 'success', 'message' => 'Cena zapisana']);
        $this->editingPrice = null;
        $this->refreshCalculations();
    }

    public function deletePrice(int $id)
    {
        $price = \App\Models\EventPricePerPerson::find($id);
        if (!$price || $price->event_id !== $this->record->id) {
            $this->dispatchBrowserEvent('toast', ['type' => 'error', 'message' => 'Nie znaleziono ceny.']);
            return;
        }

        $price->delete();
        $this->dispatchBrowserEvent('toast', ['type' => 'success', 'message' => 'Cena usunięta']);
        $this->refreshCalculations();
    }
}
