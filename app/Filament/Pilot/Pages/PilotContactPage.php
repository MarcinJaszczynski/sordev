<?php

declare(strict_types=1);

namespace App\Filament\Pilot\Pages;

use App\Actions\Crm\CreateClientTripInquiryAction;
use App\Data\CreateClientTripInquiryData;
use App\Filament\Pilot\Concerns\AuthorizesPilotTrip;
use App\Filament\Pilot\Concerns\HasPilotTripNav;
use App\Models\ClientTripInquiry;
use App\Models\Event;
use App\Services\PilotAccessService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class PilotContactPage extends Page
{
    use AuthorizesPilotTrip;
    use HasPilotTripNav;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pilot.pages.pilot-contact-page';

    protected static ?string $slug = 'contact/{event}';

    public Event $event;

    public string $formSubject = '';

    public string $formBody = '';

    public function mount(Event $event): void
    {
        $this->authorizePilotTrip($event, requireFullAccess: true);
        abort_unless(Schema::hasTable('client_trip_inquiries'), 404);

        $this->event = $event->loadMissing(['eventTemplate', 'startPlace']);
    }

    public function getPilotTripNavActiveTab(): ?string
    {
        return 'contact';
    }

    public function getTitle(): string|Htmlable
    {
        return $this->event->name;
    }

    public static function urlFor(Event $event): string
    {
        return static::getUrl(['event' => $event->id], panel: 'pilot');
    }

    /** @return list<ClientTripInquiry> */
    public function getInquiriesProperty(): array
    {
        $service = app(PilotAccessService::class);
        $userId = $service->previewPilotUser()?->id ?? Auth::id();

        if (! $userId) {
            return [];
        }

        return ClientTripInquiry::query()
            ->where('event_id', $this->event->id)
            ->where('user_id', $userId)
            ->when(
                Schema::hasColumn('client_trip_inquiries', 'source'),
                fn ($q) => $q->where('source', ClientTripInquiry::SOURCE_PILOT),
            )
            ->orderByDesc('id')
            ->get()
            ->all();
    }

    public function getReadOnlyProperty(): bool
    {
        return app(PilotAccessService::class)->isPreviewReadOnly();
    }

    public function send(): void
    {
        app(PilotAccessService::class)->assertPilotMutationsAllowed();

        $this->validate([
            'formSubject' => ['required', 'string', 'max:255'],
            'formBody' => ['required', 'string', 'max:5000'],
        ]);

        $user = app(PilotAccessService::class)->previewPilotUser() ?? Auth::user();
        abort_unless($user, 403);

        app(CreateClientTripInquiryAction::class)(new CreateClientTripInquiryData(
            event: $this->event,
            user: $user,
            subject: $this->formSubject,
            body: $this->formBody,
            source: ClientTripInquiry::SOURCE_PILOT,
        ));

        $this->formSubject = '';
        $this->formBody = '';

        Notification::make()
            ->title('Zapytanie wysłane')
            ->body('Biuro otrzymało Twoją wiadomość.')
            ->success()
            ->send();
    }
}
