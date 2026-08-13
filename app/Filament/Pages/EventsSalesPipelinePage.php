<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Events\ChangeEventStatusAction;
use App\Data\ChangeEventStatusData;
use App\Filament\Actions\HelpArticleAction;
use App\Filament\Resources\EventResource;
use App\Models\Event;
use App\Support\AdminPanelUrls;
use App\Support\FilamentNavigation;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Kanoniczna tablica sprzedaży: zapytanie → oferta → rezerwacja wstępna.
 * Lista imprez nie duplikuje tego widoku — linkuje tutaj z headera.
 */
class EventsSalesPipelinePage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-view-columns';

    protected static ?string $navigationLabel = 'Ścieżka oferty';

    protected static ?string $title = 'Ścieżka oferty';

    protected static ?string $slug = 'sales-pipeline';

    protected static string $view = 'filament.pages.events-sales-pipeline';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return FilamentNavigation::GROUP_EVENTS;
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user !== null && $user->can('viewAny', Event::class);
    }

    public function getSubheading(): ?string
    {
        return 'Tablica sprzedaży — osobny widok od listy imprez';
    }

    protected function getHeaderActions(): array
    {
        return [
            HelpArticleAction::make('sciezka-imprezy'),
            Action::make('events_list')
                ->label('Lista imprez')
                ->icon('heroicon-o-queue-list')
                ->color('gray')
                ->url(EventResource::getUrl('index')),
        ];
    }

    /**
     * @return list<string>
     */
    public function pipelineStatuses(): array
    {
        return [
            Event::STATUS_INQUIRY,
            Event::STATUS_OFFER,
            Event::STATUS_PROVISIONAL_RESERVATION,
        ];
    }

    /**
     * @return Collection<string, Collection<int, Event>>
     */
    public function columns(): Collection
    {
        $statuses = $this->pipelineStatuses();

        $events = Event::query()
            ->with(['assignedUser', 'startPlace'])
            ->whereIn('status', $statuses)
            ->orderByDesc('updated_at')
            ->limit(300)
            ->get()
            ->groupBy('status');

        return collect($statuses)->mapWithKeys(
            fn (string $status) => [$status => $events->get($status, collect())]
        );
    }

    public function moveEvent(int $eventId, string $status): void
    {
        $event = Event::query()->findOrFail($eventId);
        abort_unless(Auth::user()?->can('update', $event), 403);

        if (! in_array($status, $this->pipelineStatuses(), true)
            && $status !== Event::STATUS_CONFIRMED
            && $status !== Event::STATUS_CANCELLED) {
            Notification::make()->title('Niedozwolony status na ścieżce oferty')->danger()->send();

            return;
        }

        try {
            app(ChangeEventStatusAction::class)(new ChangeEventStatusData(
                event: $event,
                status: $status,
                reason: 'Ścieżka oferty',
            ));
            Notification::make()->title('Zmieniono status')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Nie udało się zmienić statusu')->body($e->getMessage())->danger()->send();
        }
    }

    public function eventUrl(Event $event): string
    {
        return AdminPanelUrls::eventEdit($event);
    }

    public function statusLabel(string $status): string
    {
        return Event::getStatusOptions()[$status] ?? $status;
    }

    public function statusTooltip(string $status): string
    {
        return match ($status) {
            Event::STATUS_INQUIRY => 'Nowe zapytanie od klienta — jeszcze bez oferty.',
            Event::STATUS_OFFER => 'Oferta wysłana lub w przygotowaniu. Oczekiwanie na decyzję klienta.',
            Event::STATUS_PROVISIONAL_RESERVATION => 'Klient wstępnie zarezerwował termin. Przed potwierdzeniem sprawdź dostępność.',
            default => $this->statusLabel($status),
        };
    }
}
