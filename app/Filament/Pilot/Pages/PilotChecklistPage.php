<?php

namespace App\Filament\Pilot\Pages;

use App\Filament\Actions\HelpArticleAction;
use App\Filament\Pilot\Concerns\AuthorizesPilotTrip;
use App\Filament\Pilot\Concerns\HasPilotTripNav;
use App\Models\Event;
use App\Services\PilotAccessService;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class PilotChecklistPage extends Page
{
    use AuthorizesPilotTrip;
    use HasPilotTripNav;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pilot.pages.pilot-checklist-page';

    protected static ?string $slug = 'checklist/{event}';

    public Event $event;

    public function mount(Event $event): void
    {
        $this->authorizePilotTrip($event);

        $this->event = $event->load(['assignedUser']);
    }

    public function getPilotTripNavActiveTab(): ?string
    {
        return 'checklist';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Lista kontrolna: '.$this->event->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            HelpArticleAction::make('program-i-checklista', 'pilot'),
        ];
    }

    public static function urlFor(Event $event): string
    {
        return static::getUrl(['event' => $event->id], panel: 'pilot');
    }

    protected function getViewData(): array
    {
        return [
            'archiveMessage' => app(PilotAccessService::class)->archiveMessage($this->event),
            'readOnly' => ! app(PilotAccessService::class)->hasFullAccess($this->event, Auth::user()),
        ];
    }
}
