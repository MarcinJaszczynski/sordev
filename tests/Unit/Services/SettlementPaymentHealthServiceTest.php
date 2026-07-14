<?php

namespace Tests\Unit\Services;

use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Services\SettlementPaymentHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettlementPaymentHealthServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ok_when_paid_matches_plan(): void
    {
        $event = Event::factory()->create(['use_manual_transport_cost' => true, 'manual_transport_cost' => 1000]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $event->refreshActiveSettlementCosts();

        $plan = $settlement->costs()->where('source_type', 'transport')->first();
        $this->assertNotNull($plan);

        EventSettlementCost::query()->create([
            'settlement_id' => $settlement->id,
            'source_type' => 'transport_payment',
            'name' => 'Wpłata transport',
            'actual_amount_pln' => $plan->planned_amount_pln,
            'payment_status' => 'paid',
            'paid_by' => 'office',
        ]);

        $health = app(SettlementPaymentHealthService::class);
        $allCosts = $settlement->costs()->get();
        $result = $health->evaluatePlanCost($plan, $allCosts);

        $this->assertSame(SettlementPaymentHealthService::STATUS_OK, $result['coverage_status']);
    }

    public function test_due_when_future_deadline_and_shortfall(): void
    {
        $event = Event::factory()->create(['use_manual_transport_cost' => true, 'manual_transport_cost' => 2000]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $event->refreshActiveSettlementCosts();

        $plan = $settlement->costs()->where('source_type', 'transport')->first();
        $this->assertNotNull($plan);

        EventSettlementCost::query()->create([
            'settlement_id' => $settlement->id,
            'source_type' => 'transport_payment',
            'name' => 'Zaliczka transport',
            'actual_amount_pln' => 500,
            'advance_due_date' => now()->addWeek(),
            'payment_status' => 'partially_paid',
            'paid_by' => 'office',
        ]);

        $health = app(SettlementPaymentHealthService::class);
        $allCosts = $settlement->costs()->get();
        $result = $health->evaluatePlanCost($plan, $allCosts);

        $this->assertSame(SettlementPaymentHealthService::STATUS_DUE, $result['coverage_status']);
    }

    public function test_overdue_when_past_deadline(): void
    {
        $event = Event::factory()->create(['use_manual_transport_cost' => true, 'manual_transport_cost' => 1500]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $event->refreshActiveSettlementCosts();

        $plan = $settlement->costs()->where('source_type', 'transport')->first();

        EventSettlementCost::query()->create([
            'settlement_id' => $settlement->id,
            'source_type' => 'transport_payment',
            'name' => 'Zaległa wpłata',
            'actual_amount_pln' => 100,
            'advance_due_date' => now()->subDays(3),
            'payment_status' => 'partially_paid',
            'paid_by' => 'pilot',
        ]);

        $health = app(SettlementPaymentHealthService::class);
        $result = $health->evaluatePlanCost($plan, $settlement->costs()->get());

        $this->assertSame(SettlementPaymentHealthService::STATUS_OVERDUE, $result['coverage_status']);
    }

    public function test_multiple_payments_sum_for_health(): void
    {
        $event = Event::factory()->create(['use_manual_transport_cost' => true, 'manual_transport_cost' => 1000]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $event->refreshActiveSettlementCosts();
        $plan = $settlement->costs()->where('source_type', 'transport')->first();

        foreach ([400, 600] as $amount) {
            EventSettlementCost::query()->create([
                'settlement_id' => $settlement->id,
                'source_type' => 'transport_payment',
                'name' => 'Wpłata '.$amount,
                'actual_amount_pln' => $amount,
                'payment_status' => 'paid',
                'paid_by' => 'office',
            ]);
        }

        $health = app(SettlementPaymentHealthService::class);
        $result = $health->evaluatePlanCost($plan, $settlement->costs()->get());

        $this->assertSame(SettlementPaymentHealthService::STATUS_OK, $result['coverage_status']);
        $this->assertSame(1000.0, $result['paid_pln']);
    }
}
