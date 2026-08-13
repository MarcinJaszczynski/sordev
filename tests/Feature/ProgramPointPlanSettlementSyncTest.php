<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\User;
use App\Support\ProgramPointCostPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramPointPlanSettlementSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_uses_planned_price_not_unit_based_calculation(): void
    {
        $user = User::factory()->create();
        $pln = Currency::factory()->pln()->create();

        $event = Event::factory()->create(['participant_count' => 20]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Muzeum',
            'unit_price' => 100,
            'group_size' => 1,
            'quantity' => 1,
            'planned_price' => 1500,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
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
            ->where('source_id', $point->id)
            ->first();

        $this->assertNotNull($cost);
        $this->assertSame(1500.0, (float) $cost->planned_amount);
        $this->assertSame(1500.0, (float) $cost->planned_amount_pln);

        // Kalkulacja live nadal z unit_price × osoby koszowe.
        $calc = ProgramPointCostPricing::breakdown($point->fresh(['currency']), $event);
        $this->assertSame(2000.0, (float) $calc['total']);
    }

    public function test_changing_planned_price_updates_settlement_plan_without_changing_unit_price(): void
    {
        $user = User::factory()->create();
        $pln = Currency::factory()->pln()->create();

        $event = Event::factory()->create(['participant_count' => 10]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Obiad',
            'unit_price' => 50,
            'group_size' => 1,
            'quantity' => 1,
            'planned_price' => 500,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $settlement->importFromEvent();

        $point->update(['planned_price' => 700]);

        $cost = EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->where('source_type', 'program_point')
            ->where('source_id', $point->id)
            ->first();

        $this->assertNotNull($cost);
        $this->assertSame(700.0, (float) $cost->planned_amount);
        $this->assertSame(50.0, (float) $point->fresh()->unit_price);

        $calc = ProgramPointCostPricing::breakdown($point->fresh(['currency']), $event->fresh());
        $this->assertSame(500.0, (float) $calc['total']);
    }

    public function test_changing_unit_price_updates_calculation_but_keeps_custom_plan(): void
    {
        $user = User::factory()->create();
        $pln = Currency::factory()->pln()->create();

        $event = Event::factory()->create(['participant_count' => 10]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Bilet',
            'unit_price' => 40,
            'group_size' => 1,
            'quantity' => 1,
            'planned_price' => 450,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $settlement->importFromEvent();

        $point->update(['unit_price' => 60]);

        $point = $point->fresh();
        $cost = EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->where('source_type', 'program_point')
            ->where('source_id', $point->id)
            ->first();

        $this->assertNotNull($cost);
        $this->assertSame(450.0, (float) $cost->planned_amount);
        $this->assertSame(450.0, (float) $point->planned_price);

        $calc = ProgramPointCostPricing::breakdown($point->loadMissing('currency'), $event->fresh());
        $this->assertSame(600.0, (float) $calc['total']);
    }

    public function test_empty_planned_price_seeds_plan_from_calculation(): void
    {
        $user = User::factory()->create();
        $pln = Currency::factory()->pln()->create();

        $event = Event::factory()->create(['participant_count' => 12]);

        // saveQuietly omija hook ustawiający planned_price = calculated.
        $point = new EventProgramPoint([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Transfer',
            'unit_price' => 25,
            'group_size' => 1,
            'quantity' => 1,
            'planned_price' => 0,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);
        $point->saveQuietly();

        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $cost = $settlement->upsertCostFromProgramPoint($point->fresh(['currency', 'event']));

        $expected = ProgramPointCostPricing::breakdown($point->fresh(['currency']), $event)['total'];
        $this->assertSame((float) $expected, (float) $cost->planned_amount);
    }
}
