<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Currency;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventQty;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\User;
use App\Services\EventCostCalculator;
use App\Services\EventFinanceOverviewService;
use App\Services\ProgramPointPricingCalculator;
use App\Support\ProgramPointCostPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramPointCostPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_for_one_with_gratis_multiplies_by_headcount(): void
    {
        // 1 za 1 zł × 45 osób koszowych (40+5) = 45 zł
        $this->assertSame(45, ProgramPointPricingCalculator::billableUnits(45, 1));
        $this->assertSame(45.0, ProgramPointPricingCalculator::totalPrice(1.0, 45, 1));
    }

    public function test_group_pricing_fifteen_for_ten(): void
    {
        // 15 za 10 → 45 os. = 3 × 10 = 30
        $this->assertSame(3, ProgramPointPricingCalculator::billableUnits(45, 15));
        $this->assertSame(30.0, ProgramPointPricingCalculator::totalPrice(10.0, 45, 15));
    }

    public function test_cost_headcount_defaults_to_paying_only(): void
    {
        $event = Event::factory()->create(['participant_count' => 44]);
        EventQty::create([
            'event_id' => $event->id,
            'qty' => 44,
            'gratis' => 4,
            'staff' => 1,
            'driver' => 1,
        ]);

        $this->assertSame(44, ProgramPointCostPricing::costHeadcount($event));
        $this->assertSame(44, ProgramPointCostPricing::costHeadcount($event, null, false));
        $this->assertSame(48, ProgramPointCostPricing::costHeadcount($event, null, true));
    }

    public function test_breakdown_respects_include_gratis_in_cost_flag(): void
    {
        $user = User::factory()->create();
        $pln = Currency::create([
            'name' => 'Złoty',
            'symbol' => 'PLN',
            'code' => 'PLN',
            'exchange_rate' => 1,
        ]);

        $event = Event::factory()->create([
            'participant_count' => 44,
            'assigned_to' => $user->id,
        ]);
        EventQty::create([
            'event_id' => $event->id,
            'qty' => 44,
            'gratis' => 4,
            'staff' => 1,
            'driver' => 1,
        ]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Bilet',
            'unit_price' => 1,
            'quantity' => 1,
            'group_size' => 1,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
            'include_in_program' => true,
            'include_gratis_in_cost' => false,
            'active' => true,
        ]);

        $without = ProgramPointCostPricing::breakdown($point, $event);
        $this->assertSame(44, $without['headcount']);
        $this->assertSame(44.0, $without['total']);
        $this->assertFalse($without['include_gratis']);

        $point->update(['include_gratis_in_cost' => true]);
        $with = ProgramPointCostPricing::breakdown($point->fresh(), $event);
        $this->assertSame(48, $with['headcount']);
        $this->assertSame(48.0, $with['total']);
        $this->assertTrue($with['include_gratis']);
    }

    public function test_finance_calculation_ignores_stale_calculated_price(): void
    {
        $user = User::factory()->create();
        $eur = Currency::create([
            'name' => 'Euro',
            'symbol' => 'EUR',
            'code' => 'EUR',
            'exchange_rate' => 4.35,
        ]);

        $event = Event::factory()->create([
            'participant_count' => 44,
            'assigned_to' => $user->id,
        ]);
        EventQty::create([
            'event_id' => $event->id,
            'qty' => 44,
            'gratis' => 4,
            'staff' => 1,
            'driver' => 1,
        ]);

        // Parking: 40 os. za 1305 EUR → ceil(44/40)=2 × 1305 (domyślnie bez gratisów)
        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Parking',
            'unit_price' => 1305,
            'quantity' => 1,
            'group_size' => 40,
            'currency_id' => $eur->id,
            'convert_to_pln' => true,
            'calculated_price' => 600, // stale / błędne
            'include_in_program' => true,
            'include_gratis_in_cost' => true, // z gratisami: ceil(48/40)=2 — ta sama liczba jednostek
            'active' => true,
        ]);

        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $cost = EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'name' => 'Parking',
            'source_type' => 'program_point',
            'source_id' => $point->id,
            'planned_amount' => 2610,
            'planned_amount_pln' => 11353.50,
            'planned_currency_id' => $eur->id,
            'planned_convert_to_pln' => true,
            'planned_rate' => 4.35,
            'paid_by' => 'office',
            'payment_status' => 'planned',
        ]);

        $service = app(EventFinanceOverviewService::class);
        $calc = $service->calculationPlnForPlanCost($cost, $event);

        $this->assertSame(11353.5, $calc);
        $this->assertNotEquals(600.0, $calc);
    }

    public function test_upsert_seeds_plan_from_paying_only_by_default(): void
    {
        $user = User::factory()->create();
        $pln = Currency::create([
            'name' => 'Złoty',
            'symbol' => 'PLN',
            'code' => 'PLN',
            'exchange_rate' => 1,
        ]);

        $event = Event::factory()->create([
            'participant_count' => 40,
            'assigned_to' => $user->id,
        ]);
        EventQty::create([
            'event_id' => $event->id,
            'qty' => 40,
            'gratis' => 5,
            'staff' => 1,
            'driver' => 1,
        ]);

        $point = new EventProgramPoint([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Obiad',
            'unit_price' => 1,
            'quantity' => 1,
            'group_size' => 1,
            'planned_price' => 0,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
            'include_in_program' => true,
            'include_gratis_in_cost' => false,
            'active' => true,
        ]);
        $point->saveQuietly();

        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $cost = $settlement->upsertCostFromProgramPoint($point->fresh(['currency', 'event']));

        $this->assertSame(40.0, (float) $cost->planned_amount);
        $this->assertSame(40.0, (float) $cost->planned_amount_pln);
    }

    public function test_upsert_seeds_plan_with_gratis_when_flag_enabled(): void
    {
        $user = User::factory()->create();
        $pln = Currency::create([
            'name' => 'Złoty',
            'symbol' => 'PLN',
            'code' => 'PLN',
            'exchange_rate' => 1,
        ]);

        $event = Event::factory()->create([
            'participant_count' => 40,
            'assigned_to' => $user->id,
        ]);
        EventQty::create([
            'event_id' => $event->id,
            'qty' => 40,
            'gratis' => 5,
            'staff' => 1,
            'driver' => 1,
        ]);

        $point = new EventProgramPoint([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Obiad',
            'unit_price' => 1,
            'quantity' => 1,
            'group_size' => 1,
            'planned_price' => 0,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
            'include_in_program' => true,
            'include_gratis_in_cost' => true,
            'active' => true,
        ]);
        $point->saveQuietly();

        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $cost = $settlement->upsertCostFromProgramPoint($point->fresh(['currency', 'event']));

        $this->assertSame(45.0, (float) $cost->planned_amount);
        $this->assertSame(45.0, (float) $cost->planned_amount_pln);
    }

    public function test_finance_overview_calculation_without_gratis_by_default(): void
    {
        $user = User::factory()->create();
        $pln = Currency::create([
            'name' => 'Złoty',
            'symbol' => 'PLN',
            'code' => 'PLN',
            'exchange_rate' => 1,
        ]);

        $event = Event::factory()->create([
            'participant_count' => 40,
            'assigned_to' => $user->id,
        ]);
        EventQty::create([
            'event_id' => $event->id,
            'qty' => 40,
            'gratis' => 5,
            'staff' => 1,
            'driver' => 1,
        ]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Muzeum',
            'unit_price' => 10,
            'quantity' => 1,
            'group_size' => 1,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
            'include_in_program' => true,
            'include_gratis_in_cost' => false,
            'active' => true,
        ]);

        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $cost = EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'name' => 'Muzeum',
            'source_type' => 'program_point',
            'source_id' => $point->id,
            'planned_amount' => 400,
            'planned_amount_pln' => 400,
            'planned_currency_id' => $pln->id,
            'planned_convert_to_pln' => true,
            'paid_by' => 'office',
            'payment_status' => 'planned',
        ]);

        $service = app(EventFinanceOverviewService::class);
        $this->assertEqualsWithDelta(400.0, $service->calculationPlnForPlanCost($cost, $event->fresh()), 0.01);

        $point->update(['include_gratis_in_cost' => true]);
        $this->assertEqualsWithDelta(450.0, $service->calculationPlnForPlanCost($cost, $event->fresh()), 0.01);
    }

    public function test_upsert_prefers_planned_price_over_unit_calculation(): void
    {
        $user = User::factory()->create();
        $pln = Currency::create([
            'name' => 'Złoty',
            'symbol' => 'PLN',
            'code' => 'PLN',
            'exchange_rate' => 1,
        ]);

        $event = Event::factory()->create([
            'participant_count' => 40,
            'assigned_to' => $user->id,
        ]);
        EventQty::create([
            'event_id' => $event->id,
            'qty' => 40,
            'gratis' => 5,
            'staff' => 1,
            'driver' => 1,
        ]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Obiad',
            'unit_price' => 1,
            'quantity' => 1,
            'group_size' => 1,
            'planned_price' => 50,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
            'include_in_program' => true,
            'active' => true,
        ]);

        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $cost = $settlement->upsertCostFromProgramPoint($point->fresh(['currency', 'event']));

        $this->assertSame(50.0, (float) $cost->planned_amount);
        $this->assertSame(40.0, (float) ProgramPointCostPricing::breakdown($point->fresh(['currency']), $event)['total']);

        $point->update(['include_gratis_in_cost' => true]);
        $this->assertSame(45.0, (float) ProgramPointCostPricing::breakdown($point->fresh(['currency']), $event)['total']);
    }

    public function test_offer_cost_calculator_excludes_gratis_by_default_and_includes_when_flagged(): void
    {
        $pln = Currency::create([
            'name' => 'Złoty',
            'symbol' => 'PLN',
            'code' => 'PLN',
            'exchange_rate' => 1,
        ]);

        $event = Event::factory()->create(['participant_count' => 40]);
        EventQty::create([
            'event_id' => $event->id,
            'qty' => 40,
            'gratis' => 5,
            'staff' => 1,
            'driver' => 1,
        ]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Obiad',
            'unit_price' => 10,
            'quantity' => 1,
            'group_size' => 1,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
            'include_in_calculation' => true,
            'include_in_program' => true,
            'include_gratis_in_cost' => false,
            'active' => true,
        ]);

        $result = EventCostCalculator::for($event->fresh())->calculate(40);
        $programLine = collect($result['lines'])->firstWhere('name', 'Obiad');

        $this->assertNotNull($programLine);
        $this->assertSame(400.0, (float) $programLine['cost_pln']);

        EventCostCalculator::clearRequestCache();
        $point->update(['include_gratis_in_cost' => true]);
        $resultOn = EventCostCalculator::for($event->fresh())->calculate(40);
        $programLineOn = collect($resultOn['lines'])->firstWhere('name', 'Obiad');

        $this->assertNotNull($programLineOn);
        $this->assertSame(450.0, (float) $programLineOn['cost_pln']);
    }
}
