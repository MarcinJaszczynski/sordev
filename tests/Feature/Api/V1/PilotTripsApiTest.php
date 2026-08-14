<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\User;
use App\Support\Api\ApiAbilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PilotTripsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'pilot', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    private function pilotUser(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('pilot');

        return $user;
    }

    private function sharedTripFor(User $pilot, array $overrides = []): Event
    {
        $payload = array_merge([
            'assigned_to' => $pilot->id,
            'name' => 'Wycieczka pilota',
            'status' => Event::STATUS_CONFIRMED,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ], $overrides);

        if (Schema::hasColumn('events', 'shared_with_pilot')) {
            $payload['shared_with_pilot'] = true;
        }

        return Event::factory()->create($payload);
    }

    private function apiAs(User $user, string $method, string $uri, array $data = [])
    {
        return $this->actingAs($user, 'sanctum')
            ->json($method, '/api/v1'.$uri, $data, ['Accept' => 'application/json']);
    }

    public function test_pilot_lists_only_own_shared_trips(): void
    {
        $pilot = $this->pilotUser();
        $other = $this->pilotUser();

        $own = $this->sharedTripFor($pilot, ['name' => 'Moja']);
        $this->sharedTripFor($other, ['name' => 'Cudza']);

        $response = $this->apiAs($pilot, 'GET', '/pilot/trips');

        $response->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.id', $own->id);
    }

    public function test_non_pilot_gets_403(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->apiAs($admin, 'GET', '/pilot/trips')
            ->assertForbidden();
    }

    public function test_guest_gets_401(): void
    {
        $this->json('GET', '/api/v1/pilot/trips', [], ['Accept' => 'application/json'])
            ->assertUnauthorized();
    }

    public function test_pilot_can_read_program(): void
    {
        $pilot = $this->pilotUser();
        $event = $this->sharedTripFor($pilot, [
            'duration_days' => 3,
        ]);

        EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'name' => 'Zwiedzanie',
            'day' => 1,
            'order' => 1,
            'include_in_program' => true,
            'active' => true,
            'pilot_notes' => 'Tylko dla pilota',
            'office_notes' => 'Tajne biuro',
        ]);

        $response = $this->apiAs($pilot, 'GET', '/pilot/trips/'.$event->id.'/program');

        $response->assertOk()
            ->assertJsonPath('data.points.0.name', 'Zwiedzanie')
            ->assertJsonPath('data.points.0.pilot_notes', 'Tylko dla pilota')
            ->assertJsonMissingPath('data.points.0.office_notes')
            ->assertJsonMissingPath('data.points.0.unit_price');
    }

    public function test_bearer_token_without_pilot_ability_is_forbidden(): void
    {
        $pilot = $this->pilotUser();
        $token = $pilot->createToken('mobile', [ApiAbilities::EVENTS_READ])->plainTextToken;

        $this->withToken($token)
            ->json('GET', '/api/v1/pilot/trips', [], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    public function test_bearer_token_with_pilot_ability_works(): void
    {
        $pilot = $this->pilotUser();
        $this->sharedTripFor($pilot);
        $token = $pilot->createToken('mobile', [ApiAbilities::PILOT_READ])->plainTextToken;

        $this->withToken($token)
            ->json('GET', '/api/v1/pilot/trips', [], ['Accept' => 'application/json'])
            ->assertOk();
    }
}
