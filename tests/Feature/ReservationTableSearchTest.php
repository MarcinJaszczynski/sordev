<?php

namespace Tests\Feature;

use App\Filament\Resources\ReservationResource;
use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventTemplate;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationTableSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_reservation_by_event_code(): void
    {
        $reservation = $this->createReservation(eventCode: '26-78ROU0');

        $results = ReservationResource::applyTableSearch(Reservation::query(), '26-78ROU0')->pluck('id');

        $this->assertTrue($results->contains($reservation->id));
        $this->assertCount(1, $results);
    }

    public function test_event_code_search_does_not_match_other_events(): void
    {
        $target = $this->createReservation(eventCode: '26-78ROU0');
        $other = $this->createReservation(eventCode: '26-ABCD12');

        $results = ReservationResource::applyTableSearch(Reservation::query(), '26-78ROU0')->pluck('id');

        $this->assertTrue($results->contains($target->id));
        $this->assertFalse($results->contains($other->id));
    }

    public function test_search_finds_reservation_by_contractor_name(): void
    {
        $contractor = Contractor::create(['name' => 'Hotel Aurora', 'status' => 'active']);
        $reservation = $this->createReservation(contractor: $contractor);

        $results = ReservationResource::applyTableSearch(Reservation::query(), 'Aurora')->pluck('id');

        $this->assertTrue($results->contains($reservation->id));
    }

    public function test_search_finds_reservation_by_reserved_date(): void
    {
        $reservation = $this->createReservation(reservedAt: '2026-12-30');

        $results = ReservationResource::applyTableSearch(Reservation::query(), '30.12.2026')->pluck('id');

        $this->assertTrue($results->contains($reservation->id));
    }

    public function test_search_finds_reservation_by_program_point_name(): void
    {
        $reservation = $this->createReservation(pointName: 'Muzeum Narodowe');

        $results = ReservationResource::applyTableSearch(Reservation::query(), 'Muzeum Narodowe')->pluck('id');

        $this->assertTrue($results->contains($reservation->id));
    }

    protected function createReservation(
        ?string $eventCode = null,
        ?Contractor $contractor = null,
        ?string $reservedAt = null,
        ?string $pointName = null,
    ): Reservation {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza testowa',
            'client_name' => 'Klient testowy',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-03',
            'participant_count' => 20,
            'total_cost' => 1000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $event->forceFill(['code' => $eventCode ?? 'EVT-001'])->save();

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => $pointName ?? 'Zwiedzanie',
            'day' => 1,
            'order' => 1,
            'total_price' => 100,
        ]);

        return Reservation::create([
            'event_id' => $event->id,
            'program_point_id' => $point->id,
            'contractor_id' => $contractor?->id,
            'booking_reference' => 'REZ-TEST-'.$event->id.'-'.$point->id,
            'status' => 'pending',
            'reserved_at' => $reservedAt ?? now()->toDateString(),
            'created_by' => $user->id,
        ]);
    }
}
