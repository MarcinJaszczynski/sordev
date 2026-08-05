<?php

declare(strict_types=1);

namespace App\Filament\Client\Pages;

use App\Filament\Client\Concerns\AuthorizesClientTrip;
use App\Filament\Client\Concerns\HasClientTripNav;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Services\ClientAccessService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class ClientParticipantsPage extends Page
{
    use AuthorizesClientTrip;
    use HasClientTripNav;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.client.pages.client-participants-page';

    protected static ?string $slug = 'participants/{event}';

    public Event $event;

    public string $formFirstName = '';

    public string $formLastName = '';

    public string $formDiet = '';

    public bool $formParentConsent = false;

    public ?int $editId = null;

    public function mount(Event $event): void
    {
        $this->authorizeClientTrip($event, requireFullAccess: true);
        abort_unless(app(ClientAccessService::class)->isGuardian(Auth::user(), $event), 403);
        abort_unless(Schema::hasTable('event_participants'), 404);

        $this->event = $event;
    }

    public function getClientTripNavActiveTab(): ?string
    {
        return 'participants';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Uczestnicy: '.$this->event->name;
    }

    public static function urlFor(Event $event): string
    {
        return static::getUrl(['event' => $event->id], panel: 'portal');
    }

    /** @return array<int, EventParticipant> */
    public function getParticipantsProperty(): array
    {
        return EventParticipant::query()
            ->where('event_id', $this->event->id)
            ->where('status', EventParticipant::STATUS_ACTIVE)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->all();
    }

    public function startEdit(int $id): void
    {
        $p = EventParticipant::query()->where('event_id', $this->event->id)->findOrFail($id);
        $this->editId = $p->id;
        $this->formFirstName = (string) ($p->first_name ?? '');
        $this->formLastName = (string) ($p->last_name ?? '');
        $this->formDiet = (string) ($p->diet ?? '');
        $this->formParentConsent = $p->hasParentConsent();
    }

    public function cancelEdit(): void
    {
        $this->editId = null;
        $this->formFirstName = '';
        $this->formLastName = '';
        $this->formDiet = '';
        $this->formParentConsent = false;
    }

    public function save(): void
    {
        $this->validate([
            'formFirstName' => ['required_without:formLastName', 'nullable', 'string', 'max:120'],
            'formLastName' => ['nullable', 'string', 'max:120'],
            'formDiet' => ['nullable', 'string', 'max:255'],
        ]);

        $payload = [
            'first_name' => trim($this->formFirstName) ?: null,
            'last_name' => trim($this->formLastName) ?: null,
            'diet' => trim($this->formDiet) ?: null,
        ];

        if (Schema::hasColumn('event_participants', 'parent_consent_at')) {
            $payload['parent_consent_at'] = $this->formParentConsent ? now() : null;
            $payload['parent_consent_ip'] = $this->formParentConsent ? request()->ip() : null;
        }

        if ($this->editId) {
            EventParticipant::query()
                ->where('event_id', $this->event->id)
                ->findOrFail($this->editId)
                ->update($payload);
        } else {
            EventParticipant::query()->create([
                ...$payload,
                'event_id' => $this->event->id,
                'source' => EventParticipant::SOURCE_MANUAL,
                'status' => EventParticipant::STATUS_ACTIVE,
            ]);
        }

        $this->cancelEdit();
        Notification::make()->title('Zapisano')->success()->send();
    }

    public function delete(int $id): void
    {
        EventParticipant::query()
            ->where('event_id', $this->event->id)
            ->findOrFail($id)
            ->delete();

        Notification::make()->title('Usunięto')->success()->send();
    }
}
