<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Event;
use App\Models\EventHotelRoomLine;
use App\Models\EventHotelStay;
use App\Models\EventQty;
use App\Models\HotelRoom;
use App\Models\User;
use App\Services\EventHotelOccupancyService;
use App\Services\Invoices\KsefOutboundService;
use App\Services\Sms\LogSmsChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetitiveFeaturesScaffoldingTest extends TestCase
{
    use RefreshDatabase;

    public function test_hotel_occupancy_returns_empty_summary_without_stays(): void
    {
        $event = Event::factory()->create([
            'participant_count' => 20,
        ]);

        $summary = app(EventHotelOccupancyService::class)->forEvent($event);

        $this->assertSame(20, $summary['participants']);
        $this->assertSame(20, $summary['required_beds_per_night']);
        $this->assertSame([], $summary['stays']);
    }

    public function test_hotel_occupancy_summary_uses_people_per_night_not_beds_times_days(): void
    {
        $pilot = User::factory()->create();
        $event = Event::factory()->create([
            'participant_count' => 33,
            'duration_days' => 4,
            'assigned_to' => $pilot->id,
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

        foreach ([1, 2, 3, 4] as $day) {
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

        $summary = app(EventHotelOccupancyService::class)->forEvent($event->fresh());

        $this->assertSame(33, $summary['participants']);
        $this->assertSame(4, $summary['gratis']);
        $this->assertSame(1, $summary['drivers']);
        // staff=0 w qty — przypisany pilot nie dokłada miejsca; obsługa tylko z wariantu.
        $this->assertSame(0, $summary['pilot_staff']);
        $this->assertSame(0, $summary['staff']);
        $this->assertSame(38, $summary['required_beds_per_night']);
        $this->assertCount(4, $summary['stays']);
        $this->assertSame(33, $summary['stays'][0]['beds']);
        $this->assertSame(33, $summary['stays'][1]['beds']);
        $this->assertArrayNotHasKey('beds', $summary);
        $this->assertArrayNotHasKey('occupancy_percent', $summary);
    }

    public function test_hotel_occupancy_shows_single_pilot_staff_pool(): void
    {
        $pilot = User::factory()->create();
        $event = Event::factory()->create([
            'participant_count' => 10,
            'assigned_to' => $pilot->id,
        ]);

        EventQty::create([
            'event_id' => $event->id,
            'qty' => 10,
            'gratis' => 0,
            'staff' => 1,
            'driver' => 0,
        ]);

        $summary = app(EventHotelOccupancyService::class)->forEvent($event);

        // Staff z wariantu qty już obejmuje pilota — assigned_to nie dokłada drugiego miejsca.
        $this->assertSame(1, $summary['pilot_staff']);
        $this->assertSame(1, $summary['staff']);
        $this->assertSame(11, $summary['required_beds_per_night']);
    }

    public function test_hotel_occupancy_uses_qty_staff_without_assigned_pilot(): void
    {
        $event = Event::factory()->create([
            'participant_count' => 10,
            'assigned_to' => null,
        ]);

        EventQty::create([
            'event_id' => $event->id,
            'qty' => 10,
            'gratis' => 0,
            'staff' => 1,
            'driver' => 1,
        ]);

        $summary = app(EventHotelOccupancyService::class)->forEvent($event);

        $this->assertSame(1, $summary['staff']);
        $this->assertSame(1, $summary['drivers']);
        $this->assertSame(12, $summary['required_beds_per_night']);
    }

    public function test_log_sms_channel_returns_true(): void
    {
        $this->assertTrue(app(LogSmsChannel::class)->send('+48123123123', 'test'));
    }

    public function test_ksef_outbound_disabled_by_default(): void
    {
        $invoice = new \App\Models\SalesInvoice;
        $result = app(KsefOutboundService::class)->submit($invoice);

        $this->assertFalse($result['accepted']);
        $this->assertStringContainsString('wyłączony', $result['message']);
    }
}
