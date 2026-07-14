<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramPointContractorSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_changing_program_point_contractor_syncs_settlement_costs_and_reservations(): void
    {
        $user = User::factory()->create();

        $contractorA = Contractor::create(['name' => 'Muzeum A', 'status' => 'active']);
        $contractorB = Contractor::create(['name' => 'Muzeum B', 'status' => 'active']);

        $event = Event::factory()->create(['assigned_to' => $user->id]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Zwiedzanie',
            'contractor_id' => $contractorA->id,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $planCost = $settlement->costs()->create([
            'source_type' => 'program_point',
            'source_id' => $point->id,
            'name' => $point->name,
            'planned_amount' => 1000,
            'planned_currency_id' => null,
            'planned_rate' => 1,
            'planned_amount_pln' => 1000,
            'contractor_id' => $contractorA->id,
            'paid_by' => 'office',
            'advance_type' => 'full',
            'payment_status' => 'planned',
        ]);

        $advanceCost = $settlement->costs()->create([
            'source_type' => 'program_point_payment',
            'source_id' => $point->id,
            'name' => $point->name.' • zaliczka',
            'planned_amount' => 0,
            'planned_rate' => 1,
            'planned_amount_pln' => 0,
            'advance_amount' => 200,
            'contractor_id' => $contractorA->id,
            'paid_by' => 'office',
            'advance_type' => 'advance',
            'payment_status' => 'advance_required',
        ]);

        $reservation = Reservation::create([
            'program_point_id' => $point->id,
            'event_id' => $event->id,
            'settlement_cost_id' => $planCost->id,
            'contractor_id' => $contractorA->id,
            'status' => 'active',
            'reserved_amount' => 200,
            'reserved_at' => now(),
            'created_by' => $user->id,
        ]);

        $point->update(['contractor_id' => $contractorB->id]);

        $this->assertSame($contractorB->id, (int) $planCost->fresh()->contractor_id);
        $this->assertSame($contractorB->id, (int) $advanceCost->fresh()->contractor_id);
        $this->assertSame($contractorB->id, (int) $reservation->fresh()->contractor_id);
    }

    public function test_import_from_event_sets_contractor_from_program_point(): void
    {
        $user = User::factory()->create();
        $contractor = Contractor::create(['name' => 'Przewodnik', 'status' => 'active']);

        $event = Event::factory()->create(['assigned_to' => $user->id]);

        EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Spacer',
            'contractor_id' => $contractor->id,
            'unit_price' => 500,
            'quantity' => 1,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $settlement->importFromEvent();

        $cost = EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->where('source_type', 'program_point')
            ->first();

        $this->assertNotNull($cost);
        $this->assertSame($contractor->id, (int) $cost->contractor_id);
    }
}
