<?php

declare(strict_types=1);

namespace App\Filament\Client\Pages;

use App\Filament\Client\Concerns\AuthorizesClientTrip;
use App\Filament\Client\Concerns\HasClientTripNav;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventPortalAccess;
use App\Services\ClientAccessService;
use App\Services\ContractExtrasSurchargeService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class ClientExtrasPage extends Page
{
    use AuthorizesClientTrip;
    use HasClientTripNav;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.client.pages.client-extras-page';

    protected static ?string $slug = 'extras/{event}';

    public Event $event;

    /** @var array<int, array<string, string>> participantId => [extraKey => value] */
    public array $selections = [];

    public function mount(Event $event): void
    {
        $this->authorizeClientTrip($event, requireFullAccess: true);
        $service = app(ClientAccessService::class);
        $user = Auth::user();
        abort_unless(
            $user && ($service->isParticipant($user, $event) || $service->isGuardian($user, $event)),
            403
        );

        $this->event = $event->loadMissing(['eventTemplate', 'startPlace']);
        $this->hydrateSelections();
    }

    public function getClientTripNavActiveTab(): ?string
    {
        return 'extras';
    }

    public function getTitle(): string|Htmlable
    {
        return $this->event->name;
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public static function urlFor(Event $event): string
    {
        return static::getUrl(['event' => $event->id], panel: 'portal');
    }

    /** @return list<array<string, mixed>> */
    public function getCatalogProperty(): array
    {
        $contract = $this->resolveContract();
        if (! $contract) {
            return [];
        }

        return app(ContractExtrasSurchargeService::class)->presentation($contract);
    }

    /** @return list<EventParticipant> */
    public function getParticipantsProperty(): array
    {
        $user = Auth::user();
        $service = app(ClientAccessService::class);

        if ($service->isGuardian($user, $this->event)) {
            return $service->guardianParticipantsQuery($user, $this->event)->get()->all();
        }

        $access = $service->accessFor($user, $this->event, EventPortalAccess::ROLE_PARTICIPANT);
        if ($access?->event_participant_id) {
            $p = EventParticipant::query()->find($access->event_participant_id);

            return $p ? [$p] : [];
        }

        return [];
    }

    public function getReadOnlyProperty(): bool
    {
        return app(ClientAccessService::class)->isPreviewReadOnly();
    }

    public function save(): void
    {
        app(ClientAccessService::class)->assertPortalMutationsAllowed();

        $allowedIds = collect($this->participants)->pluck('id')->all();
        $extras = app(ContractExtrasSurchargeService::class);

        foreach ($this->selections as $participantId => $values) {
            $participantId = (int) $participantId;
            if (! in_array($participantId, $allowedIds, true)) {
                continue;
            }
            $participant = EventParticipant::query()->find($participantId);
            if (! $participant) {
                continue;
            }

            $selected = is_array($values) ? $values : [];
            $diet = null;
            $other = [];
            foreach ($selected as $key => $value) {
                $value = trim((string) $value);
                if ($key === 'diet') {
                    $diet = $value !== '' ? $value : null;
                } else {
                    if ($value !== '') {
                        $other[$key] = $value;
                    }
                }
            }

            $payload = ['diet' => $diet];
            if (Schema::hasColumn('event_participants', 'selected_extras')) {
                $payload['selected_extras'] = $other !== [] ? $other : null;
            }
            $participant->forceFill($payload)->save();
            $extras->applyForParticipant($participant->fresh() ?? $participant);
        }

        $this->hydrateSelections();
        Notification::make()->title('Zapisano świadczenia')->success()->send();
    }

    private function hydrateSelections(): void
    {
        $this->selections = [];
        foreach ($this->participants as $participant) {
            $row = is_array($participant->selected_extras) ? $participant->selected_extras : [];
            if (filled($participant->diet)) {
                $row['diet'] = (string) $participant->diet;
            }
            $this->selections[$participant->id] = $row;
        }
    }

    private function resolveContract(): ?\App\Models\Contract
    {
        $user = Auth::user();
        $service = app(ClientAccessService::class);

        return $service->accessibleContract($user, $this->event)
            ?? $service->accessibleContract($user, $this->event, EventPortalAccess::ROLE_GUARDIAN)
            ?? $service->accessibleContract($user, $this->event, EventPortalAccess::ROLE_PARTICIPANT);
    }
}
