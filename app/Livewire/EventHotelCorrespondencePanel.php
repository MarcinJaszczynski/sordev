<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\HotelCorrespondenceLog;
use App\Services\HotelCorrespondenceService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class EventHotelCorrespondencePanel extends Component
{
    use WithFileUploads;

    public int $eventId;

    public string $direction = HotelCorrespondenceLog::DIRECTION_OUTBOUND;

    public ?int $contractorId = null;

    public ?int $eventHotelStayId = null;

    public string $subject = '';

    public string $body = '';

    public string $contactPerson = '';

    public string $contactedAt = '';

    public $attachment = null;

    public function mount(int $eventId): void
    {
        abort_unless(Auth::check(), 403);

        $this->eventId = $eventId;
        $this->contactedAt = now()->format('Y-m-d\\TH:i');
    }

    public function save(): void
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $this->validate([
            'direction' => 'required|in:'.implode(',', array_keys(HotelCorrespondenceLog::$directions)),
            'subject' => 'nullable|string|max:255',
            'body' => 'nullable|string|max:5000',
            'contactPerson' => 'nullable|string|max:255',
            'contactedAt' => 'required|date',
            'attachment' => 'nullable|file|max:10240',
        ]);

        if (trim($this->subject) === '' && trim($this->body) === '') {
            Notification::make()
                ->title('Uzupełnij temat lub treść wpisu')
                ->warning()
                ->send();

            return;
        }

        $event = Event::query()->findOrFail($this->eventId);

        app(HotelCorrespondenceService::class)->create($event, $user, [
            'direction' => $this->direction,
            'contractor_id' => $this->contractorId,
            'event_hotel_stay_id' => $this->eventHotelStayId,
            'subject' => $this->subject,
            'body' => $this->body,
            'contact_person' => $this->contactPerson,
            'contacted_at' => $this->contactedAt,
            'attachment' => $this->attachment,
        ]);

        $this->reset(['subject', 'body', 'contactPerson', 'attachment']);
        $this->contactedAt = now()->format('Y-m-d\\TH:i');

        Notification::make()
            ->title('Zapisano wpis korespondencji')
            ->success()
            ->send();
    }

    public function render()
    {
        $event = Event::query()
            ->with(['hotelStays.contractor'])
            ->findOrFail($this->eventId);

        $service = app(HotelCorrespondenceService::class);
        $logs = $service->logsForEvent($event);

        $stayOptions = $event->hotelStays
            ->mapWithKeys(fn ($stay) => [
                $stay->id => sprintf(
                    'Noc %d — %s',
                    (int) $stay->day,
                    $stay->contractor?->name ?? 'bez hotelu',
                ),
            ])
            ->all();

        $contractorOptions = $event->hotelStays
            ->pluck('contractor')
            ->filter()
            ->unique('id')
            ->mapWithKeys(fn ($c) => [$c->id => $c->name])
            ->all();

        return view('livewire.event-hotel-correspondence-panel', [
            'logs' => $logs,
            'stayOptions' => $stayOptions,
            'contractorOptions' => $contractorOptions,
            'directions' => HotelCorrespondenceLog::$directions,
            'service' => $service,
        ]);
    }
}
