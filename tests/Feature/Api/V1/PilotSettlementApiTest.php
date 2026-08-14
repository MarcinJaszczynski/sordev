<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PilotSettlementApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'pilot', 'guard_name' => 'web']);
    }

    public function test_pilot_can_load_settlement_and_add_expense(): void
    {
        if (! Schema::hasTable('event_settlements') || ! Schema::hasTable('event_settlement_costs')) {
            $this->markTestSkipped('Brak tabel rozliczenia.');
        }

        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $payload = [
            'assigned_to' => $pilot->id,
            'status' => Event::STATUS_CONFIRMED,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
        ];
        if (Schema::hasColumn('events', 'shared_with_pilot')) {
            $payload['shared_with_pilot'] = true;
        }
        $event = Event::factory()->create($payload);

        $this->actingAs($pilot, 'sanctum')
            ->json('GET', '/api/v1/pilot/trips/'.$event->id.'/settlement', [], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.event_id', $event->id)
            ->assertJsonStructure(['data' => ['settlement', 'expenses', 'cash', 'advances']]);

        $this->actingAs($pilot, 'sanctum')
            ->json('POST', '/api/v1/pilot/trips/'.$event->id.'/settlement/expenses', [
                'name' => 'Parking',
                'actual_amount' => 25.5,
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Parking');
    }
}
