<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\User;
use App\Services\PilotSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PilotCashReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'pilot']);
    }

    public function test_cash_reconciliation_uses_provided_minus_actual_expenses(): void
    {
        if (! Schema::hasTable('event_settlement_costs') || ! Schema::hasTable('pilot_cash_preparations')) {
            $this->markTestSkipped('Brak tabel rozliczenia gotówki.');
        }

        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $currencyId = Currency::query()->create([
            'name' => 'PLN',
            'code' => 'PLN',
            'symbol' => 'PLN',
            'exchange_rate' => 1,
        ])->id;

        $settlement->pilotCashPreparations()->create([
            'currency_id' => $currencyId,
            'provided_amount' => 2000,
            'calculated_amount' => 1832,
            'spent_amount' => 1832,
            'status' => 'provided',
        ]);

        $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Wydatek A',
            'planned_amount' => 1000,
            'planned_amount_pln' => 1000,
            'actual_amount' => 900,
            'actual_amount_pln' => 900,
            'planned_currency_id' => $currencyId,
            'actual_currency_id' => $currencyId,
            'paid_by' => 'pilot',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Wydatek B',
            'planned_amount' => 832,
            'planned_amount_pln' => 832,
            'actual_amount' => 826,
            'actual_amount_pln' => 826,
            'planned_currency_id' => $currencyId,
            'actual_currency_id' => $currencyId,
            'paid_by' => 'pilot',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $service = app(PilotSettlementService::class);
        $row = $service->getCashReconciliation($settlement->fresh())->first();

        $this->assertNotNull($row);
        $this->assertSame(2000.0, $row->from_office);
        $this->assertSame(1832.0, $row->planned_expenses);
        $this->assertSame(1726.0, $row->actual_spent);
        $this->assertSame(274.0, $row->to_return);
        $this->assertSame(1726.0, (float) $row->cash->fresh()->spent_amount);
    }

    public function test_pilot_cash_payment_row_updates_spent_and_plan_status(): void
    {
        if (! Schema::hasTable('event_settlement_costs') || ! Schema::hasTable('pilot_cash_preparations')) {
            $this->markTestSkipped('Brak tabel rozliczenia gotówki.');
        }

        Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $event = Event::factory()->create(['status' => Event::STATUS_CONFIRMED]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $plnId = Currency::query()->create([
            'name' => 'PLN', 'code' => 'PLN', 'symbol' => 'PLN', 'exchange_rate' => 1,
        ])->id;

        $settlement->pilotCashPreparations()->create([
            'currency_id' => $plnId,
            'provided_amount' => 1000,
            'calculated_amount' => 500,
            'spent_amount' => 0,
            'status' => 'provided',
        ]);

        $plan = $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Obiad',
            'planned_amount' => 500,
            'planned_amount_pln' => 500,
            'planned_currency_id' => $plnId,
            'paid_by' => 'pilot',
            'payment_status' => 'planned',
            'order' => 1,
        ]);

        app(\App\Actions\Finance\RecordSettlementCostPaymentAction::class)(new \App\Data\RecordSettlementCostPaymentData(
            planCost: $plan->fresh(['plannedCurrency']),
            amountPln: 400,
            paymentMethod: 'transfer', // wymuszone na cash dla pilota
            paidBy: 'pilot',
            advanceType: 'full',
            paidAt: now(),
            paidByUserId: $admin->id,
            amount: 400,
            rate: 1,
            currencyId: $plnId,
        ));

        $plan->refresh();
        $this->assertSame('partially_paid', $plan->payment_status);

        $payment = $settlement->costs()->where('source_type', 'manual_payment')->first();
        $this->assertNotNull($payment);
        $this->assertSame('cash', $payment->payment_method);
        $this->assertSame('pilot', $payment->paid_by);

        $row = app(PilotSettlementService::class)->getCashReconciliation($settlement->fresh())->first();
        $this->assertNotNull($row);
        $this->assertSame(400.0, $row->actual_spent);
        $this->assertSame(400.0, (float) $row->cash->fresh()->spent_amount);
        $this->assertSame(600.0, $row->to_return);

        // Plan bez osobnego actual — nie dubluje wydania.
        $this->assertNull($plan->actual_amount);
    }

    public function test_zeroing_plan_expense_deletes_pilot_cash_payment_instead_of_crashing(): void
    {
        if (! Schema::hasTable('event_settlement_costs') || ! Schema::hasTable('pilot_cash_preparations')) {
            $this->markTestSkipped('Brak tabel rozliczenia gotówki.');
        }

        Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $event = Event::factory()->create(['status' => Event::STATUS_CONFIRMED]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $plnId = Currency::query()->create([
            'name' => 'PLN', 'code' => 'PLN', 'symbol' => 'PLN', 'exchange_rate' => 1,
        ])->id;

        $settlement->pilotCashPreparations()->create([
            'currency_id' => $plnId,
            'provided_amount' => 2000,
            'calculated_amount' => 1236,
            'spent_amount' => 0,
            'status' => 'provided',
        ]);

        $plan = $settlement->costs()->create([
            'source_type' => 'program_point',
            'source_id' => 1058,
            'name' => 'Pilot zagraniczne',
            'planned_amount' => 1236,
            'planned_amount_pln' => 1236,
            'planned_currency_id' => $plnId,
            'paid_by' => 'pilot',
            'payment_status' => 'planned',
            'order' => 1,
        ]);

        app(\App\Actions\Finance\RecordSettlementCostPaymentAction::class)(new \App\Data\RecordSettlementCostPaymentData(
            planCost: $plan->fresh(['plannedCurrency']),
            amountPln: 1236,
            paymentMethod: 'cash',
            paidBy: 'pilot',
            advanceType: 'full',
            paidAt: now(),
            paidByUserId: $admin->id,
            amount: 1236,
            rate: 1,
            currencyId: $plnId,
        ));

        $this->assertSame(1, $settlement->costs()->where('source_type', 'program_point_payment')->count());

        app(PilotSettlementService::class)->updateExpenseLine($event, $plan->fresh(), [
            'actual_amount' => 0,
            'actual_currency_id' => $plnId,
            'payment_method' => 'cash',
        ]);

        $this->assertSame(0, $settlement->costs()->where('source_type', 'program_point_payment')->count());
        $this->assertContains($plan->fresh()->payment_status, ['planned', 'advance_required']);

        $row = app(PilotSettlementService::class)->getCashReconciliation($settlement->fresh())->first();
        $this->assertSame(0.0, $row->actual_spent);
    }

    public function test_recalculate_pilot_cash_applies_currency_exchange_to_balance(): void
    {
        if (! Schema::hasTable('pilot_currency_exchanges')) {
            $this->markTestSkipped('Brak tabeli pilot_currency_exchanges.');
        }

        $pilot = User::factory()->create(['status' => 'active']);
        $event = Event::factory()->create(['assigned_to' => $pilot->id, 'shared_with_pilot' => true]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $plnId = Currency::query()->create([
            'name' => 'PLN', 'code' => 'PLN', 'symbol' => 'PLN', 'exchange_rate' => 1,
        ])->id;
        $eurId = Currency::query()->create([
            'name' => 'EUR', 'code' => 'EUR', 'symbol' => 'EUR', 'exchange_rate' => 4.3,
        ])->id;

        $settlement->pilotCashPreparations()->create([
            'currency_id' => $plnId,
            'provided_amount' => 1000,
            'calculated_amount' => 0,
            'spent_amount' => 0,
            'returned_amount' => 0,
            'status' => 'provided',
        ]);

        $settlement->currencyExchanges()->create([
            'from_currency_id' => $plnId,
            'to_currency_id' => $eurId,
            'from_amount' => 200,
            'to_amount' => 45,
            'exchange_rate' => 4.4444,
            'exchanged_at' => now(),
        ]);

        $settlement->recalculatePilotCash();

        $plnCash = $settlement->pilotCashPreparations()->where('currency_id', $plnId)->first();
        $eurCash = $settlement->pilotCashPreparations()->where('currency_id', $eurId)->first();

        $this->assertNotNull($plnCash);
        $this->assertNotNull($eurCash);
        $this->assertSame(800.0, (float) $plnCash->balance);
        $this->assertSame(45.0, (float) $eurCash->balance);
    }

    public function test_cash_resource_story_narrates_payout_and_exchange_origin(): void
    {
        if (! Schema::hasTable('pilot_currency_exchanges') || ! Schema::hasTable('pilot_cash_preparations')) {
            $this->markTestSkipped('Brak tabel rozliczenia gotówki.');
        }

        $pilot = User::factory()->create(['status' => 'active']);
        $event = Event::factory()->create(['assigned_to' => $pilot->id, 'shared_with_pilot' => true]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $plnId = Currency::query()->create([
            'name' => 'PLN', 'code' => 'PLN', 'symbol' => 'PLN', 'exchange_rate' => 1,
        ])->id;
        $eurId = Currency::query()->create([
            'name' => 'EUR', 'code' => 'EUR', 'symbol' => 'EUR', 'exchange_rate' => 4,
        ])->id;

        $settlement->pilotCashPreparations()->create([
            'currency_id' => $plnId,
            'provided_amount' => 2000,
            'calculated_amount' => 0,
            'spent_amount' => 0,
            'returned_amount' => 0,
            'provided_at' => now()->subDay(),
            'status' => 'provided',
        ]);

        $settlement->currencyExchanges()->create([
            'from_currency_id' => $plnId,
            'to_currency_id' => $eurId,
            'from_amount' => 500,
            'to_amount' => 125,
            'exchange_rate' => 4,
            'exchanged_at' => now(),
        ]);

        $service = app(PilotSettlementService::class);
        $story = $service->getCashResourceStory($settlement->fresh());

        $this->assertTrue($story->has_exchanges);
        $this->assertTrue($story->has_movements);
        $this->assertStringContainsString('1 500,00 PLN (z biura)', $story->summary_label);
        $this->assertStringContainsString('125,00 EUR (z wymiany)', $story->summary_label);
        $this->assertNotEmpty($story->chain_lines);
        $this->assertStringContainsString('Wypłata 2 000,00 PLN', $story->chain_lines[0]);
        $this->assertStringContainsString('Wymiana 500,00 PLN → 125,00 EUR', $story->chain_lines[1]);
        $this->assertCount(2, $story->ledger);

        $rows = $service->getCashReconciliation($settlement->fresh())->keyBy('currency_id');
        $this->assertSame('office', $rows[$plnId]->origin);
        $this->assertSame('exchange', $rows[$eurId]->origin);
        $this->assertSame(1500.0, $rows[$plnId]->available);
        $this->assertSame(125.0, $rows[$eurId]->available);
    }

    public function test_cash_funding_plan_shows_bus_and_exchange_vs_natura(): void
    {
        if (! Schema::hasTable('event_bus_collections') || ! Schema::hasTable('pilot_cash_preparations')) {
            $this->markTestSkipped('Brak tabel gotówki / zbiórek.');
        }

        Currency::clearPlnIdsCache();

        $pilot = User::factory()->create(['status' => 'active']);
        $event = Event::factory()->create(['assigned_to' => $pilot->id, 'shared_with_pilot' => true]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $plnId = Currency::query()->create([
            'name' => 'PLN', 'code' => 'PLN', 'symbol' => 'PLN', 'exchange_rate' => 1,
        ])->id;
        $eurId = Currency::query()->create([
            'name' => 'EUR', 'code' => 'EUR', 'symbol' => 'EUR', 'exchange_rate' => 4,
        ])->id;
        Currency::clearPlnIdsCache();

        $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Gotówka PLN',
            'planned_amount' => 2000,
            'planned_amount_pln' => 2000,
            'planned_currency_id' => $plnId,
            'paid_by' => 'pilot',
            'payment_method' => 'cash',
            'payment_status' => 'pending',
        ]);
        $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Gotówka EUR',
            'planned_amount' => 1300,
            'planned_amount_pln' => 5200,
            'planned_currency_id' => $eurId,
            'planned_rate' => 4,
            'paid_by' => 'pilot',
            'payment_method' => 'cash',
            'payment_status' => 'pending',
        ]);

        \App\Models\EventBusCollection::create([
            'event_id' => $event->id,
            'settlement_id' => $settlement->id,
            'title' => 'EUR w autokarze',
            'amount' => 1000,
            'planned_amount' => 1000,
            'currency_id' => $eurId,
            'participant_count' => 20,
            'amount_per_person' => 50,
            'status' => \App\Models\EventBusCollection::STATUS_PLANNED,
        ]);

        $plan = app(PilotSettlementService::class)->getCashFundingPlan($settlement->fresh());

        $this->assertStringContainsString('2 000,00 PLN z biura', $plan->sources_label);
        $this->assertStringContainsString('300,00 EUR z biura', $plan->sources_label);
        $this->assertStringContainsString('1 000,00 EUR z autokaru (plan)', $plan->sources_label);
        $this->assertTrue($plan->has_bus);
        $this->assertFalse($plan->bus_covers_need);
        $this->assertStringContainsString('1 000,00 EUR (plan)', $plan->bus_label);
        $this->assertNull($plan->bus_surplus_label);
        $this->assertStringContainsString('2 000,00 PLN', $plan->needed_label);
        $this->assertStringContainsString('1 300,00 EUR', $plan->needed_label);

        // 2000 PLN + 300 EUR × 4 = 3200 PLN na wymianę
        $this->assertSame('3 200,00 PLN (w tym 1 200,00 na zakup)', $plan->payout_exchange_label);
        $this->assertStringContainsString('1 200,00 PLN na zakup 300,00 EUR', (string) $plan->payout_exchange_detail);
        $this->assertSame('2 000,00 PLN + 300,00 EUR', $plan->payout_natura_label);
        $this->assertTrue($plan->has_office_gap);
        $this->assertSame($plan->payout_exchange_label, $plan->cash_to_pay_label);
    }

    public function test_cash_funding_plan_bus_surplus_is_informational_and_clears_office_gap(): void
    {
        if (! Schema::hasTable('event_bus_collections') || ! Schema::hasTable('pilot_cash_preparations')) {
            $this->markTestSkipped('Brak tabel gotówki / zbiórek.');
        }

        Currency::clearPlnIdsCache();

        $pilot = User::factory()->create(['status' => 'active']);
        $event = Event::factory()->create(['assigned_to' => $pilot->id]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $eurId = Currency::query()->create([
            'name' => 'EUR', 'code' => 'EUR', 'symbol' => 'EUR', 'exchange_rate' => 4,
        ])->id;

        $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Koszt EUR',
            'planned_amount' => 1800,
            'planned_amount_pln' => 7200,
            'planned_currency_id' => $eurId,
            'planned_rate' => 4,
            'paid_by' => 'pilot',
            'payment_method' => 'cash',
            'payment_status' => 'pending',
        ]);

        \App\Models\EventBusCollection::create([
            'event_id' => $event->id,
            'settlement_id' => $settlement->id,
            'title' => 'Zbiórka EUR',
            'amount' => 4400,
            'planned_amount' => 4400,
            'currency_id' => $eurId,
            'participant_count' => 44,
            'amount_per_person' => 100,
            'status' => \App\Models\EventBusCollection::STATUS_PLANNED,
        ]);

        $plan = app(PilotSettlementService::class)->getCashFundingPlan($settlement->fresh());

        $this->assertStringContainsString('4 400,00 EUR z autokaru (plan)', $plan->sources_label);
        $this->assertStringContainsString('4 400,00 EUR (plan)', $plan->bus_label);
        $this->assertSame('1 800,00 EUR', $plan->needed_label);
        $this->assertTrue($plan->bus_covers_need);
        $this->assertStringContainsString('Zbiórka jest o 2 600,00 EUR wyższa niż potrzeba z programu (1 800,00 EUR)', $plan->bus_surplus_label);
        $this->assertFalse($plan->has_office_gap);
        $this->assertSame('—', $plan->cash_to_pay_label);
        $this->assertSame('—', $plan->payout_exchange_label);
        $this->assertSame('—', $plan->payout_natura_label);
    }
}
