<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Facades\Schema;

/**
 * Read-model zajętości hotelowej imprezy (occupancy).
 *
 * Pilot i obsługa to ta sama pula miejsc — używamy {@see EventHotelPlanService::resolveAllocationGroupCounts()}
 * (tak jak alokacja pokoi ze szablonu), zamiast doliczać pilota osobno do staff z wariantu qty.
 */
final class EventHotelOccupancyService
{
    public function __construct(
        private readonly EventHotelPlanService $hotelPlanService,
    ) {}

    /**
     * @return array{
     *   participants: int,
     *   gratis: int,
     *   staff: int,
     *   drivers: int,
     *   pilot: int,
     *   pilot_staff: int,
     *   required_beds_per_night: int,
     *   stays: list<array<string, mixed>>
     * }
     */
    public function forEvent(Event $event): array
    {
        $groupCounts = $this->hotelPlanService->resolveGroupCounts($event);
        $allocationCounts = $this->hotelPlanService->resolveAllocationGroupCounts($event);

        $participants = max(0, (int) $groupCounts['qty']);
        $gratis = max(0, (int) $groupCounts['gratis']);
        $drivers = max(0, (int) $allocationCounts['driver']);
        // Jedna pula: obsługa z wariantu + ewentualnie assigned pilot (jak w alokacji pokoi).
        $pilotStaff = max(0, (int) $allocationCounts['staff']);
        $requiredBedsPerNight = $participants + $gratis + $pilotStaff + $drivers;

        if (! Schema::hasTable('event_hotel_stays')) {
            return [
                'participants' => $participants,
                'gratis' => $gratis,
                'staff' => $pilotStaff,
                'drivers' => $drivers,
                'pilot' => $pilotStaff,
                'pilot_staff' => $pilotStaff,
                'required_beds_per_night' => $requiredBedsPerNight,
                'stays' => [],
            ];
        }

        $event->loadMissing(['hotelStays.roomLines.occupants', 'hotelStays.roomLines.hotelRoom', 'hotelStays.contractor']);

        $stays = [];

        foreach ($event->hotelStays as $stay) {
            $stayBeds = 0;
            $stayAssigned = 0;

            foreach ($stay->roomLines as $line) {
                $peoplePerRoom = $line->effectivePeopleCount();
                $qty = max(1, (int) ($line->quantity ?? 1));
                $lineBeds = $peoplePerRoom * $qty;
                $lineAssigned = $line->occupants->count();

                $stayBeds += $lineBeds;
                $stayAssigned += $lineAssigned;
            }

            $dayDate = $event->dateForProgramDay((int) ($stay->day ?? 1));

            $stays[] = [
                'id' => $stay->id,
                'label' => $stay->contractor?->name ?? ('Nocleg dzień '.($stay->day ?? '?')),
                'day' => $stay->day,
                'date_from' => $dayDate?->format('d.m.Y'),
                'date_to' => $dayDate?->copy()->addDay()->format('d.m.Y'),
                'beds' => $stayBeds,
                'assigned' => $stayAssigned,
                'free' => max(0, $stayBeds - $stayAssigned),
                'percent' => $stayBeds > 0 ? round(100 * $stayAssigned / $stayBeds, 1) : 0.0,
            ];
        }

        return [
            'participants' => $participants,
            'gratis' => $gratis,
            'staff' => $pilotStaff,
            'drivers' => $drivers,
            'pilot' => $pilotStaff,
            'pilot_staff' => $pilotStaff,
            'required_beds_per_night' => $requiredBedsPerNight,
            'stays' => $stays,
        ];
    }
}
