<?php

namespace App\Filament\Pilot\Pages;

use App\Filament\Pilot\Concerns\AuthorizesPilotTrip;
use App\Filament\Pilot\Concerns\HasPilotTripNav;
use App\Models\Event;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

/**
 * @deprecated Bookmark /advance/{event} → PilotSettlementPage (jedna zakładka gotówka+rozliczenie).
 */
class PilotAdvancePage extends Page
{
    use AuthorizesPilotTrip;
    use HasPilotTripNav;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pilot.pages.pilot-advance-page';

    protected static ?string $slug = 'advance/{event}';

    public Event $event;

    public function mount(Event $event): void
    {
        $this->authorizePilotTrip($event, requireFullAccess: true);

        $this->redirect(PilotSettlementPage::settleUrl($event), navigate: false);
    }

    public function getPilotTripNavActiveTab(): ?string
    {
        return 'settlement';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Gotówka i rozliczenie: '.$this->event->name;
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

        return $user->hasRole(['admin', 'super_admin']) && \App\Http\Middleware\PilotPreviewMiddleware::isActive();
    }

    public static function urlFor(Event $event): string
    {
        // Jedna zakładka — stare linki „zaliczka” prowadzą do rozliczenia.
        return PilotSettlementPage::settleUrl($event);
    }
}
