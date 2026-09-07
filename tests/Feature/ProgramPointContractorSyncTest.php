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

    public function test_set_parent_contractor_does_not_sync_child_payments_or_reservations(): void
    {
        $user = User::factory()->create();

        $place = Contractor::create(['name' => 'Liceum Batorego', 'status' => 'active']);
        $museum = Contractor::create(['name' => 'Muzeum', 'status' => 'active']);
        $guide = Contractor::create(['name' => 'Przewodnik', 'status' => 'active']);

        $event = Event::factory()->create(['assigned_to' => $user->id]);

        $parent = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Wizyta w szkole',
            'include_in_program' => true,
            'include_in_calculation' => false,
            'active' => true,
        ]);

        $childMuseum = EventProgramPoint::create([
            'event_id' => $event->id,
            'parent_id' => $parent->id,
            'day' => 1,
            'order' => 2,
            'name' => 'Bilety muzeum',
            'contractor_id' => $museum->id,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $childGuide = EventProgramPoint::create([
            'event_id' => $event->id,
            'parent_id' => $parent->id,
            'day' => 1,
            'order' => 3,
            'name' => 'Przewodnik',
            'contractor_id' => $guide->id,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $museumCost = $settlement->costs()->create([
            'source_type' => 'program_point',
            'source_id' => $childMuseum->id,
            'name' => $childMuseum->name,
            'planned_amount' => 500,
            'planned_rate' => 1,
            'planned_amount_pln' => 500,
            'contractor_id' => $museum->id,
            'paid_by' => 'office',
            'advance_type' => 'full',
            'payment_status' => 'planned',
        ]);

        $museumPayment = $settlement->costs()->create([
            'source_type' => 'program_point_payment',
            'source_id' => $childMuseum->id,
            'name' => $childMuseum->name.' • zaliczka',
            'planned_amount' => 0,
            'planned_rate' => 1,
            'planned_amount_pln' => 0,
            'advance_amount' => 100,
            'contractor_id' => $museum->id,
            'paid_by' => 'office',
            'advance_type' => 'advance',
            'payment_status' => 'advance_required',
        ]);

        $museumReservation = Reservation::create([
            'program_point_id' => $childMuseum->id,
            'event_id' => $event->id,
            'settlement_cost_id' => $museumCost->id,
            'contractor_id' => $museum->id,
            'status' => 'pending',
            'reserved_amount' => 100,
            'reserved_at' => now(),
            'created_by' => $user->id,
        ]);

        $guideReservation = Reservation::create([
            'program_point_id' => $childGuide->id,
            'event_id' => $event->id,
            'contractor_id' => $guide->id,
            'status' => 'confirmed',
            'reserved_amount' => 200,
            'reserved_at' => now(),
            'created_by' => $user->id,
        ]);

        if (\Illuminate\Support\Facades\Schema::hasColumn('event_program_points', 'reservation_id')) {
            $childMuseum->forceFill(['reservation_id' => $museumReservation->id])->saveQuietly();
            $childGuide->forceFill(['reservation_id' => $guideReservation->id])->saveQuietly();
            // Stary bug: set mógł mieć reservation_id wspólnej grupy.
            $parent->forceFill(['reservation_id' => $museumReservation->id])->saveQuietly();
        }

        $parent->update(['contractor_id' => $place->id]);

        $this->assertTrue($parent->fresh()->isSetParent());
        $this->assertSame($place->id, (int) $parent->fresh()->contractor_id);
        $this->assertSame($museum->id, (int) $museumCost->fresh()->contractor_id);
        $this->assertSame($museum->id, (int) $museumPayment->fresh()->contractor_id);
        $this->assertSame($museum->id, (int) $museumReservation->fresh()->contractor_id);
        $this->assertSame($guide->id, (int) $guideReservation->fresh()->contractor_id);
        $this->assertNotNull($museumPayment->fresh());

        if (\Illuminate\Support\Facades\Schema::hasColumn('event_program_points', 'reservation_id')) {
            $this->assertNull($parent->fresh()->reservation_id);
            $this->assertSame($museumReservation->id, (int) $childMuseum->fresh()->reservation_id);
            $this->assertSame($guideReservation->id, (int) $childGuide->fresh()->reservation_id);
        }
    }
}
