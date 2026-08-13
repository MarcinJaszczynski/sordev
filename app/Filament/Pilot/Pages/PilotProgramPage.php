<?php

namespace App\Filament\Pilot\Pages;

use App\Filament\Pilot\Concerns\AuthorizesPilotTrip;
use App\Filament\Pilot\Concerns\HasPilotTripNav;
use App\Models\Event;
use App\Services\EventProgramPointOrderService;
use App\Services\PilotAccessService;
use App\Services\PilotProgramPointFinanceDisplay;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class PilotProgramPage extends Page
{
    use AuthorizesPilotTrip;
    use HasPilotTripNav;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pilot.pages.pilot-program-page';

    protected static ?string $slug = 'program/{event}';

    public Event $event;

    public function mount(Event $event): void
    {
        $this->authorizePilotTrip($event, requireFullAccess: true);

        $this->event = $event->load(['startPlace']);
    }

    public function getPilotTripNavActiveTab(): ?string
    {
        return 'program';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Program: '.$this->event->name;
    }

    public static function urlFor(Event $event): string
    {
        return static::getUrl(['event' => $event->id], panel: 'pilot');
    }

    protected function getViewData(): array
    {
        $points = app(EventProgramPointOrderService::class)->pilotProgramPoints($this->event);
        $programPointIds = $points->pluck('id')->map(fn ($id): int => (int) $id);

        return [
            'programPoints' => $points,
            'programPointIds' => $programPointIds,
            'financeHintsByPointId' => app(PilotProgramPointFinanceDisplay::class)->hintsForPoints($this->event, $points),
            'archiveMessage' => app(PilotAccessService::class)->archiveMessage($this->event),
        ];
    }
}
