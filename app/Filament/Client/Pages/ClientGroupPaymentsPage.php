<?php

namespace App\Filament\Client\Pages;

use App\Filament\Client\Concerns\AuthorizesClientTrip;
use App\Filament\Client\Concerns\HasClientTripNav;
use App\Models\Event;
use App\Services\ClientAccessService;
use App\Services\ClientGroupPaymentsService;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class ClientGroupPaymentsPage extends Page
{
    use AuthorizesClientTrip;
    use HasClientTripNav;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.client.pages.client-group-payments-page';

    protected static ?string $slug = 'group-payments/{event}';

    public Event $event;

    public function mount(Event $event): void
    {
        $this->authorizeClientTrip($event, requireFullAccess: true);

        abort_unless(app(ClientAccessService::class)->isGuardian(Auth::user(), $event), 403);

        $this->event = $event;
    }

    public function getClientTripNavActiveTab(): ?string
    {
        return 'group_payments';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Wpłaty grupy: '.$this->event->name;
    }

    public static function urlFor(Event $event): string
    {
        return static::getUrl(['event' => $event->id], panel: 'portal');
    }

    protected function getViewData(): array
    {
        $user = auth()->user();
        $service = app(ClientGroupPaymentsService::class);

        return [
            'event' => $this->event->loadMissing(['eventTemplate', 'startPlace']),
            'rows' => $service->rowsFor($user, $this->event),
            'summary' => $service->summaryFor($user, $this->event),
            'archiveMessage' => app(ClientAccessService::class)->archiveMessage($this->event),
        ];
    }
}
