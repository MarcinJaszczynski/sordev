<?php

namespace App\Filament\Client\Resources\ClientEventResource\Pages;

use App\Filament\Client\Resources\ClientEventResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;

class ListClientEvents extends ListRecords
{
    protected static string $resource = ClientEventResource::class;

    protected static string $view = 'filament.client.resources.client-event-resource.pages.list-client-events';

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
        return ClientEventResource::getEloquentQuery()
            ->with(['eventTemplate', 'startPlace'])
            ->orderByDesc('start_date')
            ->get();
    }
}
