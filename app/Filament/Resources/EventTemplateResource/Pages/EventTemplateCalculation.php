<?php

namespace App\Filament\Resources\EventTemplateResource\Pages;

use App\Filament\Concerns\AuthorizesEventTemplatePages;
use App\Filament\Resources\EventTemplateResource;
use App\Filament\Resources\EventTemplateResource\Concerns\HasEventTemplateWorkflowContext;
use App\Filament\Resources\EventTemplateResource\Concerns\HasGenerateEventAction;
use App\Filament\Resources\EventTemplateResource\Widgets\EventTemplatePriceTable;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class EventTemplateCalculation extends Page
{
    use AuthorizesEventTemplatePages;
    use HasEventTemplateWorkflowContext;
    use HasGenerateEventAction;
    use InteractsWithRecord;

    protected static string $resource = EventTemplateResource::class;

    protected static string $view = 'filament.resources.event-template-resource.pages.event-template-calculation';

    protected static ?string $navigationLabel = 'Kalkulacja';

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    public ?int $startPlaceId = null;

    public ?\App\Models\Place $startPlace = null;

    public ?float $transportKm = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->startPlaceId = request()->integer('start_place') ?: null;
        if ($this->startPlaceId) {
            $this->startPlace = \App\Models\Place::find($this->startPlaceId);
            $this->calculateTransportKm();
        }
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

    public function getTitle(): string
    {
        $title = 'Kalkulacja cen - '.$this->record->name;
        if ($this->startPlace) {
            $title .= ' (z '.$this->startPlace->name.')';
        }

        return $title;
    }

    public function getViewData(): array
    {
        return array_merge(parent::getViewData(), [
            'startPlace' => $this->startPlace,
            'transportKm' => $this->transportKm,
            'calculatedKm' => $this->transportKm ? (1.1 * $this->transportKm + 50) : null,
        ]);
    }
}
