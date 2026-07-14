<?php

namespace App\Filament\Client\Pages;

use App\Filament\Client\Concerns\AuthorizesClientTrip;
use App\Filament\Client\Concerns\HasClientTripNav;
use App\Models\Event;
use App\Models\EventPortalAccess;
use App\Services\ClientAccessService;
use App\Services\ContractGroupPricingService;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class ClientPaymentSchedulePage extends Page
{
    use AuthorizesClientTrip;
    use HasClientTripNav;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.client.pages.client-payment-schedule-page';

    protected static ?string $slug = 'payment-schedule/{event}';

    public Event $event;

    public function mount(Event $event): void
    {
        $this->authorizeClientTrip($event, requireFullAccess: true);

        $this->event = $event;
    }

    public function getClientTripNavActiveTab(): ?string
    {
        return 'payment_schedule';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Harmonogram płatności: '.$this->event->name;
    }

    public static function urlFor(Event $event): string
    {
        return static::getUrl(['event' => $event->id], panel: 'portal');
    }

    protected function getViewData(): array
    {
        $user = auth()->user();
        $service = app(ClientAccessService::class);
        $role = $service->isGuardian($user, $this->event)
            ? EventPortalAccess::ROLE_GUARDIAN
            : EventPortalAccess::ROLE_PARTICIPANT;
        $contract = $service->accessibleContract($user, $this->event, $role);
        $presentation = $contract
            ? app(ContractGroupPricingService::class)->presentationFor($contract)
            : null;

        return [
            'event' => $this->event,
            'contract' => $contract,
            'presentation' => $presentation,
            'archiveMessage' => app(ClientAccessService::class)->archiveMessage($this->event),
        ];
    }
}
