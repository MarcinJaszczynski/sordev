<?php

declare(strict_types=1);

namespace App\Filament\Pilot\Pages;

use App\Actions\Events\MarkAttendanceAction;
use App\Data\MarkAttendanceData;
use App\Filament\Pilot\Concerns\AuthorizesPilotTrip;
use App\Filament\Pilot\Concerns\HasPilotTripNav;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\EventParticipant;
use App\Services\PilotAccessService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class PilotAttendancePage extends Page
{
    use AuthorizesPilotTrip;
    use HasPilotTripNav;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pilot.pages.pilot-attendance-page';

    protected static ?string $slug = 'attendance/{event}';

    public Event $event;

    public int $day = 1;

    /** @var array<int|string, bool> */
    public array $statuses = [];

    public function mount(Event $event): void
    {
        abort_unless($event->showsPilotAttendance(), 404);

        $this->authorizePilotTrip($event);
        $this->event = $event;
        $this->day = 1;
        $this->loadStatuses();
    }

    public function updatedDay(): void
    {
        $this->loadStatuses();
    }

    public function getPilotTripNavActiveTab(): ?string
    {
        return 'attendance';
    }

    public function getTitle(): string|Htmlable
    {
        return $this->event->name;
    }

    public static function urlFor(Event $event): string
    {
        return static::getUrl(['event' => $event->id], panel: 'pilot');
    }

    public function getMaxDayProperty(): int
    {
        return max(1, (int) ($this->event->duration_days ?? 1));
    }

    /** @return array<int, EventParticipant> */
    public function getParticipantsProperty(): array
    {
        if (! Schema::hasTable('event_participants')) {
            return [];
        }

        return EventParticipant::query()
            ->where('event_id', $this->event->id)
            ->where('status', EventParticipant::STATUS_ACTIVE)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->all();
    }

    public function save(): void
    {
        abort_unless(app(PilotAccessService::class)->hasFullAccess($this->event, Auth::user()), 403);

        $statuses = [];
        foreach ($this->participants as $participant) {
            $checked = (bool) ($this->statuses[$participant->id] ?? false);
            $statuses[$participant->id] = $checked
                ? EventAttendance::STATUS_PRESENT
                : EventAttendance::STATUS_ABSENT;
        }

        app(MarkAttendanceAction::class)(new MarkAttendanceData(
            event: $this->event,
            day: $this->day,
            statuses: $statuses,
            markedBy: Auth::id(),
        ));

        Notification::make()->title('Zapisano obecność')->success()->send();
    }

    protected function loadStatuses(): void
    {
        $this->statuses = [];

        foreach ($this->participants as $participant) {
            $this->statuses[$participant->id] = false;
        }

        if (! Schema::hasTable('event_attendances')) {
            return;
        }

        EventAttendance::query()
            ->where('event_id', $this->event->id)
            ->where('day', $this->day)
            ->get()
            ->each(function (EventAttendance $row): void {
                $this->statuses[$row->event_participant_id] = $row->status === EventAttendance::STATUS_PRESENT;
            });
    }

    protected function getViewData(): array
    {
        return [
            'archiveMessage' => app(PilotAccessService::class)->archiveMessage($this->event),
            'readOnly' => ! app(PilotAccessService::class)->hasFullAccess($this->event, Auth::user()),
        ];
    }
}
