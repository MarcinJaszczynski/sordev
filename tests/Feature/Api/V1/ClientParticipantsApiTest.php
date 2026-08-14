<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Event;
use App\Models\EventPortalAccess;
use App\Models\EventParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientParticipantsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'client_guardian', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'client_participant', 'guard_name' => 'web']);
    }

    public function test_guardian_can_create_participant(): void
    {
        if (! Schema::hasTable('event_participants') || ! Schema::hasTable('event_portal_accesses')) {
            $this->markTestSkipped('Brak tabel uczestników/portalu.');
        }

        $guardian = User::factory()->create(['status' => 'active']);
        $guardian->assignRole('client_guardian');

        $event = Event::factory()->create([
            'status' => Event::STATUS_CONFIRMED,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(12)->toDateString(),
        ]);

        EventPortalAccess::create([
            'event_id' => $event->id,
            'user_id' => $guardian->id,
            'role' => EventPortalAccess::ROLE_GUARDIAN,
            'source' => EventPortalAccess::SOURCE_ADMIN,
            'shared_at' => now(),
        ]);

        $response = $this->actingAs($guardian, 'sanctum')
            ->json('POST', '/api/v1/client/trips/'.$event->id.'/participants', [
                'first_name' => 'Anna',
                'last_name' => 'Nowak',
                'diet' => 'wegetariańska',
                'consents' => ['rodo' => true],
            ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.first_name', 'Anna');

        $this->assertDatabaseHas('event_participants', [
            'event_id' => $event->id,
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
        ]);
    }

    public function test_participant_cannot_list_group_participants(): void
    {
        if (! Schema::hasTable('event_portal_accesses')) {
            $this->markTestSkipped('Brak event_portal_accesses.');
        }

        $participant = User::factory()->create(['status' => 'active']);
        $participant->assignRole('client_participant');
        $event = Event::factory()->create(['status' => Event::STATUS_CONFIRMED]);

        EventPortalAccess::create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'role' => EventPortalAccess::ROLE_PARTICIPANT,
            'source' => EventPortalAccess::SOURCE_ADMIN,
            'shared_at' => now(),
        ]);

        $this->actingAs($participant, 'sanctum')
            ->json('GET', '/api/v1/client/trips/'.$event->id.'/participants', [], ['Accept' => 'application/json'])
            ->assertForbidden();
    }
}
