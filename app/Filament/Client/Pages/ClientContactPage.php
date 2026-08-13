<?php

declare(strict_types=1);

namespace App\Filament\Client\Pages;

use App\Actions\Crm\CreateClientTripInquiryAction;
use App\Data\CreateClientTripInquiryData;
use App\Filament\Client\Concerns\AuthorizesClientTrip;
use App\Filament\Client\Concerns\HasClientTripNav;
use App\Models\ClientTripInquiry;
use App\Models\Event;
use App\Models\EventPortalAccess;
use App\Services\ClientAccessService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class ClientContactPage extends Page
{
    use AuthorizesClientTrip;
    use HasClientTripNav;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.client.pages.client-contact-page';

    protected static ?string $slug = 'contact/{event}';

    public Event $event;

    public string $formSubject = '';

    public string $formBody = '';

    public function mount(Event $event): void
    {
        $this->authorizeClientTrip($event, requireFullAccess: true);
        abort_unless(Schema::hasTable('client_trip_inquiries'), 404);

        $service = app(ClientAccessService::class);
        $user = Auth::user();
        abort_unless(
            $user && ($service->isParticipant($user, $event) || $service->isGuardian($user, $event)),
            403
        );

        $this->event = $event->loadMissing(['eventTemplate', 'startPlace']);
    }

    public function getClientTripNavActiveTab(): ?string
    {
        return 'contact';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Kontakt: '.$this->event->name;
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public static function urlFor(Event $event): string
    {
        return static::getUrl(['event' => $event->id], panel: 'portal');
    }

    /** @return list<ClientTripInquiry> */
    public function getInquiriesProperty(): array
    {
        $user = Auth::user();
        $access = app(ClientAccessService::class)->previewAccessForEvent($this->event);
        $userId = $access?->user_id ?? $user?->id;

        if (! $userId) {
            return [];
        }

        return ClientTripInquiry::query()
            ->where('event_id', $this->event->id)
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->get()
            ->all();
    }

    public function getReadOnlyProperty(): bool
    {
        return app(ClientAccessService::class)->isPreviewReadOnly();
    }

    public function send(): void
    {
        app(ClientAccessService::class)->assertPortalMutationsAllowed();

        $this->validate([
            'formSubject' => ['required', 'string', 'max:255'],
            'formBody' => ['required', 'string', 'max:5000'],
        ]);

        $user = Auth::user();
        abort_unless($user, 403);

        $service = app(ClientAccessService::class);
        $access = $service->accessFor($user, $this->event);
        $contract = $service->accessibleContract($user, $this->event)
            ?? $service->accessibleContract($user, $this->event, EventPortalAccess::ROLE_GUARDIAN)
            ?? $service->accessibleContract($user, $this->event, EventPortalAccess::ROLE_PARTICIPANT);

        // W preview identity pochodzi z access — zapis i tak zablokowany; tu user_id = klient z accessu gdybyśmy odblokowali.
        $clientUser = $access?->user ?? $user;

        app(CreateClientTripInquiryAction::class)(new CreateClientTripInquiryData(
            event: $this->event,
            user: $clientUser,
            subject: $this->formSubject,
            body: $this->formBody,
            contract: $contract,
            portalAccess: $access,
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
