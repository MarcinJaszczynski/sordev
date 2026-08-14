<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\Reservation;
use App\Support\PilotProgramReservationDisplay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PilotProgramReservationDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_empty_when_point_has_no_reservations(): void
    {
        if (! Schema::hasTable('reservations')) {
            $this->markTestSkipped('Tabela reservations nie istnieje.');
        }

        $event = Event::factory()->create();
        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'start_time' => '10:30:00',
        ]);

        $this->assertSame([], PilotProgramReservationDisplay::linesForPoint($point));
    }

    public function test_maps_reservation_status_time_and_reference(): void
    {
        if (! Schema::hasTable('reservations')) {
            $this->markTestSkipped('Tabela reservations nie istnieje.');
        }

        $event = Event::factory()->create();
        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'start_time' => '14:15:00',
        ]);

        Reservation::create([
            'event_id' => $event->id,
            'program_point_id' => $point->id,
            'status' => 'confirmed',
            'booking_reference' => 'HTL-22',
            'reserved_at' => now(),
        ]);

        Reservation::create([
            'event_id' => $event->id,
            'program_point_id' => $point->id,
            'status' => 'not_required',
            'reserved_at' => now(),
        ]);

        $lines = PilotProgramReservationDisplay::linesForPoint($point->fresh('reservations'));

        $this->assertCount(1, $lines);
        $this->assertSame('confirmed', $lines[0]['status']);
        $this->assertSame('Rez. potwierdzona', $lines[0]['status_label']);
        $this->assertSame('14:15', $lines[0]['time']);
        $this->assertSame('HTL-22', $lines[0]['reference']);
        $this->assertStringContainsString('emerald', $lines[0]['badge_classes']);
    }
}
