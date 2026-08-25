<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\Bus;
use App\Models\Event;
use App\Models\EventQty;
use App\Models\Vehicle;
use App\Support\EventBusSeatCapacity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventBusSeatCapacityTest extends TestCase
{
    use RefreshDatabase;

    public function test_exceeds_only_when_paying_plus_gratis_over_capacity(): void
    {
        $this->assertFalse(EventBusSeatCapacity::exceeds(40, 5, 49));
        $this->assertFalse(EventBusSeatCapacity::exceeds(49, 0, 49));
        $this->assertTrue(EventBusSeatCapacity::exceeds(47, 3, 49));
        $this->assertFalse(EventBusSeatCapacity::exceeds(100, 10, 0));
    }

    public function test_message_describes_shortage(): void
    {
        $message = EventBusSeatCapacity::message(47, 3, 49, 'Setra 49');

        $this->assertNotNull($message);
        $this->assertStringContainsString('47 uczestników + 3 opiekunów = 50', $message);
        $this->assertStringContainsString('„Setra 49”', $message);
        $this->assertStringContainsString('49 miejsc', $message);
        $this->assertStringContainsString('Brakuje 1 miejsce', $message);
    }

    public function test_resolve_message_uses_form_state_and_bus_capacity(): void
    {
        $bus = Bus::factory()->create([
            'name' => 'Mały bus',
            'capacity' => 19,
        ]);

        $message = EventBusSeatCapacity::resolveMessage(
            fn (string $key) => match ($key) {
                'bus_id' => $bus->id,
                'participant_count' => 18,
                'gratis_count' => 2,
                default => null,
            }
        );

        $this->assertNotNull($message);
        $this->assertStringContainsString('przekracza pojemność', $message);
        $this->assertStringContainsString('Mały bus', $message);
    }

    public function test_resolve_message_falls_back_to_event_gratis_variant(): void
    {
        $bus = Bus::factory()->create(['capacity' => 20]);
        $event = Event::factory()->create([
            'bus_id' => $bus->id,
            'participant_count' => 18,
        ]);
        EventQty::query()->create([
            'event_id' => $event->id,
            'qty' => 18,
            'gratis' => 3,
            'staff' => 0,
            'driver' => 0,
        ]);

        $message = EventBusSeatCapacity::resolveMessage(
            fn (string $key) => match ($key) {
                'bus_id' => $bus->id,
                default => null,
            },
            $event
        );

        $this->assertNotNull($message);
        $this->assertStringContainsString('18 uczestników + 3 opiekunów = 21', $message);
    }

    public function test_resolve_message_null_when_within_capacity(): void
    {
        $bus = Bus::factory()->create(['capacity' => 49]);

        $this->assertNull(EventBusSeatCapacity::resolveMessage(
            fn (string $key) => match ($key) {
                'bus_id' => $bus->id,
                'participant_count' => 40,
                'gratis_count' => 5,
                default => null,
            }
        ));
    }

    public function test_resolve_fleet_message_uses_vehicle_passenger_capacity(): void
    {
        $vehicle = Vehicle::factory()->create([
            'registration_number' => 'WW 12345',
            'capacity' => 19,
            'crew_seats' => 2,
        ]);

        $message = EventBusSeatCapacity::resolveFleetMessage(
            fn (string $key) => match ($key) {
                'main_fleet_vehicle_id' => $vehicle->id,
                'participant_count' => 18,
                'gratis_count' => 2,
                default => null,
            }
        );

        $this->assertNotNull($message);
        $this->assertStringContainsString('WW 12345', $message);
        $this->assertStringContainsString('19 miejsc', $message);
        $this->assertStringContainsString('Brakuje 1 miejsce', $message);
    }

    public function test_resolve_fleet_message_null_when_within_capacity(): void
    {
        $vehicle = Vehicle::factory()->create([
            'capacity' => 49,
            'crew_seats' => 2,
        ]);

        $this->assertNull(EventBusSeatCapacity::resolveFleetMessage(
            fn (string $key) => match ($key) {
                'main_fleet_vehicle_id' => $vehicle->id,
                'participant_count' => 40,
                'gratis_count' => 5,
                default => null,
            }
        ));
    }

    public function test_resolve_fleet_message_ignores_crew_seats(): void
    {
        $vehicle = Vehicle::factory()->create([
            'capacity' => 49,
            'crew_seats' => 2,
        ]);

        // 49+1 = 50 > 49 passenger seats; crew_seats must not inflate capacity.
        $message = EventBusSeatCapacity::resolveFleetMessage(
            fn (string $key) => match ($key) {
                'main_fleet_vehicle_id' => $vehicle->id,
                'participant_count' => 49,
                'gratis_count' => 1,
                default => null,
            }
        );

        $this->assertNotNull($message);
        $this->assertStringContainsString('49 miejsc', $message);
    }
}
