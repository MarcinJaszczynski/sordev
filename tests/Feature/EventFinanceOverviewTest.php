<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Finance\RecordSettlementCostPaymentAction;
use App\Data\RecordSettlementCostPaymentData;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\User;
use App\Services\EventFinanceOverviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventFinanceOverviewTest extends TestCase
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

    public function test_overview_exposes_three_amounts_per_plan_row(): void
    {
        if (! Schema::hasTable('event_settlement_costs')) {
            $this->markTestSkipped('Brak tabeli event_settlement_costs.');
        }

        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $plan = $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Hotel test',
            'planned_amount' => 1000,
            'planned_amount_pln' => 1000,
            'planned_convert_to_pln' => true,
            'paid_by' => 'office',
            'payment_status' => 'advance_required',
            'order' => 1,
        ]);

        app(RecordSettlementCostPaymentAction::class)(new RecordSettlementCostPaymentData(
            planCost: $plan->fresh(),
            amountPln: 400,
            paymentMethod: 'transfer',
            paidBy: 'office',
            advanceType: 'advance',
        ));

        app(RecordSettlementCostPaymentAction::class)(new RecordSettlementCostPaymentData(
            planCost: $plan->fresh(),
            amountPln: 200,
            paymentMethod: 'cash',
            paidBy: 'pilot',
            advanceType: 'advance',
        ));

        $overview = app(EventFinanceOverviewService::class)->forEvent($event->fresh());

        $this->assertNotEmpty($overview['rows']);
        $row = collect($overview['rows'])->firstWhere('cost_id', $plan->id);
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(1000.0, $row['planned_pln'], 0.01);
        $this->assertEqualsWithDelta(600.0, $row['paid_pln'], 0.01);
        $this->assertCount(2, $row['payments']);
        $this->assertEqualsWithDelta(600.0, $overview['totals']['paid_pln'], 0.01);
        $this->assertNotEmpty($row['payment_hint']);
        $this->assertStringContainsString('zalicz', mb_strtolower($row['payment_hint']));
        $this->assertFalse($row['has_uploaded_file']);
        $this->assertSame('Brak pliku', $row['document_hint']);
    }

    public function test_hide_zero_hides_empty_plan_rows_by_default(): void
    {
        if (! Schema::hasTable('event_settlement_costs')) {
            $this->markTestSkipped('Brak tabeli event_settlement_costs.');
        }

        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $valued = $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Hotel',
            'planned_amount' => 800,
            'planned_amount_pln' => 800,
            'paid_by' => 'office',
            'payment_status' => 'planned',
            'order' => 1,
        ]);

        $zero = $settlement->costs()->create([
            'source_type' => 'program_point',
            'source_id' => 999001,
            'name' => 'Pusty punkt',
            'planned_amount' => 0,
            'planned_amount_pln' => 0,
            'paid_by' => 'office',
            'payment_status' => 'planned',
            'order' => 2,
        ]);

        $hidden = app(EventFinanceOverviewService::class)->forEvent($event->fresh(), hideZero: true);
        $this->assertNotNull(collect($hidden['rows'])->firstWhere('cost_id', $valued->id));
        $this->assertNull(collect($hidden['rows'])->firstWhere('cost_id', $zero->id));
        $this->assertGreaterThanOrEqual(1, (int) $hidden['hidden_zero_count']);
        $this->assertTrue($hidden['hide_zero']);

        $shown = app(EventFinanceOverviewService::class)->forEvent($event->fresh(), hideZero: false);
        $this->assertNotNull(collect($shown['rows'])->firstWhere('cost_id', $zero->id));
        $this->assertSame(0, (int) $shown['hidden_zero_count']);
    }

    public function test_document_number_without_file_is_not_counted_as_uploaded(): void
    {
        if (! Schema::hasTable('event_settlement_costs')) {
            $this->markTestSkipped('Brak tabeli event_settlement_costs.');
        }

        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $plan = $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Przewodnik',
            'planned_amount' => 300,
            'planned_amount_pln' => 300,
            'paid_by' => 'office',
            'payment_status' => 'advance_required',
            'order' => 1,
        ]);

        app(RecordSettlementCostPaymentAction::class)(new RecordSettlementCostPaymentData(
            planCost: $plan->fresh(),
            amountPln: 100,
            paymentMethod: 'transfer',
            paidBy: 'office',
            advanceType: 'advance',
            documentNumber: 'FV/TEST/1',
        ));

        $overview = app(EventFinanceOverviewService::class)->forEvent($event->fresh());
        $row = collect($overview['rows'])->firstWhere('cost_id', $plan->id);
        $this->assertNotNull($row);
        $this->assertFalse($row['has_uploaded_file']);
        $this->assertSame(0, (int) $row['files_count']);
        $this->assertStringContainsString('bez pliku', mb_strtolower($row['document_hint']));
    }

    public function test_pilot_cash_needed_appears_in_overview_summary(): void
    {
        if (! Schema::hasTable('event_settlement_costs') || ! Schema::hasTable('pilot_cash_preparations')) {
            $this->markTestSkipped('Brak tabel gotówki pilota.');
        }

        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Wejściówki na miejscu',
            'planned_amount' => 450,
            'planned_amount_pln' => 450,
            'paid_by' => 'pilot',
            'payment_status' => 'advance_required',
            'order' => 1,
        ]);

        $overview = app(EventFinanceOverviewService::class)->forEvent($event->fresh());
        $pilot = $overview['pilot_cash'] ?? [];

        $this->assertTrue($pilot['has_pilot_costs'] ?? false);
        $this->assertEqualsWithDelta(450.0, (float) ($pilot['needed_pln'] ?? 0), 0.01);
        $this->assertStringContainsString('450', preg_replace('/\s+/u', '', (string) ($pilot['needed_label'] ?? '')));
    }

    public function test_manual_payment_rows_are_not_listed_as_plan_costs(): void
    {
        if (! Schema::hasTable('event_settlement_costs')) {
            $this->markTestSkipped('Brak tabeli event_settlement_costs.');
        }

        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $plan = $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Bus',
            'planned_amount_pln' => 500,
            'planned_amount' => 500,
            'paid_by' => 'office',
            'payment_status' => 'planned',
            'order' => 1,
        ]);

        app(RecordSettlementCostPaymentAction::class)(new RecordSettlementCostPaymentData(
            planCost: $plan->fresh(),
            amountPln: 500,
            paymentMethod: 'transfer',
            paidBy: 'office',
            advanceType: 'full',
        ));

        $payments = EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->where('source_type', 'manual_payment')
            ->count();

        $this->assertSame(1, $payments);

        $overview = app(EventFinanceOverviewService::class)->forEvent($event->fresh());
        $planRows = collect($overview['rows'])->where('source_type', 'manual_payment');
        $this->assertCount(0, $planRows);
    }

    public function test_costs_auto_assign_to_default_groups(): void
    {
        if (! Schema::hasTable('event_settlement_cost_groups')) {
            $this->markTestSkipped('Brak tabeli grup.');
        }

        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $transport = $settlement->costs()->create([
            'source_type' => 'transport',
            'name' => 'Autokar',
            'planned_amount' => 1000,
            'planned_amount_pln' => 1000,
            'paid_by' => 'office',
            'payment_status' => 'planned',
            'order' => 1,
        ]);

        $overview = app(EventFinanceOverviewService::class)->forEvent($event->fresh());
        $this->assertNotEmpty($overview['groups']);

        $transport->refresh();
        $this->assertNotNull($transport->finance_group_id);

        $group = \App\Models\EventSettlementCostGroup::query()->find($transport->finance_group_id);
        $this->assertSame('transport', $group?->key);
    }

    public function test_overview_without_settlement_does_not_create_one(): void
    {
        if (! Schema::hasTable('event_settlements')) {
            $this->markTestSkipped('Brak tabeli event_settlements.');
        }

        $event = Event::factory()->create();
        // Observer tworzy draft settlement — usuwamy, żeby sprawdzić czysty odczyt.
        $event->settlements()->delete();
        $this->assertSame(0, $event->fresh()->settlements()->count());

        $overview = app(EventFinanceOverviewService::class)->forEvent($event->fresh());

        $this->assertNull($overview['settlement_id']);
        $this->assertSame([], $overview['rows']);
        $this->assertSame([], $overview['groups']);
        $this->assertEqualsWithDelta(0.0, $overview['totals']['planned_pln'], 0.01);
        $this->assertEqualsWithDelta(0.0, $overview['totals']['paid_pln'], 0.01);
        $this->assertSame(0, $event->fresh()->settlements()->count());
    }

    public function test_event_finance_page_mount_does_not_create_settlement(): void
    {
        if (! Schema::hasTable('event_settlements')) {
            $this->markTestSkipped('Brak tabeli event_settlements.');
        }

        $event = Event::factory()->create();
        $event->settlements()->delete();

        \Livewire\Livewire::test(\App\Filament\Resources\EventResource\Pages\EventFinance::class, [
            'record' => $event->getKey(),
        ])
            ->assertSuccessful()
            ->assertSee('Brak rozliczenia dla tej imprezy')
            ->assertSee('Utwórz rozliczenie');

        $this->assertSame(0, $event->fresh()->settlements()->count());
    }

    public function test_event_finance_create_settlement_action(): void
    {
        if (! Schema::hasTable('event_settlements')) {
            $this->markTestSkipped('Brak tabeli event_settlements.');
        }

        $event = Event::factory()->create();
        $event->settlements()->delete();

        \Livewire\Livewire::test(\App\Filament\Resources\EventResource\Pages\EventFinance::class, [
            'record' => $event->getKey(),
        ])
            ->call('createSettlement')
            ->assertNotified();

        $this->assertSame(1, $event->fresh()->settlements()->count());
    }

    public function test_event_finance_change_cost_paid_by_preserves_payment_history(): void
    {
        if (! Schema::hasTable('event_settlement_costs')) {
            $this->markTestSkipped('Brak tabeli event_settlement_costs.');
        }

        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $plan = $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Hotel płatnik',
            'planned_amount' => 1000,
            'planned_amount_pln' => 1000,
            'planned_convert_to_pln' => true,
            'paid_by' => 'office',
            'payment_status' => 'advance_required',
            'order' => 1,
        ]);

        app(RecordSettlementCostPaymentAction::class)(new RecordSettlementCostPaymentData(
            planCost: $plan->fresh(),
            amountPln: 300,
            paymentMethod: 'transfer',
            paidBy: 'office',
            advanceType: 'advance',
        ));

        $payment = $settlement->costs()
            ->where('source_type', 'manual_payment')
            ->where('source_id', $plan->id)
            ->first();
        $this->assertNotNull($payment);
        $this->assertSame('office', $payment->paid_by);

        \Livewire\Livewire::test(\App\Filament\Resources\EventResource\Pages\EventFinance::class, [
            'record' => $event->getKey(),
        ])
            ->call('changeCostPaidBy', $plan->id, 'pilot')
            ->assertNotified();

        $this->assertSame('pilot', $plan->fresh()->paid_by);
        $this->assertSame('office', $payment->fresh()->paid_by);
    }

    public function test_office_advance_then_pilot_remaining_shows_in_overview_and_pilot_cash(): void
    {
        if (! Schema::hasTable('event_settlement_costs')) {
            $this->markTestSkipped('Brak tabeli event_settlement_costs.');
        }

        if (! Schema::hasTable('pilot_cash_preparations')) {
            $this->markTestSkipped('Brak tabeli pilot_cash_preparations.');
        }

        $pln = \App\Models\Currency::factory()->pln()->create();
        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $plan = $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Hotel mieszany płatnik',
            'planned_amount' => 1236,
            'planned_amount_pln' => 1236,
            'planned_currency_id' => $pln->id,
            'planned_convert_to_pln' => true,
            'paid_by' => 'office',
            'payment_status' => 'planned',
            'order' => 1,
        ]);

        app(RecordSettlementCostPaymentAction::class)(new RecordSettlementCostPaymentData(
            planCost: $plan->fresh(),
            amountPln: 500,
            paymentMethod: 'transfer',
            paidBy: 'office',
            advanceType: 'advance',
        ));

        $plan->update(['paid_by' => 'pilot']);
        $settlement->recalculatePilotCash();

        $overview = app(EventFinanceOverviewService::class)->forEvent($event->fresh(), hideZero: false);
        $row = collect($overview['rows'])->firstWhere('cost_id', $plan->id);
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(500.0, (float) $row['paid_pln'], 0.01);
        $this->assertEqualsWithDelta(736.0, (float) $row['remaining_pln'], 0.01);
        $this->assertStringContainsString('Zaliczka', (string) ($row['payment_hint'] ?? ''));
        $this->assertStringContainsString('736', (string) $row['remaining_label']);

        $cash = $settlement->fresh()->pilotCashPreparations()->where('currency_id', $pln->id)->first();
        $this->assertNotNull($cash);
        $this->assertEqualsWithDelta(736.0, (float) $cash->calculated_amount, 0.01);
        $this->assertEqualsWithDelta(736.0, (float) $cash->pln_equivalent, 0.01);
    }

    public function test_event_finance_bulk_change_paid_by(): void
    {
        if (! Schema::hasTable('event_settlement_costs')) {
            $this->markTestSkipped('Brak tabeli event_settlement_costs.');
        }

        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $planA = $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Koszt A',
            'planned_amount' => 100,
            'planned_amount_pln' => 100,
            'planned_convert_to_pln' => true,
            'paid_by' => 'office',
            'payment_status' => 'planned',
            'order' => 1,
        ]);
        $planB = $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Koszt B',
            'planned_amount' => 200,
            'planned_amount_pln' => 200,
            'planned_convert_to_pln' => true,
            'paid_by' => 'office',
            'payment_status' => 'planned',
            'order' => 2,
        ]);

        \Livewire\Livewire::test(\App\Filament\Resources\EventResource\Pages\EventFinance::class, [
            'record' => $event->getKey(),
        ])
            ->set('selectedCostIds', [$planA->id, $planB->id])
            ->set('bulkPaidBy', 'pilot')
            ->call('bulkChangePaidBy')
            ->assertNotified();

        $this->assertSame('pilot', $planA->fresh()->paid_by);
        $this->assertSame('pilot', $planB->fresh()->paid_by);
    }

    public function test_overview_search_filters_by_contractor_name(): void
    {
        if (! Schema::hasTable('event_settlement_costs')) {
            $this->markTestSkipped('Brak tabeli event_settlement_costs.');
        }

        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $contractor = \App\Models\Contractor::create(['name' => 'Restauracja Pod Lipą', 'status' => 'active']);

        $match = $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Obiad',
            'contractor_id' => $contractor->id,
            'planned_amount' => 900,
            'planned_amount_pln' => 900,
            'paid_by' => 'office',
            'payment_status' => 'planned',
            'order' => 1,
        ]);

        $other = $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Inny koszt',
            'planned_amount' => 100,
            'planned_amount_pln' => 100,
            'paid_by' => 'office',
            'payment_status' => 'planned',
            'order' => 2,
        ]);

        $overview = app(EventFinanceOverviewService::class)->forEvent(
            $event->fresh(),
            search: 'pod lipą',
        );

        $ids = collect($overview['rows'])->pluck('cost_id')->all();
        $this->assertContains($match->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_overview_sorts_by_contractor(): void
    {
        if (! Schema::hasTable('event_settlement_costs')) {
            $this->markTestSkipped('Brak tabeli event_settlement_costs.');
        }

        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $alpha = \App\Models\Contractor::create(['name' => 'Alpha Hotel', 'status' => 'active']);
        $beta = \App\Models\Contractor::create(['name' => 'Beta Bus', 'status' => 'active']);

        $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Z',
            'contractor_id' => $beta->id,
            'planned_amount' => 100,
            'planned_amount_pln' => 100,
            'paid_by' => 'office',
            'payment_status' => 'planned',
            'order' => 1,
        ]);

        $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'A',
            'contractor_id' => $alpha->id,
            'planned_amount' => 200,
            'planned_amount_pln' => 200,
            'paid_by' => 'office',
            'payment_status' => 'planned',
            'order' => 2,
        ]);

        $overview = app(EventFinanceOverviewService::class)->forEvent(
            $event->fresh(),
            sortBy: EventFinanceOverviewService::SORT_CONTRACTOR,
            sortDir: 'asc',
        );

        $contractors = collect($overview['rows'])->pluck('contractor')->filter()->values()->all();
        $this->assertSame(['Alpha Hotel', 'Beta Bus'], $contractors);
    }
}
