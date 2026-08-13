<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Services\SettlementPaymentHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettlementPaymentInvoiceSettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_payment_below_plan_is_paid_with_savings(): void
    {
        $event = Event::factory()->create(['use_manual_transport_cost' => true, 'manual_transport_cost' => 1000]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $event->refreshActiveSettlementCosts();
        $plan = $settlement->costs()->where('source_type', 'transport')->firstOrFail();

        EventSettlementCost::query()->create([
            'settlement_id' => $settlement->id,
            'source_type' => 'transport_payment',
            'name' => 'Faktura z rabatem',
            'actual_amount_pln' => 900,
            'advance_type' => 'full',
            'payment_status' => 'paid',
            'paid_by' => 'office',
        ]);

        $health = app(SettlementPaymentHealthService::class);
        $result = $health->evaluatePlanCost($plan->fresh(), $settlement->costs()->get());

        $this->assertSame(SettlementPaymentHealthService::STATUS_OK, $result['coverage_status']);
        $this->assertTrue($result['invoice_settled']);
        $this->assertEqualsWithDelta(100.0, $result['savings_pln'], 0.01);
        $this->assertSame(0.0, $result['remaining_pln']);
        $this->assertSame('paid', $health->planPaymentStatusFromAmounts(
            $result['paid_pln'],
            $result['planned_pln'],
            true,
            false,
        ));
    }

    public function test_overpayment_requires_review_until_approved(): void
    {
        $event = Event::factory()->create(['use_manual_transport_cost' => true, 'manual_transport_cost' => 1000]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $event->refreshActiveSettlementCosts();
        $plan = $settlement->costs()->where('source_type', 'transport')->firstOrFail();

        EventSettlementCost::query()->create([
            'settlement_id' => $settlement->id,
            'source_type' => 'transport_payment',
            'name' => 'Nadpłata',
            'actual_amount_pln' => 1200,
            'advance_type' => 'full',
            'payment_status' => 'paid',
            'paid_by' => 'office',
        ]);

        $health = app(SettlementPaymentHealthService::class);
        $all = $settlement->costs()->get();
        $result = $health->evaluatePlanCost($plan->fresh(), $all);

        $this->assertSame(SettlementPaymentHealthService::STATUS_OVERPAYMENT_REVIEW, $result['coverage_status']);
        $this->assertEqualsWithDelta(200.0, $result['overpayment_pln'], 0.01);

        $health->approveOverpayment($plan, 1);
        $resultApproved = $health->evaluatePlanCost($plan->fresh(), $all);

        $this->assertSame(SettlementPaymentHealthService::STATUS_OK, $resultApproved['coverage_status']);
        $this->assertTrue($resultApproved['overpayment_approved']);
    }

    public function test_program_point_price_change_does_not_overwrite_settlement_plan(): void
    {
        $event = Event::factory()->create(['participant_count' => 20]);
        $point = EventProgramPoint::query()->create([
            'event_id' => $event->id,
            'name' => 'Test punkt',
            'day' => 1,
            'order' => 1,
            'unit_price' => 100,
            'group_size' => 1,
            'quantity' => 1,
            'include_in_calculation' => true,
            'include_in_program' => true,
            'active' => true,
            'planned_price' => 2000,
            'calculated_price' => 2000,
            'total_price' => 2000,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $event->refreshActiveSettlementCosts();

        $cost = $settlement->costs()
            ->where('source_type', 'program_point')
            ->where('source_id', $point->id)
            ->firstOrFail();

        $planBefore = (float) $cost->planned_amount_pln;
        $this->assertGreaterThan(0, $planBefore);

        // Ręczna korekta planu w settlement (ustalenia) — sync nie może jej nadpisać.
        $cost->update([
            'planned_amount' => 2500,
            'planned_amount_pln' => 2500,
        ]);

        $point->update(['unit_price' => 180]);
        $event->refreshActiveSettlementCosts();

        $cost->refresh();
        $point->refresh();

        $this->assertEqualsWithDelta(2500.0, (float) $cost->planned_amount_pln, 0.01);
        $this->assertEqualsWithDelta(2000.0, (float) $point->planned_price, 0.01);
    }
}
