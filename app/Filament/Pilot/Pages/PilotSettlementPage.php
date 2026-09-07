<?php

namespace App\Filament\Pilot\Pages;

use App\Filament\Actions\HelpArticleAction;
use App\Filament\Pilot\Concerns\AuthorizesPilotTrip;
use App\Filament\Pilot\Concerns\HasPilotTripNav;
use App\Models\Event;
use App\Services\PilotAccessService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class PilotSettlementPage extends Page
{
    use AuthorizesPilotTrip;
    use HasPilotTripNav;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pilot.pages.pilot-settlement-page';

    protected static ?string $slug = 'settlement/{event}';

    public Event $event;

    public function mount(Event $event): void
    {
        $this->authorizePilotTrip($event, requireFullAccess: true);

        $this->event = $event->load(['activeSettlement', 'assignedUser']);
    }

    public function getPilotTripNavActiveTab(): ?string
    {
        return 'settlement';
    }

    public function getTitle(): string|Htmlable
    {
        return $this->event->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            HelpArticleAction::make('zaliczka-i-rozliczenie', 'pilot'),
            Action::make('folder')
                ->label('Teczka PDF')
                ->icon('heroicon-o-folder-open')
                ->url(route('pilot.events.pdf', ['event' => $this->event, 'audience' => 'folder']))
                ->openUrlInNewTab(),
        ];
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole('pilot')) {
            return true;
        }

        return app(PilotAccessService::class)->canStaffPreviewPortal($user);
    }

    public static function settleUrl(Event|int $event, bool $isAbsolute = true): string
    {
        $eventId = $event instanceof Event ? $event->id : $event;

        return static::getUrl(['event' => $eventId], $isAbsolute, panel: 'pilot');
    }
}
