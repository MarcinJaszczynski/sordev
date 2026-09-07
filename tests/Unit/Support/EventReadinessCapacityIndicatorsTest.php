<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\Bus;
use App\Models\Event;
use App\Models\EventHotelRoomLine;
use App\Models\EventHotelStay;
use App\Models\EventQty;
use App\Models\EventVehicle;
use App\Models\HotelRoom;
use App\Models\Vehicle;
use App\Support\EventReadinessIndicators;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventReadinessCapacityIndicatorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_bus_capacity_readiness_is_muted_when_only_catalog_bus_is_set(): void
    {
        $bus = Bus::factory()->create([
            'name' => 'Setra 49',
            'capacity' => 49,
        ]);

        $event = Event::factory()->create([
            'bus_id' => $bus->id,
            'participant_count' => 47,
        ]);

        EventQty::create([
            'event_id' => $event->id,
            'qty' => 47,
            'gratis' => 3,
            'staff' => 0,
            'driver' => 0,
        ]);

        $item = collect(EventReadinessIndicators::forEvent($event->fresh()))->firstWhere('key', 'bus_capacity');

        $this->assertSame('muted', $item['tone']);
        $this->assertSame('—', $item['short']);
        $this->assertStringContainsString('pojazdu floty', $item['title']);
    }

    public function test_bus_capacity_readiness_is_danger_when_group_exceeds_fleet_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create([
            'registration_number' => 'WW 11111',
            'capacity' => 49,
        ]);

        $event = Event::factory()->create([
            'bus_id' => null,
            'participant_count' => 47,
        ]);

        EventVehicle::query()->create([
            'event_id' => $event->id,
            'vehicle_id' => $vehicle->id,
            'role' => 'main',
        ]);

        EventQty::create([
            'event_id' => $event->id,
            'qty' => 47,
            'gratis' => 3,
            'staff' => 0,
            'driver' => 0,
        ]);

        $item = collect(EventReadinessIndicators::forEvent($event->fresh(['eventVehicles.vehicle'])))->firstWhere('key', 'bus_capacity');

        $this->assertSame('danger', $item['tone']);
        $this->assertSame('50/49', $item['short']);
        $this->assertStringContainsString('przekracza pojemność', $item['title']);
    }

    public function test_bus_capacity_readiness_is_ok_when_group_fits_fleet_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create(['capacity' => 49]);
        $event = Event::factory()->create([
            'bus_id' => null,
            'participant_count' => 40,
        ]);

        EventVehicle::query()->create([
            'event_id' => $event->id,
            'vehicle_id' => $vehicle->id,
            'role' => 'main',
        ]);

        EventQty::create([
            'event_id' => $event->id,
            'qty' => 40,
            'gratis' => 5,
            'staff' => 0,
            'driver' => 0,
        ]);

        $item = collect(EventReadinessIndicators::forEvent($event->fresh(['eventVehicles.vehicle'])))->firstWhere('key', 'bus_capacity');

        $this->assertSame('ok', $item['tone']);
        $this->assertSame('OK', $item['short']);
    }

    public function test_bus_capacity_ignores_catalog_bus_when_fleet_vehicle_fits(): void
    {
        $catalogBus = Bus::factory()->create([
            'name' => 'Mały z cennika',
            'capacity' => 19,
        ]);
        $vehicle = Vehicle::factory()->create([
            'registration_number' => 'WW 55555',
            'capacity' => 49,
        ]);

        $event = Event::factory()->create([
            'bus_id' => $catalogBus->id,
            'participant_count' => 40,
        ]);

        EventVehicle::query()->create([
            'event_id' => $event->id,
            'vehicle_id' => $vehicle->id,
            'role' => 'main',
        ]);

        EventQty::create([
            'event_id' => $event->id,
            'qty' => 40,
            'gratis' => 5,
            'staff' => 0,
            'driver' => 0,
        ]);

        $item = collect(EventReadinessIndicators::forEvent($event->fresh(['eventVehicles.vehicle'])))->firstWhere('key', 'bus_capacity');

        $this->assertSame('ok', $item['tone']);
        $this->assertSame('OK', $item['short']);
    }

    public function test_bus_capacity_readiness_uses_fleet_vehicle_when_no_catalog_bus(): void
    {
        $vehicle = Vehicle::factory()->create([
            'registration_number' => 'WW 99999',
            'capacity' => 19,
        ]);

        $event = Event::factory()->create([
            'bus_id' => null,
            'participant_count' => 18,
        ]);

        EventVehicle::query()->create([
            'event_id' => $event->id,
            'vehicle_id' => $vehicle->id,
            'role' => 'main',
        ]);

        EventQty::create([
            'event_id' => $event->id,
            'qty' => 18,
            'gratis' => 2,
            'staff' => 0,
            'driver' => 0,
        ]);

        $item = collect(EventReadinessIndicators::forEvent($event->fresh(['eventVehicles.vehicle'])))->firstWhere('key', 'bus_capacity');

        $this->assertSame('danger', $item['tone']);
        $this->assertSame('20/19', $item['short']);
    }

    public function test_hotel_beds_readiness_is_danger_when_planned_beds_are_insufficient(): void
    {
        $event = Event::factory()->create([
            'participant_count' => 33,
            'duration_days' => 2,
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

        $stay = EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 1,
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

        $item = collect(EventReadinessIndicators::forEvent($event->fresh()))->firstWhere('key', 'hotel_beds');

        $this->assertSame('danger', $item['tone']);
        $this->assertSame('33/38', $item['short']);
        $this->assertStringContainsString('Brakuje 5 miejsc', $item['title']);
    }

    public function test_hotel_beds_readiness_is_muted_without_room_lines(): void
    {
        $event = Event::factory()->create(['participant_count' => 20]);

        EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 1,
        ]);

        $item = collect(EventReadinessIndicators::forEvent($event->fresh()))->firstWhere('key', 'hotel_beds');

        $this->assertSame('muted', $item['tone']);
        $this->assertSame('—', $item['short']);
    }

    public function test_pilot_funds_readiness_uses_bus_collection_plan_instead_of_danger(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('event_bus_collections')) {
            $this->markTestSkipped('Brak tabeli event_bus_collections.');
        }

        $pilot = \App\Models\User::factory()->create();
        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'pilot_funds_paid' => false,
            'pilot_advance_planned_amount' => null,
        ]);

        $plnId = \App\Models\Currency::query()->firstOrCreate(
            ['code' => 'PLN'],
            ['name' => 'PLN', 'symbol' => 'PLN', 'exchange_rate' => 1],
        )->id;

        \App\Models\EventBusCollection::create([
            'event_id' => $event->id,
            'title' => 'Plan EUR',
            'amount' => 1280,
            'planned_amount' => 1280,
            'currency_id' => $plnId,
            'participant_count' => 10,
            'amount_per_person' => 128,
            'status' => \App\Models\EventBusCollection::STATUS_PLANNED,
        ]);

        $item = collect(EventReadinessIndicators::forEvent($event->fresh()))->firstWhere('key', 'pilot_funds');

        $this->assertSame('warn', $item['tone']);
        $this->assertSame('plan zb.', $item['short']);
        $this->assertStringContainsString('zbiórkę w autokarze', $item['title']);
    }
}
