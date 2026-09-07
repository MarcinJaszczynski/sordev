<?php

namespace Tests\Feature;

use App\Actions\Finance\UpdateSettlementCostPlanAction;
use App\Data\UpdateSettlementCostPlanData;
use App\Livewire\SettlementAggregateFinancePanel;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\User;
use App\Services\EventFinanceOverviewService;
use App\Services\SettlementAggregateFinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SettlementAggregateFinanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');
        $this->actingAs($admin);
    }

    public function test_transport_plan_advance_and_payment_persist(): void
    {
        $event = Event::factory()->create([
            'transfer_km' => 100,
            'program_km' => 50,
            'participant_count' => 30,
            'use_manual_transport_cost' => true,
            'manual_transport_cost' => 5000,
        ]);

        $service = app(SettlementAggregateFinanceService::class);
        $base = $service->ensureBaseCost($event, 'transport');

        $this->assertNotNull($base);
        $this->assertGreaterThan(0, (float) $base->planned_amount_pln);

        $form = $service->buildFormData($event->fresh(), 'transport');
        $form['settlement_planned_amount'] = (float) $base->planned_amount;
        $form['settlement_advance_amount'] = 500;
        $form['settlement_advance_paid_amount'] = 500;
        $form['settlement_advance_paid_amount_pln'] = 500;

        $service->persist($event->fresh(), 'transport', $form, false);

        $base = $base->fresh();
        $paymentType = $service->paymentSourceTypeForPlan($base);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event->fresh());

        $this->assertTrue($settlement->costs()
            ->where('source_type', $paymentType)
            ->when(
                $base->source_id !== null,
                fn ($q) => $q->where('source_id', (int) $base->source_id),
                fn ($q) => $q->whereNull('source_id'),
            )
            ->where('advance_amount', 500)
            ->exists());

        $form = $service->buildFormData($event->fresh(), 'transport');
        $form['payment_entries'] = [[
            'paid_by' => 'office',
            'due_date' => null,
            'actual_amount' => 1000,
            'actual_currency_id' => $form['settlement_planned_currency_id'],
            'actual_rate' => 1,
            'actual_amount_pln' => 1000,
            'payment_method' => 'transfer',
            'document_id' => null,
            'document_type' => null,
            'document_number' => 'FV/1',
            'document_files' => [],
            'paid_at' => now()->toDateString(),
            'notes' => null,
        ]];

        $summary = $service->persist($event->fresh(), 'transport', $form, true);

        $this->assertGreaterThan(0, $summary['paid_pln']);
    }

    public function test_transport_groups_by_contractor_and_migrates_legacy(): void
    {
        $contractor = \App\Models\Contractor::create([
            'name' => 'Autokary Alfa',
            'status' => 'active',
        ]);

        $event = Event::factory()->create([
            'use_manual_transport_cost' => true,
            'manual_transport_cost' => 5000,
            'transport_contractor_id' => $contractor->id,
        ]);

        $sync = app(\App\Services\TransportContractorSettlementSync::class);
        $cost = $sync->ensureForContractor($event, (int) $contractor->id);

        $this->assertNotNull($cost);
        $this->assertSame('transport_contractor', $cost->source_type);
        $this->assertSame((int) $contractor->id, (int) $cost->source_id);
        $this->assertEqualsWithDelta(5000.0, (float) $cost->planned_amount_pln, 0.01);

        $groups = $sync->financeGroups($event->fresh());
        $this->assertCount(1, $groups);
        $this->assertSame((int) $contractor->id, (int) $groups[0]['contractor_id']);
        $this->assertTrue($groups[0]['is_primary']);

        Livewire::test(SettlementAggregateFinancePanel::class, [
            'eventId' => $event->getKey(),
            'aggregateType' => 'transport',
        ])
            ->assertSee('Płatności wg przewoźnika')
            ->assertSee('Autokary Alfa')
            ->call('openTransportGroupFinance', (int) $contractor->id)
            ->assertSet('selectedCostId', (int) $cost->id)
            ->assertSet('selectedRow.supports_reservation', true);
    }

    public function test_transport_reference_total_is_kosztorys_not_plan(): void
    {
        $event = Event::factory()->create([
            'transfer_km' => 100,
            'program_km' => 50,
            'participant_count' => 30,
            'use_manual_transport_cost' => true,
            'manual_transport_cost' => 5000,
        ]);

        $service = app(SettlementAggregateFinanceService::class);
        $base = $service->ensureBaseCost($event, 'transport');
        $this->assertNotNull($base);

        app(UpdateSettlementCostPlanAction::class)(new UpdateSettlementCostPlanData(
            planCost: $base->fresh(),
            plannedAmountPln: 4000,
            paidBy: 'office',
            plannedAmount: 4000,
        ));

        $this->assertEqualsWithDelta(
            5000.0,
            $service->resolveReferenceTotalPln($event->fresh(), 'transport'),
            0.01,
        );

        EventFinanceOverviewService::forgetOverviewCacheForEvent((int) $event->id);
        $overview = app(EventFinanceOverviewService::class)->forEvent($event->fresh(), hideZero: false);
        $row = collect($overview['rows'])->first(
            fn (array $candidate): bool => in_array($candidate['source_type'] ?? null, ['transport', 'transport_contractor'], true)
        );

        $this->assertNotNull($row);
        $this->assertTrue((bool) ($row['supports_reservation'] ?? false));
        $this->assertEqualsWithDelta(5000.0, (float) $row['calculation_pln'], 0.01);
        $this->assertEqualsWithDelta(4000.0, (float) $row['planned_pln'], 0.01);

        // buildFormData woła ensureBaseCost (sync może nadpisać plan) — kosztorys musi zostać 5000.
        $form = $service->buildFormData($event->fresh(), 'transport');
        $this->assertEqualsWithDelta(5000.0, (float) $form['event_point_total'], 0.01);
    }

    public function test_transport_finance_panel_opens_settlement_drawer(): void
    {
        $event = Event::factory()->create([
            'transfer_km' => 100,
            'program_km' => 50,
            'participant_count' => 30,
            'use_manual_transport_cost' => true,
            'manual_transport_cost' => 5000,
        ]);

        $base = app(SettlementAggregateFinanceService::class)->ensureBaseCost($event, 'transport');
        $this->assertNotNull($base);

        Livewire::test(SettlementAggregateFinancePanel::class, [
            'eventId' => $event->getKey(),
            'aggregateType' => 'transport',
            'heading' => 'Finanse transportu',
        ])
            ->assertSee('Planowane')
            ->assertSee('Zaliczka')
            ->assertSee('Wpłaty')
            ->call('openAggregateFinance', 'advance')
            ->assertSet('selectedCostId', (int) $base->id)
            ->assertSet('showPaymentForm', true)
            ->assertSet('paymentForm.advance_type', 'advance')
            ->call('closeCost')
            ->assertSet('selectedCostId', null)
            ->call('openAggregateFinance', 'payment')
            ->assertSet('selectedCostId', (int) $base->id)
            ->assertSet('showPaymentForm', true)
            ->call('closeCost')
            ->call('openAggregateFinance', 'plan')
            ->assertSet('selectedCostId', (int) $base->id)
            ->assertSet('showPlanForm', true);
    }

    public function test_transport_drawer_records_advance_and_second_payment(): void
    {
        $event = Event::factory()->create([
            'use_manual_transport_cost' => true,
            'manual_transport_cost' => 5000,
        ]);

        $base = app(SettlementAggregateFinanceService::class)->ensureBaseCost($event, 'transport');
        $this->assertNotNull($base);

        $component = Livewire::test(SettlementAggregateFinancePanel::class, [
            'eventId' => $event->getKey(),
            'aggregateType' => 'transport',
        ])
            ->call('openAggregateFinance', 'advance')
            ->set('paymentForm.amount_pln', 500)
            ->set('paymentForm.payment_method', 'transfer')
            ->set('paymentForm.paid_at', now()->toDateString())
            ->call('savePayment')
            ->assertHasNoErrors();

        $settlement = EventSettlement::findOrCreateActiveForEvent($event->fresh());
        $paymentType = $base->source_type.'_payment';
        $payments = $settlement->costs()
            ->where('source_type', $paymentType)
            ->when(
                $base->source_id !== null,
                fn ($q) => $q->where('source_id', (int) $base->source_id),
                fn ($q) => $q->whereNull('source_id'),
            )
            ->get();

        $this->assertCount(1, $payments);
        $this->assertEqualsWithDelta(500.0, (float) $payments->first()->actual_amount_pln, 0.01);

        $component
            ->call('openAggregateFinance', 'payment')
            ->set('paymentForm.advance_type', 'supplement')
            ->set('paymentForm.amount_pln', 1500)
            ->set('paymentForm.payment_method', 'transfer')
            ->set('paymentForm.paid_at', now()->toDateString())
            ->call('savePayment')
            ->assertHasNoErrors();

        $this->assertSame(
            2,
            $settlement->fresh()->costs()
                ->where('source_type', $paymentType)
                ->when(
                    $base->source_id !== null,
                    fn ($q) => $q->where('source_id', (int) $base->source_id),
                    fn ($q) => $q->whereNull('source_id'),
                )
                ->count()
        );
    }
}
