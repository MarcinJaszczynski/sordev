<?php

namespace Tests\Unit\Services;

use App\Models\Currency;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Services\EventFinanceOverviewService;
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
            'advance_type' => 'advance',
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
            'advance_type' => 'advance',
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

    public function test_flag_paid_without_payment_stack_is_not_ok(): void
    {
        $event = Event::factory()->create(['use_manual_transport_cost' => true, 'manual_transport_cost' => 1000]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $event->refreshActiveSettlementCosts();
        $plan = $settlement->costs()->where('source_type', 'transport')->first();

        $plan->update([
            'payment_status' => 'paid',
            'actual_amount_pln' => null,
            'paid_at' => now(),
        ]);

        $health = app(SettlementPaymentHealthService::class);
        $result = $health->evaluatePlanCost($plan->fresh(), $settlement->costs()->get());

        $this->assertNotSame(SettlementPaymentHealthService::STATUS_OK, $result['coverage_status']);
        $this->assertSame(0.0, $result['paid_pln']);
        $this->assertFalse(SettlementPaymentHealthService::isFullyPaid($result['paid_pln'], $result['planned_pln']));
    }

    public function test_planned_payment_row_actual_does_not_count_as_paid(): void
    {
        $event = Event::factory()->create(['use_manual_transport_cost' => true, 'manual_transport_cost' => 1000]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $event->refreshActiveSettlementCosts();
        $plan = $settlement->costs()->where('source_type', 'transport')->first();

        EventSettlementCost::query()->create([
            'settlement_id' => $settlement->id,
            'source_type' => 'transport_payment',
            'name' => 'KSeF nieopłacona',
            'actual_amount_pln' => 1000,
            'payment_status' => 'planned',
            'paid_by' => 'office',
        ]);

        $health = app(SettlementPaymentHealthService::class);
        $result = $health->evaluatePlanCost($plan, $settlement->costs()->get());

        $this->assertSame(0.0, $result['paid_pln']);
        $this->assertNotSame(SettlementPaymentHealthService::STATUS_OK, $result['coverage_status']);
    }

    public function test_zero_plan_is_n_a_not_ok(): void
    {
        $health = app(SettlementPaymentHealthService::class);

        $this->assertSame(
            SettlementPaymentHealthService::STATUS_NA,
            $health->resolveStatus(0.0, 0.0, null),
        );
        $this->assertFalse(SettlementPaymentHealthService::isFullyPaid(0.0, 0.0));
        $this->assertSame('planned', $health->planPaymentStatusFromAmounts(0.0, 0.0));
    }

    public function test_booked_zero_closure_marks_paid_and_ok(): void
    {
        $health = app(SettlementPaymentHealthService::class);

        $this->assertSame(
            SettlementPaymentHealthService::STATUS_OK,
            $health->resolveStatus(0.0, 500.0, null, false, false, true),
        );
        $this->assertSame('paid', $health->planPaymentStatusFromAmounts(0.0, 500.0, false, false, true));
    }

    public function test_non_converted_foreign_plan_is_due_not_n_a(): void
    {
        $eur = Currency::factory()->eur()->create(['exchange_rate' => 4.35]);
        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $plan = $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Bilety EUR',
            'planned_amount' => 100,
            'planned_amount_pln' => null,
            'planned_currency_id' => $eur->id,
            'planned_convert_to_pln' => false,
            'planned_rate' => 4.35,
            'paid_by' => 'pilot',
            'payment_status' => 'planned',
            'order' => 1,
        ]);

        $health = app(SettlementPaymentHealthService::class);
        $result = $health->evaluatePlanCost($plan->fresh(['plannedCurrency']), $settlement->costs()->get());

        $this->assertSame(0.0, $result['planned_pln']);
        $this->assertEqualsWithDelta(435.0, $result['status_planned_pln'], 0.01);
        $this->assertNotSame(SettlementPaymentHealthService::STATUS_NA, $result['coverage_status']);
        $this->assertSame(SettlementPaymentHealthService::STATUS_SHORTFALL, $result['coverage_status']);

        $overview = app(EventFinanceOverviewService::class)->forEvent($event->fresh(), hideZero: false);
        $row = collect($overview['rows'])->firstWhere('cost_id', $plan->id);
        $this->assertNotNull($row);
        $this->assertSame('due', $row['ui_status']);
        $this->assertSame('Do zapłaty', $row['ui_status_label']);
    }

    public function test_two_partials_cover_plan(): void
    {
        $event = Event::factory()->create(['use_manual_transport_cost' => true, 'manual_transport_cost' => 1000]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $event->refreshActiveSettlementCosts();
        $plan = $settlement->costs()->where('source_type', 'transport')->first();

        EventSettlementCost::query()->create([
            'settlement_id' => $settlement->id,
            'source_type' => 'transport_payment',
            'name' => 'Zaliczka',
            'actual_amount_pln' => 400,
            'payment_status' => 'advance_paid',
            'paid_by' => 'office',
        ]);
        EventSettlementCost::query()->create([
            'settlement_id' => $settlement->id,
            'source_type' => 'transport_payment',
            'name' => 'Dopłata',
            'actual_amount_pln' => 600,
            'payment_status' => 'partially_paid',
            'paid_by' => 'office',
        ]);

        $health = app(SettlementPaymentHealthService::class);
        $result = $health->evaluatePlanCost($plan, $settlement->costs()->get());

        $this->assertSame(1000.0, $result['paid_pln']);
        $this->assertSame(SettlementPaymentHealthService::STATUS_OK, $result['coverage_status']);
        $this->assertSame('paid', $health->planPaymentStatusFromAmounts($result['paid_pln'], $result['planned_pln']));
    }
}
