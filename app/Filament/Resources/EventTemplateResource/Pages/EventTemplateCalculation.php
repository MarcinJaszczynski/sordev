<?php

namespace App\Filament\Resources\EventTemplateResource\Pages;

use App\Filament\Concerns\AuthorizesEventTemplatePages;
use App\Filament\Concerns\ConfirmsEventTemplateEditing;
use App\Filament\Resources\EventTemplateResource;
use App\Filament\Resources\EventTemplateResource\Concerns\HasEventTemplateWorkflowContext;
use App\Filament\Resources\EventTemplateResource\Concerns\HasGenerateEventAction;
use App\Filament\Resources\EventTemplateResource\Widgets\EventTemplatePriceTable;
use App\Models\Place;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class EventTemplateCalculation extends Page
{
    use AuthorizesEventTemplatePages;
    use ConfirmsEventTemplateEditing;
    use HasEventTemplateWorkflowContext;
    use HasGenerateEventAction;
    use InteractsWithRecord;

    protected static string $resource = EventTemplateResource::class;

    protected static string $view = 'filament.resources.event-template-resource.pages.event-template-calculation';

    protected static ?string $navigationLabel = 'Kalkulacja';

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    public ?int $startPlaceId = null;

    public ?Place $startPlace = null;

    public ?float $transportKm = null;

    /** @var array<int, string> */
    public array $availableStartPlaces = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->bootTemplateEditingGate();

        $this->availableStartPlaces = $this->resolveAvailableStartPlaceOptions();

        $requestedStartPlaceId = request()->integer('start_place') ?: null;

        if ($requestedStartPlaceId && array_key_exists($requestedStartPlaceId, $this->availableStartPlaces)) {
            $this->startPlaceId = $requestedStartPlaceId;
        } elseif (count($this->availableStartPlaces) === 1) {
            $this->startPlaceId = (int) array_key_first($this->availableStartPlaces);
        } else {
            $this->startPlaceId = $requestedStartPlaceId;
        }

        if ($this->startPlaceId) {
            $this->startPlace = Place::find($this->startPlaceId);
            $this->calculateTransportKm();
        }
    }

    public function selectCalculationStartPlace(mixed $value = null): void
    {
        $placeId = $value !== null && $value !== '' ? (int) $value : null;

        $params = ['record' => $this->record];
        if ($placeId) {
            $params['start_place'] = $placeId;
        }

        // Pełny reload — SPA navigate psuje layout Filament (czarna treść).
        $this->redirect(static::getResource()::getUrl('calculation', $params), navigate: false);
    }

    /**
     * @return array<int, string>
     */
    private function resolveAvailableStartPlaceOptions(): array
    {
        $ids = $this->record->resolveAvailableStartPlaceIds();

        if ($ids->isEmpty()) {
            return Place::query()
                ->startingPlaces()
                ->orderBy('name')
                ->pluck('name', 'id')
                ->mapWithKeys(fn ($name, $id) => [(int) $id => (string) $name])
                ->all();
        }

        return Place::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(int) $id => (string) $name])
            ->all();
    }

    private function calculateTransportKm(): void
    {
        if (! $this->startPlace || ! $this->record->start_place_id || ! $this->record->end_place_id) {
            return;
        }

        // Odległość: miejsce startowe -> początek programu
        $d1 = \App\Models\PlaceDistance::where('from_place_id', $this->startPlace->id)
            ->where('to_place_id', $this->record->start_place_id)
            ->first()?->distance_km ?? 0;

        // Odległość: koniec programu -> miejsce startowe
        $d2 = \App\Models\PlaceDistance::where('from_place_id', $this->record->end_place_id)
            ->where('to_place_id', $this->startPlace->id)
            ->first()?->distance_km ?? 0;

        // Program km
        $programKm = $this->record->program_km ?? 0;

        $this->transportKm = $d1 + $d2 + $programKm;
    }

    protected function getHeaderActions(): array
    {
        return [
            ...$this->templateEditingHeaderActions(),
            $this->makePreviewOfferAction(),
            $this->makeGenerateEventAction(),
        ];
    }

    public function getWidgets(): array
    {
        return [
            EventTemplatePriceTable::class,
            \App\Filament\Resources\EventTemplateResource\Widgets\PriceRecalcProgressWidget::class,
        ];
    }

    public function getViewData(): array
    {
        return array_merge(parent::getViewData(), [
            'startPlace' => $this->startPlace,
            'transportKm' => $this->transportKm,
            'calculatedKm' => $this->transportKm ? (1.1 * $this->transportKm + 50) : null,
            'availableStartPlaces' => $this->availableStartPlaces,
        ]);
    }
}
