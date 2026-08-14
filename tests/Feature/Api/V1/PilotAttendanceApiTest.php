<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\EventParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PilotAttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'pilot', 'guard_name' => 'web']);
    }

    private function pilotWithTrip(): array
    {
        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $payload = [
            'assigned_to' => $pilot->id,
            'status' => Event::STATUS_CONFIRMED,
            'duration_days' => 2,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ];
        if (Schema::hasColumn('events', 'shared_with_pilot')) {
            $payload['shared_with_pilot'] = true;
        }

        $event = Event::factory()->create($payload);

        return [$pilot, $event];
    }

    public function test_pilot_can_read_and_save_attendance(): void
    {
        if (! Schema::hasTable('event_participants') || ! Schema::hasTable('event_attendances')) {
            $this->markTestSkipped('Brak tabel obecności.');
        }

        [$pilot, $event] = $this->pilotWithTrip();
        $participant = EventParticipant::query()->create([
            'event_id' => $event->id,
            'status' => EventParticipant::STATUS_ACTIVE,
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
        ]);

        $this->actingAs($pilot, 'sanctum')
            ->json('GET', '/api/v1/pilot/trips/'.$event->id.'/attendance', [], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.day', 1);

        $this->actingAs($pilot, 'sanctum')
            ->json('PUT', '/api/v1/pilot/trips/'.$event->id.'/attendance', [
                'day' => 1,
                'statuses' => [
                    (string) $participant->id => EventAttendance::STATUS_PRESENT,
                ],
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.statuses.'.$participant->id, EventAttendance::STATUS_PRESENT);

        $this->assertDatabaseHas('event_attendances', [
            'event_id' => $event->id,
            'event_participant_id' => $participant->id,
            'day' => 1,
            'status' => EventAttendance::STATUS_PRESENT,
        ]);
    }
}
