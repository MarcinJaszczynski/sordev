<?php

namespace App\Filament\Pilot\Resources\PilotEventResource\Pages;

use App\Filament\Pilot\Resources\PilotEventResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;

class ListPilotEvents extends ListRecords
{
    protected static string $resource = PilotEventResource::class;

    protected static string $view = 'filament.pilot.resources.pilot-event-resource.pages.list-pilot-events';

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Moje wycieczki';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Moje wycieczki';
    }

    /**
     * @return Collection<int, \App\Models\Event>
     */
    public function getTripsProperty(): Collection
    {
        return PilotEventResource::getEloquentQuery()
            ->with(['eventTemplate', 'startPlace'])
            ->orderByDesc('start_date')
            ->get();
    }
}
