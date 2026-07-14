<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventTemplate;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationAdvanceDueDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_with_date_only_sets_advance_due_date_from_reserved_at(): void
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza rezerwacja',
            'client_name' => 'Klient',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 10,
            'total_cost' => 1000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Muzeum',
            'day' => 1,
            'order' => 1,
            'total_price' => 100,
        ]);

        Reservation::create([
            'program_point_id' => $point->id,
            'event_id' => $event->id,
            'status' => 'pending',
            'deposit_due_at' => '2026-12-30',
            'reserved_amount' => 34234,
            'created_by' => $user->id,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event->fresh());
        $cost = $settlement->costs()
            ->where('source_type', 'program_point')
            ->where('source_id', $point->id)
            ->first();

        $this->assertNotNull($cost);
        $this->assertNotNull($cost->advance_due_date);
        $this->assertSame('2026-12-30', $cost->advance_due_date->toDateString());
    }

    public function test_reservation_rejects_invalid_year(): void
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza rezerwacja',
            'client_name' => 'Klient',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 10,
            'total_cost' => 1000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Muzeum',
            'day' => 1,
            'order' => 1,
            'total_price' => 100,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        Reservation::create([
            'program_point_id' => $point->id,
            'event_id' => $event->id,
            'status' => 'pending',
            'reserved_at' => '0325-12-30 00:00:00',
            'created_by' => $user->id,
        ]);
    }
}
