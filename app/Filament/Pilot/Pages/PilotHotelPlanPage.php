<?php

namespace App\Filament\Pilot\Pages;

use App\Filament\Pilot\Concerns\AuthorizesPilotTrip;
use App\Filament\Pilot\Concerns\HasPilotTripNav;
use App\Models\Event;
use App\Services\EventHotelPlanService;
use App\Services\PilotAccessService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class PilotHotelPlanPage extends Page
{
    use AuthorizesPilotTrip;
    use HasPilotTripNav;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pilot.pages.pilot-hotel-plan-page';

    protected static ?string $slug = 'hotel-plan/{event}';

    public Event $event;

    /** @var array<int, array<string, mixed>> */
    public array $plan = [];

    /** @var array<int, string> */
    public array $roomNumbers = [];

    /** @var array<string, string> unit_id.bed_index => name */
    public array $occupantNames = [];

    public function mount(Event $event): void
    {
        $this->authorizePilotTrip($event, requireFullAccess: true);

        $this->event = $event->load(['eventTemplate']);
        app(EventHotelPlanService::class)->ensureStaysForEvent($this->event);
        $this->refreshPlan();
    }

    public function getPilotTripNavActiveTab(): ?string
    {
        return 'hotel';
    }

    public function getTitle(): string|Htmlable
    {
        return $this->event->name;
    }

    public function refreshPlan(): void
    {
        app(EventHotelPlanService::class)->syncAllRoomUnitsForEvent($this->event);
        $this->plan = app(EventHotelPlanService::class)
            ->buildHotelPlanForPdf($this->event->fresh())
            ->values()
            ->all();

        $this->roomNumbers = [];
        $this->occupantNames = [];
        foreach ($this->plan as $night) {
            foreach (['qty', 'gratis', 'staff', 'driver'] as $role) {
                foreach ($night[$role] ?? [] as $line) {
                    foreach ($line['units'] ?? [] as $unit) {
                        if (! empty($unit['id'])) {
                            $unitId = (int) $unit['id'];
                            $this->roomNumbers[$unitId] = (string) ($unit['room_number'] ?? '');

                            foreach ($unit['occupant_slots'] ?? [] as $slot) {
                                $bedIndex = (int) ($slot['bed_index'] ?? 1);
                                $slotKey = "{$unitId}.{$bedIndex}";
                                $this->occupantNames[$slotKey] = (string) ($slot['name'] ?? '');
                            }
                        }
                    }
                }
            }
        }
    }

    public function saveRoomNumbers(): void
    {
        abort_unless($this->canEditRoomNumbers(), 403);

        $service = app(EventHotelPlanService::class);
        $service->updateUnitRoomNumbers($this->event, $this->roomNumbers);
        $service->updateUnitOccupantNames($this->event, $this->occupantNames);
        $this->refreshPlan();

        Notification::make()
            ->title('Numery pokoi zapisane')
            ->success()
            ->send();
    }

    public function canEditRoomNumbers(): bool
    {
        if (app(PilotAccessService::class)->isPreviewReadOnly()) {
            return false;
        }

        $user = Auth::user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole(['admin', 'super_admin'])) {
            return true;
        }

        return $user->hasRole('pilot') && (int) $this->event->assigned_to === (int) $user->id;
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user && ($user->hasRole(['admin', 'super_admin', 'pilot']));
    }

    public static function urlFor(Event $event, bool $isAbsolute = true): string
    {
        return static::getUrl(['event' => $event->id], $isAbsolute, panel: 'pilot');
    }
}
