<?php

namespace App\Filament\Client\Pages;

use App\Filament\Client\Concerns\AuthorizesClientTrip;
use App\Filament\Client\Concerns\HasClientTripNav;
use App\Models\Event;
use App\Services\ClientAccessService;
use App\Services\EventProgramPointOrderService;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class ClientProgramPage extends Page
{
    use AuthorizesClientTrip;
    use HasClientTripNav;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.client.pages.client-program-page';

    protected static ?string $slug = 'program/{event}';

    public Event $event;

    public function mount(Event $event): void
    {
        $this->authorizeClientTrip($event, requireFullAccess: true);

        $this->event = $event->load(['startPlace']);
    }

    public function getClientTripNavActiveTab(): ?string
    {
        return 'program';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Program: '.$this->event->name;
    }

    public static function urlFor(Event $event): string
    {
        return static::getUrl(['event' => $event->id], panel: 'portal');
    }

    protected function getViewData(): array
    {
        $points = app(EventProgramPointOrderService::class)->clientProgramPoints($this->event);

        return [
            'event' => $this->event->loadMissing(['eventTemplate', 'startPlace']),
            'programPoints' => $points,
            'coverUrl' => \App\Support\ClientPortalMedia::coverUrl($this->event),
            'archiveMessage' => app(ClientAccessService::class)->archiveMessage($this->event),
        ];
    }
}
