<?php

namespace App\Filament\Resources\EventTemplateResource\Pages;

use App\Filament\Resources\EventTemplateResource;
use Filament\Resources\Pages\Page;
use App\Models\EventTemplate;
use App\Filament\Resources\EventTemplateResource\Widgets\EventTemplatePriceTable;
use Filament\Actions;

class EventTemplateCalculation extends Page
{
    protected static string $resource = EventTemplateResource::class;
    protected static string $view = 'filament.resources.event-template-resource.pages.event-template-calculation';

    public EventTemplate $record;
    public ?int $startPlaceId = null;
    public ?\App\Models\Place $startPlace = null;
    public ?float $transportKm = null;

    public function mount($record): void
    {
        if (is_array($record) && isset($record['id'])) {
            $this->record = \App\Models\EventTemplate::findOrFail($record['id']);
        } elseif ($record instanceof \App\Models\EventTemplate) {
            $this->record = $record;
        } else {
            $this->record = \App\Models\EventTemplate::findOrFail($record);
        }

        // Pobierz start_place z parametru URL
        $this->startPlaceId = request()->get('start_place');
        if ($this->startPlaceId) {
            $this->startPlace = \App\Models\Place::find($this->startPlaceId);
            $this->calculateTransportKm();
        }
    }

    private function calculateTransportKm(): void
    {
        if (!$this->startPlace || !$this->record->start_place_id || !$this->record->end_place_id) {
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
            Actions\Action::make('back-to-transport')
                ->label('Wróć do transportu')
                ->icon('heroicon-o-truck')
                ->url(fn() => static::getResource()::getUrl('transport', ['record' => $this->record->id]))
                ->color('gray'),
            Actions\Action::make('back')
                ->label('Wróć do edycji')
                ->icon('heroicon-o-arrow-left')
                ->url(fn() => static::getResource()::getUrl('edit', ['record' => $this->record->id]))
                ->color('gray'),
            Actions\Action::make('edit-program')
                ->label('Edytuj program')
                ->icon('heroicon-o-bars-3')
                ->url(fn() => static::getResource()::getUrl('edit-program', ['record' => $this->record->id]))
                ->color('primary'),
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
        $title = 'Kalkulacja cen - ' . $this->record->name;
        if ($this->startPlace) {
            $title .= ' (z ' . $this->startPlace->name . ')';
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
