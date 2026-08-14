<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Event;
use App\Models\EventPortalAccess;
use App\Models\EventProgramPoint;
use App\Models\User;
use App\Support\Api\ApiAbilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientTripsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'client_participant', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'client_guardian', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'pilot', 'guard_name' => 'web']);
    }

    private function clientUser(string $role = 'client_participant'): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole($role);

        return $user;
    }

    private function grantAccess(User $user, Event $event, string $role = EventPortalAccess::ROLE_PARTICIPANT): void
    {
        if (! Schema::hasTable('event_portal_accesses')) {
            $this->markTestSkipped('Brak tabeli event_portal_accesses.');
        }

        EventPortalAccess::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'role' => $role,
            'source' => EventPortalAccess::SOURCE_ADMIN,
            'shared_at' => now(),
        ]);
    }

    private function apiAs(User $user, string $method, string $uri, array $data = [])
    {
        return $this->actingAs($user, 'sanctum')
            ->json($method, '/api/v1'.$uri, $data, ['Accept' => 'application/json']);
    }

    public function test_client_lists_only_accessible_trips(): void
    {
        $client = $this->clientUser();
        $own = Event::factory()->create(['name' => 'Moja impreza', 'status' => Event::STATUS_CONFIRMED]);
        Event::factory()->create(['name' => 'Obca', 'status' => Event::STATUS_CONFIRMED]);
        $this->grantAccess($client, $own);

        $response = $this->apiAs($client, 'GET', '/client/trips');

        $response->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.id', $own->id);
    }

    public function test_pilot_cannot_use_client_api(): void
    {
        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $this->apiAs($pilot, 'GET', '/client/trips')
            ->assertForbidden();
    }

    public function test_client_can_read_program_without_prices(): void
    {
        $client = $this->clientUser();
        $event = Event::factory()->create([
            'status' => Event::STATUS_CONFIRMED,
            'duration_days' => 2,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
        ]);
        $this->grantAccess($client, $event);

        EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'name' => 'Muzeum',
            'day' => 1,
            'order' => 1,
            'include_in_program' => true,
            'active' => true,
            'unit_price' => 99.5,
            'pilot_notes' => 'nie dla klienta',
        ]);

        $response = $this->apiAs($client, 'GET', '/client/trips/'.$event->id.'/program');

        $response->assertOk()
            ->assertJsonPath('data.points.0.name', 'Muzeum')
            ->assertJsonMissingPath('data.points.0.unit_price')
            ->assertJsonMissingPath('data.points.0.pilot_notes');
    }

    public function test_bearer_token_with_client_ability_works(): void
    {
        $client = $this->clientUser();
        $event = Event::factory()->create(['status' => Event::STATUS_CONFIRMED]);
        $this->grantAccess($client, $event);
        $token = $client->createToken('ios', [ApiAbilities::CLIENT_READ])->plainTextToken;

        $this->withToken($token)
            ->json('GET', '/api/v1/client/trips', [], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);
    }
}
