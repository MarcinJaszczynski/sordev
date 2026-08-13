<?php

namespace Tests\Feature;

use App\Actions\Finance\RecordSettlementCostPaymentAction;
use App\Data\RecordSettlementCostPaymentData;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\User;
use App\Services\EventFinanceOverviewService;
use App\Services\SettlementPaymentHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RecordSettlementCostPaymentActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_payments_mark_plan_paid_from_amounts(): void
    {
        Role::findOrCreate('admin');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $event = Event::factory()->create(['use_manual_transport_cost' => true, 'manual_transport_cost' => 1000]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $event->refreshActiveSettlementCosts();
        $plan = $settlement->costs()->where('source_type', 'transport')->first();
        $this->assertNotNull($plan);

        $action = app(RecordSettlementCostPaymentAction::class);

        $action(new RecordSettlementCostPaymentData(
            planCost: $plan,
            amountPln: 400,
            paymentMethod: 'transfer',
            paidBy: 'office',
            advanceType: 'advance',
            paidAt: now(),
            notes: 'zaliczka',
        ));

        $plan->refresh();
        $this->assertSame('partially_paid', $plan->payment_status);

        $action(new RecordSettlementCostPaymentData(
            planCost: $plan->fresh(),
            amountPln: 600,
            paymentMethod: 'transfer',
            paidBy: 'office',
            advanceType: 'final',
            paidAt: now(),
            notes: 'dopłata',
        ));

        $plan->refresh();
        $this->assertSame('paid', $plan->payment_status);

        $health = app(SettlementPaymentHealthService::class);
        $eval = $health->evaluatePlanCost($plan, $settlement->fresh()->costs()->get());
        $this->assertSame(SettlementPaymentHealthService::STATUS_OK, $eval['coverage_status']);
        $this->assertSame(1000.0, $eval['paid_pln']);
    }

    public function test_updating_flag_alone_does_not_create_paid_coverage(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['use_manual_transport_cost' => true, 'manual_transport_cost' => 800]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $event->refreshActiveSettlementCosts();
        $plan = $settlement->costs()->where('source_type', 'transport')->first();

        // Symulacja starego „Opłacona” bez stosu wpłat.
        $plan->update(['payment_status' => 'paid', 'actual_amount_pln' => null]);

        $health = app(SettlementPaymentHealthService::class);
        $eval = $health->evaluatePlanCost($plan->fresh(), $settlement->costs()->get());

        $this->assertNotSame(SettlementPaymentHealthService::STATUS_OK, $eval['coverage_status']);
        $this->assertSame(0.0, $eval['paid_pln']);
        $this->assertSame(0, EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->where('source_type', 'transport_payment')
            ->count());
    }

    public function test_foreign_currency_advance_stores_amount_and_rate(): void
    {
        Role::findOrCreate('admin');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $eur = Currency::factory()->eur()->create(['exchange_rate' => 4.35]);
        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $plan = $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Bilety EUR',
            'planned_amount' => 111,
            'planned_amount_pln' => null,
            'planned_currency_id' => $eur->id,
            'planned_convert_to_pln' => false,
            'planned_rate' => 4.35,
            'paid_by' => 'office',
            'payment_status' => 'planned',
            'order' => 1,
        ]);

        $action = app(RecordSettlementCostPaymentAction::class);
        $payment = $action(new RecordSettlementCostPaymentData(
            planCost: $plan->fresh(['plannedCurrency']),
            amountPln: 241.425,
            paymentMethod: 'transfer',
            paidBy: 'office',
            advanceType: 'advance',
            paidAt: now(),
            amount: 55.5,
            rate: 4.35,
            currencyId: $eur->id,
        ));

        $this->assertEqualsWithDelta(55.5, (float) $payment->actual_amount, 0.01);
        $this->assertEqualsWithDelta(4.35, (float) $payment->actual_rate, 0.0001);
        $this->assertEqualsWithDelta(241.43, (float) $payment->actual_amount_pln, 0.01);
        $this->assertSame($eur->id, (int) $payment->actual_currency_id);

        $plan->refresh();
        $this->assertSame('partially_paid', $plan->payment_status);

        $overview = app(EventFinanceOverviewService::class)->forEvent($event->fresh(), hideZero: false);
        $row = collect($overview['rows'])->firstWhere('cost_id', $plan->id);
        $this->assertNotNull($row);
        $this->assertStringContainsString('EUR', (string) $row['paid_label']);
        $this->assertNotEmpty($row['payments']);
        $this->assertEqualsWithDelta(55.5, (float) $row['payments'][0]['amount'], 0.01);
        $this->assertStringContainsString('EUR', (string) $row['payments'][0]['amount_label']);
    }

    public function test_payment_can_be_updated_and_deleted(): void
    {
        Role::findOrCreate('admin');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $plan = $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Hotel',
            'planned_amount' => 1000,
            'planned_amount_pln' => 1000,
            'planned_convert_to_pln' => true,
            'paid_by' => 'office',
            'payment_status' => 'planned',
            'order' => 1,
        ]);

        $payment = app(RecordSettlementCostPaymentAction::class)(new RecordSettlementCostPaymentData(
            planCost: $plan->fresh(),
            amountPln: 400,
            paymentMethod: 'transfer',
            paidBy: 'office',
            advanceType: 'advance',
        ));

        app(\App\Actions\Finance\UpdateSettlementCostPaymentAction::class)(new \App\Data\UpdateSettlementCostPaymentData(
            payment: $payment->fresh(),
            amountPln: 500,
            paymentMethod: 'transfer',
            paidBy: 'office',
            advanceType: 'advance',
        ));

        $this->assertEqualsWithDelta(500.0, (float) $payment->fresh()->actual_amount_pln, 0.01);
        $this->assertSame('partially_paid', $plan->fresh()->payment_status);

        app(\App\Actions\Finance\DeleteSettlementCostPaymentAction::class)($payment->fresh());

        $this->assertNull(EventSettlementCost::query()->find($payment->id));
        $this->assertSame('advance_required', $plan->fresh()->payment_status);
    }
}
