<?php

namespace App\Filament\Pilot\Pages;

use App\Filament\Pilot\Concerns\AuthorizesPilotTrip;
use App\Filament\Pilot\Concerns\HasPilotTripNav;
use App\Models\Event;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Hub dokumentów pilota — PDF otwierane świadomie (nie bezpośredni skok z trip-nav).
 */
class PilotDocumentsPage extends Page
{
    use AuthorizesPilotTrip;
    use HasPilotTripNav;

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-down';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pilot.pages.pilot-documents-page';

    protected static ?string $slug = 'documents/{event}';

    public Event $event;

    public function mount(Event $event): void
    {
        $this->authorizePilotTrip($event, requireFullAccess: true);

        $this->event = $event;
    }

    public function getPilotTripNavActiveTab(): ?string
    {
        return 'documents';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Dokumenty: '.$this->event->name;
    }

    public function getSubheading(): ?string
    {
        return 'Pobierz PDF teczki wycieczki lub pakietu pilota';
    }

    public static function urlFor(Event $event): string
    {
        return static::getUrl(['event' => $event->id], panel: 'pilot');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('folder_pdf')
                ->label('Teczka PDF')
                ->icon('heroicon-o-folder')
                ->url(route('pilot.events.pdf', ['event' => $this->event, 'audience' => 'folder']))
                ->openUrlInNewTab(),
            Action::make('pilot_pdf')
                ->label('Pakiet pilota PDF')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(route('pilot.events.pdf', ['event' => $this->event, 'audience' => 'pilot']))
                ->openUrlInNewTab(),
        ];
    }
}
