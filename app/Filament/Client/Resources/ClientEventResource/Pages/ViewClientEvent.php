<?php

namespace App\Filament\Client\Resources\ClientEventResource\Pages;

use App\Filament\Client\Concerns\HasClientTripNav;
use App\Filament\Client\Pages\ClientContactPage;
use App\Filament\Client\Resources\ClientEventResource;
use App\Services\ClientTripReadinessService;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class ViewClientEvent extends ViewRecord
{
    use HasClientTripNav;

    protected static string $resource = ClientEventResource::class;

    protected static string $view = 'filament.client.resources.client-event-resource.pages.view-client-event';

    public function getClientTripNavActiveTab(): ?string
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

    /**
     * @return array{readiness: list<array<string, mixed>>, contactUrl: ?string}
     */
    protected function getViewData(): array
    {
        $user = Auth::user();
        $readiness = $user
            ? app(ClientTripReadinessService::class)->items($user, $this->record)
            : [];

        return [
            'readiness' => $readiness,
            'contactUrl' => Schema::hasTable('client_trip_inquiries') && $user
                ? ClientContactPage::urlFor($this->record)
                : null,
        ];
    }
}
