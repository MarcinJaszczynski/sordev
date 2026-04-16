<?php

namespace App\Filament\Resources\EventResource\Widgets;

use Filament\Widgets\Widget;
use App\Models\Event;
use App\Models\EventPricePerPerson;
use App\Models\EventSettlement;
use App\Filament\Resources\EventSettlementResource;

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
    public array $eventOnlyPointsForDetails = [];
    public $qtyVariants = [];
    public $priceRows = [];
    public ?array $currentVariant = null;
    public array $nearestVariants = [];
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
            ->with(['templatePoint', 'currency'])
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
                'template_name' => $this->record->eventTemplate?->name,
                'bus_name' => $this->record->bus?->name,
                'markup_name' => $this->record->markup?->name,
            ],
        ];

        $this->priceRows = $this->record->pricePerPerson()
            ->with('eventTemplateQty:id,qty,gratis,staff,driver')
            ->orderByDesc('id')
            ->get();

        // Oblicz szczegółowe kalkulacje z uwzględnieniem różnych wariantów
        $this->calculateDetailedPricing();
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
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];

            if ($value === '' || $value === null) {
                $payload[$field] = null;
                continue;
            }

            if (!is_numeric($value) || (int) $value < 0) {
                $errors[] = $label . ' musi być liczbą całkowitą.';
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
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];

            if ($value === '' || $value === null) {
                $payload[$field] = null;
                continue;
            }

            if (!is_numeric($value)) {
                $errors[] = $label . ' musi być liczbą.';
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
        $this->eventOnlyPointsForDetails = [];
        $this->qtyVariants = [];
        $this->currentVariant = null;
        $this->nearestVariants = [];

        $template = $this->record?->eventTemplate;
        if (!$template) {
            return;
        }

        $participantCount = max(1, (int) ($this->record->participant_count ?? 1));
        $eventVariants = $this->record->qtyVariants()->get(['qty', 'gratis', 'staff', 'driver']);

        $exactEventVariant = $eventVariants->firstWhere('qty', $participantCount);
        $closestEventVariant = $eventVariants
            ->sortBy(fn ($variant) => abs(((int) ($variant->qty ?? 0)) - $participantCount))
            ->first();

        $customVariant = [
            'qty' => $participantCount,
            'gratis' => max(0, (int) ($exactEventVariant->gratis ?? $closestEventVariant->gratis ?? 0)),
            'staff' => max(0, (int) ($exactEventVariant->staff ?? $closestEventVariant->staff ?? 1)),
            'driver' => max(0, (int) ($exactEventVariant->driver ?? $closestEventVariant->driver ?? 1)),
        ];

        $selectedVariants = [$customVariant];

        $this->currentVariant = $customVariant;
        $this->nearestVariants = [];

        try {
            $sourceWidget = app(\App\Filament\Resources\EventTemplateResource\Widgets\EventTemplatePriceTable::class);
            $sourceWidget->record = $template;
            $sourceWidget->startPlaceId = $this->record->start_place_id;
            $sourceWidget->transportKm = null;
            $sourceWidget->variantOverrides = $selectedVariants;

            $this->qtyVariants = $sourceWidget->getQtyVariantsProperty();
            $this->detailedCalculations = $sourceWidget->getDetailedCalculations();
            $this->appendEventOnlyPointsToDetailedCalculations();
        } catch (\Throwable $e) {
            report($e);
            $this->qtyVariants = [];
            $this->detailedCalculations = [];
            $this->eventOnlyPointsForDetails = [];
        }
    }

    private function appendEventOnlyPointsToDetailedCalculations(): void
    {
        $eventPoints = collect($this->programPoints ?? [])
            ->filter(function ($point) {
                return (bool) ($point->active ?? true)
                    && (bool) ($point->include_in_calculation ?? true);
            })
            ->sortBy(['day', 'order'])
            ->values();

        if ($eventPoints->isEmpty() || empty($this->detailedCalculations)) {
            return;
        }

        foreach ($this->detailedCalculations as $qty => $currencies) {
            $plnPoints = collect($currencies['PLN']['points'] ?? []);
            $existingNames = $plnPoints
                ->map(fn ($point) => $this->normalizePointName((string) ($point['name'] ?? '')))
                ->filter()
                ->values();

            $missingForPln = collect();

            $pointsForVariant = $eventPoints
                ->map(function ($point) use ($existingNames, $missingForPln) {
                    $name = (string) ($point->templatePoint?->name ?? $point->name ?? 'Bez nazwy');

                    $normalized = $this->normalizePointName($name);
                    $isMissingInPln = $normalized !== '' && ! $existingNames->contains($normalized);

                    if ($isMissingInPln) {
                        $missingForPln->push([
                            'name' => $name,
                            'unit_price' => (float) ($point->unit_price ?? 0),
                            'group_size' => (float) ($point->group_size ?? 1),
                            'cost' => (float) ($point->total_price ?? 0),
                            'is_child' => (bool) ($point->parent_id ?? false),
                            'currency_symbol' => $point->currency?->symbol ?? 'PLN',
                        ]);
                    }

                    return [
                        'name' => $name,
                        'day' => (int) ($point->day ?? 0),
                        'order' => (float) ($point->order ?? 0),
                        'unit_price' => (float) ($point->unit_price ?? 0),
                        'quantity' => (float) ($point->quantity ?? 1),
                        'cost' => (float) ($point->total_price ?? 0),
                        'currency_symbol' => $point->currency?->symbol ?? 'PLN',
                    ];
                })
                ->values()
                ->all();

            if (!empty($pointsForVariant)) {
                $this->eventOnlyPointsForDetails[(string) $qty] = $pointsForVariant;
            }

            if ($missingForPln->isNotEmpty()) {
                $baseDelta = (float) $missingForPln
                    ->filter(fn ($point) => ($point['currency_symbol'] ?? 'PLN') === 'PLN')
                    ->sum('cost');

                $mergedPlnPoints = $plnPoints
                    ->concat($missingForPln->filter(fn ($point) => ($point['currency_symbol'] ?? 'PLN') === 'PLN')->values())
                    ->values()
                    ->all();

                $this->detailedCalculations[$qty]['PLN']['points'] = $mergedPlnPoints;

                if ($baseDelta > 0) {
                    $this->applyPlnDeltaToDetailedTotals($qty, $baseDelta);
                }
            }
        }
    }

    private function normalizePointName(string $name): string
    {
        $name = trim($name);
        $name = ltrim($name, "\xE2\x86\x92 ");
        $name = rtrim($name, '.');

        return mb_strtolower(trim($name));
    }

    private function applyPlnDeltaToDetailedTotals(int|string $qty, float $baseDelta): void
    {
        $pln = $this->detailedCalculations[$qty]['PLN'] ?? null;
        if (!is_array($pln)) {
            return;
        }

        $markupPercent = (float) ($this->detailedCalculations[$qty]['markup']['percent_applied'] ?? 0);
        $markupDelta = round($baseDelta * ($markupPercent / 100), 2);

        if (isset($this->detailedCalculations[$qty]['markup']['amount'])) {
            $this->detailedCalculations[$qty]['markup']['amount'] = round(
                (float) $this->detailedCalculations[$qty]['markup']['amount'] + $markupDelta,
                2
            );
        }

        $taxDeltaTotal = 0.0;
        if (!empty($this->detailedCalculations[$qty]['taxes']['breakdown']) && is_array($this->detailedCalculations[$qty]['taxes']['breakdown'])) {
            foreach ($this->detailedCalculations[$qty]['taxes']['breakdown'] as $idx => $tax) {
                $percent = (float) ($tax['percentage'] ?? 0);
                $applyToBase = (bool) ($tax['apply_to_base'] ?? false);
                $applyToMarkup = (bool) ($tax['apply_to_markup'] ?? false);

                $taxDelta = 0.0;
                if ($applyToBase) {
                    $taxDelta += $baseDelta * ($percent / 100);
                }
                if ($applyToMarkup) {
                    $taxDelta += $markupDelta * ($percent / 100);
                }

                if ($taxDelta > 0) {
                    $taxDelta = round($taxDelta, 2);
                    $taxDeltaTotal += $taxDelta;
                    $this->detailedCalculations[$qty]['taxes']['breakdown'][$idx]['amount'] = round(
                        (float) ($tax['amount'] ?? 0) + $taxDelta,
                        2
                    );
                }
            }
        }

        if (isset($this->detailedCalculations[$qty]['taxes']['total_amount'])) {
            $this->detailedCalculations[$qty]['taxes']['total_amount'] = round(
                (float) $this->detailedCalculations[$qty]['taxes']['total_amount'] + $taxDeltaTotal,
                2
            );
        }

        $this->detailedCalculations[$qty]['PLN']['total_before_markup'] = round(
            (float) ($pln['total_before_markup'] ?? 0) + $baseDelta,
            2
        );
        $this->detailedCalculations[$qty]['PLN']['total_before_tax'] = round(
            (float) ($pln['total_before_tax'] ?? 0) + $baseDelta + $markupDelta,
            2
        );
        $this->detailedCalculations[$qty]['PLN']['total'] = round(
            (float) ($pln['total'] ?? 0) + $baseDelta + $markupDelta + $taxDeltaTotal,
            2
        );
    }

    public function refreshCalculations()
    {
        $this->record->refresh();
        $this->record->calculateTotalCost();
        $this->loadCalculations();
    }

    public function settleProgramPoint(int $programPointId)
    {
        if (!$this->record) {
            $this->dispatch('toast', type: 'error', message: 'Brak aktywnej imprezy.');
            return;
        }

        $programPoint = $this->record
            ->programPoints()
            ->with(['templatePoint', 'currency'])
            ->find($programPointId);

        if (!$programPoint) {
            $this->dispatch('toast', type: 'error', message: 'Nie znaleziono punktu programu.');
            return;
        }

        $settlement = EventSettlement::findOrCreateActiveForEvent($this->record);
        $settlement->upsertCostFromProgramPoint($programPoint);

        return redirect(EventSettlementResource::getUrl('edit', ['record' => $settlement]));
    }

    // --- Price editing helpers (can be called from front-end Livewire actions) ---
    public function editPrice(int $id)
    {
        $price = EventPricePerPerson::find($id);
        if (!$price || $price->event_id !== $this->record->id) {
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
        if (!$price || $price->event_id !== $this->record->id) {
            $this->dispatch('toast', type: 'error', message: 'Nieprawidłowy rekord ceny.');
            return;
        }

        [$payload, $errors] = $this->buildPricePayload($data);

        if (!empty($errors)) {
            $this->dispatch('toast', type: 'error', message: implode(' ', $errors));
            return;
        }

        $price->fill($payload);
        $price->save();

        $this->dispatch('toast', type: 'success', message: 'Cena zapisana');
        $this->editingPrice = null;
        $this->refreshCalculations();
    }

    public function deletePrice(int $id)
    {
        $price = EventPricePerPerson::find($id);
        if (!$price || $price->event_id !== $this->record->id) {
            $this->dispatch('toast', type: 'error', message: 'Nie znaleziono ceny.');
            return;
        }

        $price->delete();
        $this->dispatch('toast', type: 'success', message: 'Cena usunięta');
        $this->refreshCalculations();
    }
}
