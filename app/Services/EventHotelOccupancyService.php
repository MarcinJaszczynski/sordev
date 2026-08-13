<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Facades\Schema;

/**
 * Read-model zajętości hotelowej imprezy (occupancy).
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
     *   required_beds_per_night: int,
     *   stays: list<array<string, mixed>>
     * }
     */
    public function forEvent(Event $event): array
    {
        $groupCounts = $this->hotelPlanService->resolveGroupCounts($event);
        $participants = max(0, (int) $groupCounts['qty']);
        $gratis = max(0, (int) $groupCounts['gratis']);
        $staff = max(0, (int) $groupCounts['staff']);
        $drivers = max(0, (int) $groupCounts['driver']);
        $pilot = $event->assigned_to ? 1 : 0;
        $requiredBedsPerNight = $participants + $gratis + $staff + $drivers + $pilot;

        if (! Schema::hasTable('event_hotel_stays')) {
            return [
                'participants' => $participants,
                'gratis' => $gratis,
                'staff' => $staff,
                'drivers' => $drivers,
                'pilot' => $pilot,
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
            'staff' => $staff,
            'drivers' => $drivers,
            'pilot' => $pilot,
            'required_beds_per_night' => $requiredBedsPerNight,
            'stays' => $stays,
        ];
    }
}
