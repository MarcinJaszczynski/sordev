<?php

namespace Tests\Feature;

use App\Console\Commands\PruneOrphanSettlementRows;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\EventTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneOrphanSettlementRowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_program_point_removes_linked_settlement_costs(): void
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza orphan',
            'client_name' => 'Szkola',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 10,
            'total_cost' => 1000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Muzeum',
            'day' => 1,
            'order' => 1,
            'unit_price' => 100,
            'quantity' => 1,
            'calculated_price' => 100,
        ]);

        EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point',
            'source_id' => $point->id,
            'name' => 'Muzeum',
            'planned_amount_pln' => 100,
            'paid_by' => 'office',
        ]);

        $this->assertDatabaseHas('event_settlement_costs', [
            'source_type' => 'program_point',
            'source_id' => $point->id,
        ]);

        $point->delete();

        $this->assertSame(0, EventSettlementCost::query()
            ->where('source_type', 'program_point')
            ->where('source_id', $point->id)
            ->count());
    }

    public function test_prune_command_removes_orphan_settlement_costs(): void
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza prune',
            'client_name' => 'Szkola',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 10,
            'total_cost' => 1000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point',
            'source_id' => 999_999,
            'name' => 'Osierocony koszt',
            'planned_amount_pln' => 50,
            'paid_by' => 'office',
        ]);

        $this->artisan(PruneOrphanSettlementRows::class)
            ->assertSuccessful();

        $this->assertSame(0, EventSettlementCost::query()
            ->where('source_type', 'program_point')
            ->where('source_id', 999_999)
            ->count());
    }
}
