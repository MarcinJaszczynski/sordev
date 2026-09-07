<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\Event;
use App\Models\EventHotelRoomLine;
use App\Models\EventHotelStay;
use App\Models\EventQty;
use App\Models\HotelRoom;
use App\Services\EventHotelOccupancyService;
use App\Support\EventHotelBedCapacity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventHotelBedCapacityTest extends TestCase
{
    use RefreshDatabase;

    public function test_exceeds_only_when_required_over_planned_beds(): void
    {
        $this->assertFalse(EventHotelBedCapacity::exceeds(38, 40));
        $this->assertFalse(EventHotelBedCapacity::exceeds(38, 38));
        $this->assertTrue(EventHotelBedCapacity::exceeds(38, 33));
        $this->assertFalse(EventHotelBedCapacity::exceeds(38, 0));
        $this->assertFalse(EventHotelBedCapacity::exceeds(0, 10));
    }

    public function test_message_describes_shortage_for_night(): void
    {
        $message = EventHotelBedCapacity::message(48, 40, 2, 'Hotel Test');

        $this->assertNotNull($message);
        $this->assertStringContainsString('noc 2', $message);
        $this->assertStringContainsString('Hotel Test', $message);
        $this->assertStringContainsString('zaplanowano 40 miejsc', $message);
        $this->assertStringContainsString('grupa potrzebuje 48', $message);
        $this->assertStringContainsString('Brakuje 8 miejsc', $message);
    }

    public function test_analyze_occupancy_flags_deficient_stays(): void
    {
        $event = Event::factory()->create([
            'participant_count' => 33,
            'duration_days' => 3,
        ]);

        EventQty::create([
            'event_id' => $event->id,
            'qty' => 33,
            'gratis' => 4,
            'staff' => 0,
            'driver' => 1,
        ]);

        $room = HotelRoom::create([
            'name' => 'Trzyosobowy',
            'people_count' => 3,
            'price' => 300,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);

        foreach ([1, 2] as $day) {
            $stay = EventHotelStay::create([
                'event_id' => $event->id,
                'day' => $day,
            ]);

            EventHotelRoomLine::create([
                'event_hotel_stay_id' => $stay->id,
                'hotel_room_id' => $room->id,
                'role' => 'qty',
                'quantity' => 11,
                'people_count' => 3,
                'unit_price' => 300,
                'convert_to_pln' => true,
                'order' => 0,
            ]);
        }

        $occupancy = app(EventHotelOccupancyService::class)->forEvent($event->fresh());
        $analysis = EventHotelBedCapacity::analyzeOccupancy($occupancy);

        $this->assertSame(38, $analysis['required']);
        $this->assertSame(33, $analysis['min_beds']);
        $this->assertTrue($analysis['has_deficiency']);
        $this->assertCount(2, $analysis['deficient_stays']);
        $this->assertSame(5, $analysis['deficient_stays'][0]['shortage']);
    }

    public function test_analyze_occupancy_ignores_nights_without_room_lines(): void
    {
        $event = Event::factory()->create(['participant_count' => 20]);

        EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 1,
        ]);

        $occupancy = app(EventHotelOccupancyService::class)->forEvent($event->fresh());
        $analysis = EventHotelBedCapacity::analyzeOccupancy($occupancy);

        $this->assertFalse($analysis['has_deficiency']);
        $this->assertNull($analysis['min_beds']);
        $this->assertSame([], $analysis['deficient_stays']);
    }
}
