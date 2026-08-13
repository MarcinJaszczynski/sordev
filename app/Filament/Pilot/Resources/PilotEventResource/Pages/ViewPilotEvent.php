<?php

namespace App\Filament\Pilot\Resources\PilotEventResource\Pages;

use App\Filament\Pilot\Concerns\HasPilotTripNav;
use App\Filament\Pilot\Resources\PilotEventResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewPilotEvent extends ViewRecord
{
    use HasPilotTripNav;

    protected static string $resource = PilotEventResource::class;

    protected static string $view = 'filament.pilot.resources.pilot-event-resource.pages.view-pilot-event';

    public function getPilotTripNavActiveTab(): ?string
    {
        return 'info';
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->name;
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->load(['startPlace', 'eventTemplate']);

        return $data;
    }
}
