<?php

namespace Tests\Feature;

use App\Filament\Pilot\Pages\PilotAdvancePage;
use App\Filament\Pilot\Pages\PilotSettlementPage;
use App\Livewire\PilotCashDesk;
use App\Models\Currency;
use App\Models\Event;
use App\Models\PilotAdvanceLine;
use App\Models\User;
use App\Services\PilotAdvanceService;
use App\Services\PilotSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PilotAdvanceMultiCurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'pilot']);
    }

    public function test_approve_payment_syncs_multiple_currencies_to_cash_preparations(): void
    {
        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $plnId = Currency::query()->create([
            'name' => 'PLN', 'code' => 'PLN', 'symbol' => 'PLN', 'exchange_rate' => 1,
        ])->id;
        $eurId = Currency::query()->create([
            'name' => 'EUR', 'code' => 'EUR', 'symbol' => 'EUR', 'exchange_rate' => 4.3,
        ])->id;

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
            'pilot_funds_paid' => false,
        ]);

        app(PilotAdvanceService::class)->approvePayment($event, paidLines: [
            ['amount' => 1000, 'currency_id' => $plnId],
            ['amount' => 50, 'currency_id' => $eurId],
        ]);

        $event->refresh();

        $this->assertTrue($event->pilot_funds_paid);
        $this->assertSame(2, PilotAdvanceLine::query()->where('event_id', $event->id)->where('phase', 'paid')->count());

        $settlement = \App\Models\EventSettlement::findOrCreateActiveForEvent($event);
        $cash = $settlement->pilotCashPreparations()->get()->keyBy('currency_id');

        $this->assertSame(1000.0, (float) $cash[$plnId]->provided_amount);
        $this->assertSame(50.0, (float) $cash[$eurId]->provided_amount);
    }

    public function test_pilot_can_record_currency_exchange_from_cash_desk(): void
    {
        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $plnId = Currency::query()->create([
            'name' => 'PLN', 'code' => 'PLN', 'symbol' => 'PLN', 'exchange_rate' => 1,
        ])->id;
        $eurId = Currency::query()->create([
            'name' => 'EUR', 'code' => 'EUR', 'symbol' => 'EUR', 'exchange_rate' => 4.3,
        ])->id;

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
            'pilot_funds_paid' => true,
            'pilot_portal_show_currency_exchange' => true,
        ]);

        app(PilotAdvanceService::class)->approvePayment($event, paidLines: [
            ['amount' => 1000, 'currency_id' => $plnId],
        ]);

        Livewire::actingAs($pilot)
            ->test(PilotCashDesk::class, ['event' => $event, 'context' => 'pilot', 'compact' => true])
            ->set('exchangeFromCurrencyId', $plnId)
            ->set('exchangeToCurrencyId', $eurId)
            ->set('exchangeFromAmount', '200')
            ->set('exchangeToAmount', '45')
            ->call('saveExchange')
            ->assertNotified();

        $settlement = \App\Models\EventSettlement::findOrCreateActiveForEvent($event->fresh());
        $this->assertSame(1, $settlement->currencyExchanges()->count());

        $row = app(PilotSettlementService::class)->getCashReconciliation($settlement->fresh())->keyBy('currency_id');
        $this->assertSame(800.0, $row[$plnId]->office_provided);
        $this->assertSame(45.0, $row[$eurId]->office_provided);
    }

    public function test_currency_exchange_with_rate_debits_from_amount_as_to_times_rate(): void
    {
        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $plnId = Currency::query()->create([
            'name' => 'PLN', 'code' => 'PLN', 'symbol' => 'PLN', 'exchange_rate' => 1,
        ])->id;
        $eurId = Currency::query()->create([
            'name' => 'EUR', 'code' => 'EUR', 'symbol' => 'EUR', 'exchange_rate' => 4.3,
        ])->id;

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
            'pilot_funds_paid' => true,
            'pilot_portal_show_currency_exchange' => true,
        ]);

        app(PilotAdvanceService::class)->approvePayment($event, paidLines: [
            ['amount' => 2500, 'currency_id' => $plnId],
            ['amount' => 500, 'currency_id' => $eurId],
        ]);

        // Kwota oddana 500, ale przy kursie 4.3 i 100 EUR schodzi 430 PLN (70 zostaje).
        Livewire::actingAs($pilot)
            ->test(PilotCashDesk::class, ['event' => $event, 'context' => 'pilot', 'compact' => true])
            ->set('exchangeFromCurrencyId', $plnId)
            ->set('exchangeToCurrencyId', $eurId)
            ->set('exchangeFromAmount', '500')
            ->set('exchangeToAmount', '100')
            ->set('exchangeRate', '4.3')
            ->call('saveExchange')
            ->assertNotified();

        $settlement = \App\Models\EventSettlement::findOrCreateActiveForEvent($event->fresh());
        $exchange = $settlement->currencyExchanges()->first();
        $this->assertNotNull($exchange);
        $this->assertSame(430.0, (float) $exchange->from_amount);
        $this->assertSame(100.0, (float) $exchange->to_amount);
        $this->assertSame(4.3, (float) $exchange->exchange_rate);

        $row = app(PilotSettlementService::class)->getCashReconciliation($settlement->fresh())->keyBy('currency_id');
        $this->assertSame(2070.0, $row[$plnId]->office_provided); // 2500 - 430
        $this->assertSame(600.0, $row[$eurId]->office_provided); // 500 + 100
        $this->assertSame(430.0, $row[$plnId]->exchange_out);
        $this->assertSame(100.0, $row[$eurId]->exchange_in);
    }

    public function test_normalize_currency_exchange_amounts_prefers_to_times_rate(): void
    {
        [$from, $to, $rate] = PilotSettlementService::normalizeCurrencyExchangeAmounts(500, 100, 4.3);

        $this->assertSame(430.0, $from);
        $this->assertSame(100.0, $to);
        $this->assertSame(4.3, $rate);

        [$fromNoRate, $toNoRate, $impliedRate] = PilotSettlementService::normalizeCurrencyExchangeAmounts(200, 45, null);

        $this->assertSame(200.0, $fromNoRate);
        $this->assertSame(45.0, $toNoRate);
        // Kurs = jednostki źródłowe za 1 jednostkę docelową (from / to).
        $this->assertEqualsWithDelta(200 / 45, $impliedRate, 0.00001);
    }

    public function test_pilot_can_edit_and_delete_currency_exchange_from_cash_desk(): void
    {
        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $plnId = Currency::query()->create([
            'name' => 'PLN', 'code' => 'PLN', 'symbol' => 'PLN', 'exchange_rate' => 1,
        ])->id;
        $eurId = Currency::query()->create([
            'name' => 'EUR', 'code' => 'EUR', 'symbol' => 'EUR', 'exchange_rate' => 4.3,
        ])->id;

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
            'pilot_funds_paid' => true,
            'pilot_portal_show_currency_exchange' => true,
        ]);

        app(PilotAdvanceService::class)->approvePayment($event, paidLines: [
            ['amount' => 1000, 'currency_id' => $plnId],
        ]);

        $this->actingAs($pilot);

        $service = app(PilotSettlementService::class);
        $exchange = $service->recordCurrencyExchange($event, [
            'from_currency_id' => $plnId,
            'to_currency_id' => $eurId,
            'from_amount' => 200,
            'to_amount' => 45,
        ]);

        Livewire::actingAs($pilot)
            ->test(PilotCashDesk::class, ['event' => $event, 'context' => 'pilot', 'compact' => true])
            ->call('editExchange', $exchange->id)
            ->assertSet('editingExchangeId', $exchange->id)
            ->set('exchangeToAmount', '50')
            ->set('exchangeRate', '4')
            ->call('saveExchange')
            ->assertNotified();

        $exchange->refresh();
        $this->assertSame(200.0, (float) $exchange->from_amount); // 50 * 4
        $this->assertSame(50.0, (float) $exchange->to_amount);

        $row = $service->getCashReconciliation(
            \App\Models\EventSettlement::findOrCreateActiveForEvent($event->fresh())
        )->keyBy('currency_id');
        $this->assertSame(800.0, $row[$plnId]->office_provided);
        $this->assertSame(50.0, $row[$eurId]->office_provided);

        Livewire::actingAs($pilot)
            ->test(PilotCashDesk::class, ['event' => $event, 'context' => 'pilot', 'compact' => true])
            ->call('deleteExchange', $exchange->id)
            ->assertNotified();

        $settlement = \App\Models\EventSettlement::findOrCreateActiveForEvent($event->fresh());
        $this->assertSame(0, $settlement->currencyExchanges()->count());

        $rowAfter = $service->getCashReconciliation($settlement->fresh())->keyBy('currency_id');
        $this->assertSame(1000.0, $rowAfter[$plnId]->office_provided);
        $this->assertFalse($rowAfter->has($eurId) && (float) ($rowAfter[$eurId]->exchange_in ?? 0) > 0);
    }

    public function test_cash_reconciliation_exposes_needed_and_prefill_uses_it(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $plnId = Currency::query()->create([
            'name' => 'PLN', 'code' => 'PLN', 'symbol' => 'PLN', 'exchange_rate' => 1,
        ])->id;

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
        ]);

        $settlement = \App\Models\EventSettlement::findOrCreateActiveForEvent($event);
        $settlement->costs()->create([
            'source_type' => 'program_point',
            'source_id' => 9001,
            'name' => 'Pilot zagraniczne',
            'planned_amount' => 2922,
            'planned_amount_pln' => 2922,
            'planned_currency_id' => $plnId,
            'paid_by' => 'pilot',
            'payment_status' => 'planned',
            'order' => 1,
        ]);

        $settlement->recalculatePilotCash();

        $row = app(PilotSettlementService::class)->getCashReconciliation($settlement->fresh())->keyBy('currency_id');
        $this->assertSame(2922.0, $row[$plnId]->needed);
        $this->assertSame(2922.0, $row[$plnId]->planned_expenses);
        $this->assertSame(0.0, $row[$plnId]->from_office);

        Livewire::actingAs($admin)
            ->test(PilotCashDesk::class, ['event' => $event, 'context' => 'admin', 'compact' => false])
            ->call('prefillPayoutFromCalculation')
            ->assertSet('payoutCurrencyId', $plnId)
            ->assertSet('payoutAmount', '2922');
    }

    public function test_currency_exchange_requires_office_cash_balance(): void
    {
        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');
        $this->actingAs($pilot);

        $plnId = Currency::query()->create([
            'name' => 'PLN', 'code' => 'PLN', 'symbol' => 'PLN', 'exchange_rate' => 1,
        ])->id;
        $eurId = Currency::query()->create([
            'name' => 'EUR', 'code' => 'EUR', 'symbol' => 'EUR', 'exchange_rate' => 4.3,
        ])->id;

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
        ]);

        // Bez wypłaty z biura — wymiana musi być zablokowana.
        try {
            app(PilotSettlementService::class)->recordCurrencyExchange($event, [
                'from_currency_id' => $plnId,
                'to_currency_id' => $eurId,
                'from_amount' => 500,
                'to_amount' => 100,
                'exchange_rate' => 4.3,
            ]);
            $this->fail('Oczekiwano ValidationException przy braku gotówki.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('Brak gotówki', collect($e->errors())->flatten()->first() ?? '');
        }

        app(PilotAdvanceService::class)->approvePayment($event, paidLines: [
            ['amount' => 1000, 'currency_id' => $plnId],
        ]);

        $exchange = app(PilotSettlementService::class)->recordCurrencyExchange($event, [
            'from_currency_id' => $plnId,
            'to_currency_id' => $eurId,
            'from_amount' => 500,
            'to_amount' => 100,
            'exchange_rate' => 4.3,
        ]);

        $this->assertSame(430.0, (float) $exchange->from_amount);
    }

    public function test_repair_normalizes_legacy_exchange_from_amount_by_rate(): void
    {
        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');
        $this->actingAs($pilot);

        $plnId = Currency::query()->create([
            'name' => 'PLN', 'code' => 'PLN', 'symbol' => 'PLN', 'exchange_rate' => 1,
        ])->id;
        $eurId = Currency::query()->create([
            'name' => 'EUR', 'code' => 'EUR', 'symbol' => 'EUR', 'exchange_rate' => 4.3,
        ])->id;

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
        ]);

        app(PilotAdvanceService::class)->approvePayment($event, paidLines: [
            ['amount' => 1000, 'currency_id' => $plnId],
        ]);

        $settlement = \App\Models\EventSettlement::findOrCreateActiveForEvent($event);
        $settlement->currencyExchanges()->create([
            'from_currency_id' => $plnId,
            'to_currency_id' => $eurId,
            'from_amount' => 500, // legacy — powinno być 430 przy kursie 4.3
            'to_amount' => 100,
            'exchange_rate' => 4.3,
            'exchanged_at' => now(),
        ]);

        $row = app(PilotSettlementService::class)->getCashReconciliation($settlement->fresh())->keyBy('currency_id');
        $this->assertSame(430.0, (float) $settlement->currencyExchanges()->first()->from_amount);
        $this->assertSame(570.0, $row[$plnId]->office_provided); // 1000 - 430
        $this->assertSame(100.0, $row[$eurId]->office_provided);
    }

    public function test_office_advance_reduces_needed_and_marks_top_up_on_expense_line(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $plnId = Currency::query()->create([
            'name' => 'PLN', 'code' => 'PLN', 'symbol' => 'PLN', 'exchange_rate' => 1,
        ])->id;

        $event = Event::factory()->create(['status' => Event::STATUS_CONFIRMED]);
        $settlement = \App\Models\EventSettlement::findOrCreateActiveForEvent($event);

        $hotel = $settlement->costs()->create([
            'source_type' => 'program_point',
            'source_id' => 8801,
            'name' => 'Hotel',
            'planned_amount' => 800,
            'planned_amount_pln' => 800,
            'planned_currency_id' => $plnId,
            'paid_by' => 'pilot',
            'payment_status' => 'planned',
            'order' => 1,
        ]);

        app(\App\Actions\Finance\RecordSettlementCostPaymentAction::class)(new \App\Data\RecordSettlementCostPaymentData(
            planCost: $hotel->fresh(['plannedCurrency']),
            amountPln: 300,
            paymentMethod: 'transfer',
            paidBy: 'office',
            advanceType: 'advance',
            paidAt: now(),
            paidByUserId: $admin->id,
            amount: 300,
            rate: 1,
            currencyId: $plnId,
        ));

        $settlement->recalculatePilotCash();
        $row = app(PilotSettlementService::class)->getCashReconciliation($settlement->fresh())->keyBy('currency_id');

        $this->assertSame(500.0, $row[$plnId]->needed);
        $this->assertSame(800.0, $row[$plnId]->plan_total);
        $this->assertSame(300.0, $row[$plnId]->office_advances_on_costs);
        $this->assertTrue($row[$plnId]->is_top_up);

        $line = app(PilotSettlementService::class)->getExpenseLines($settlement->fresh())->first();
        $this->assertSame(300.0, (float) $line->ledger_office_paid);
        $this->assertSame(500.0, (float) $line->ledger_pilot_due);
        $this->assertTrue((bool) $line->ledger_is_top_up);

        $totals = app(PilotSettlementService::class)->summarizeExpenseLines(
            app(PilotSettlementService::class)->getExpenseLines($settlement->fresh())
        );
        $this->assertCount(1, $totals);
        $this->assertSame('PLN', $totals[0]['currency']);
        $this->assertSame(800.0, $totals[0]['planned']);
        $this->assertSame(300.0, $totals[0]['office_paid']);
        $this->assertSame(0.0, $totals[0]['pilot_paid']);
        $this->assertSame(500.0, $totals[0]['pilot_due']);
    }

    public function test_admin_can_edit_and_delete_office_cash_payout(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $plnId = Currency::query()->create([
            'name' => 'PLN', 'code' => 'PLN', 'symbol' => 'PLN', 'exchange_rate' => 1,
        ])->id;

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
        ]);

        $this->actingAs($admin);
        app(PilotAdvanceService::class)->recordOfficeCashPayout($event, [
            'amount' => 2500,
            'currency_id' => $plnId,
            'provided_at' => '2026-08-08',
        ]);

        Livewire::actingAs($admin)
            ->test(PilotCashDesk::class, ['event' => $event->fresh(), 'context' => 'admin'])
            ->assertSee('Wydatki / koszty pilota')
            ->assertSee('Gotówka od biura')
            ->assertSee('2 500,00')
            ->call('editOfficePayout', $plnId)
            ->assertSet('editingPayoutCurrencyId', $plnId)
            ->assertSet('payoutAmount', '2500.00')
            ->set('payoutAmount', '2422')
            ->call('saveOfficePayout')
            ->assertNotified();

        $settlement = \App\Models\EventSettlement::findOrCreateActiveForEvent($event->fresh());
        $cash = $settlement->pilotCashPreparations()->where('currency_id', $plnId)->first();
        $this->assertSame(2422.0, (float) $cash->provided_amount);

        Livewire::actingAs($admin)
            ->test(PilotCashDesk::class, ['event' => $event->fresh(), 'context' => 'admin'])
            ->call('deleteOfficePayout', $plnId)
            ->assertNotified();

        $event->refresh();
        $this->assertFalse((bool) $event->pilot_funds_paid);
        $this->assertNull(
            $settlement->fresh()->pilotCashPreparations()->where('currency_id', $plnId)->value('provided_amount')
        );
    }

    public function test_clear_all_office_cash_payouts_resets_paid_flag_for_all_currencies(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $plnId = Currency::query()->create([
            'name' => 'PLN', 'code' => 'PLN', 'symbol' => 'PLN', 'exchange_rate' => 1,
        ])->id;
        $eurId = Currency::query()->create([
            'name' => 'EUR', 'code' => 'EUR', 'symbol' => 'EUR', 'exchange_rate' => 4.3,
        ])->id;

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
            'pilot_funds_paid' => false,
        ]);

        app(PilotAdvanceService::class)->approvePayment($event, paidLines: [
            ['amount' => 1000, 'currency_id' => $plnId],
            ['amount' => 50, 'currency_id' => $eurId],
        ]);

        $event->refresh();
        $this->assertTrue($event->pilot_funds_paid);

        app(PilotAdvanceService::class)->clearAllOfficeCashPayouts($event->fresh());

        $event->refresh();
        $this->assertFalse((bool) $event->pilot_funds_paid);
        $this->assertNull($event->pilot_funds_paid_at);
        $this->assertNull($event->pilot_funds_paid_by);
        $this->assertSame(0, PilotAdvanceLine::query()->where('event_id', $event->id)->where('phase', 'paid')->count());

        $settlement = \App\Models\EventSettlement::findOrCreateActiveForEvent($event);
        $this->assertNull(
            $settlement->pilotCashPreparations()->where('currency_id', $plnId)->value('provided_amount')
        );
        $this->assertNull(
            $settlement->pilotCashPreparations()->where('currency_id', $eurId)->value('provided_amount')
        );
    }

    public function test_manage_event_pilot_can_revoke_paid_funds_via_form_action(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $plnId = Currency::query()->create([
            'name' => 'PLN', 'code' => 'PLN', 'symbol' => 'PLN', 'exchange_rate' => 1,
        ])->id;

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
        ]);

        $this->actingAs($admin);
        app(PilotAdvanceService::class)->approvePayment($event, paidLines: [
            ['amount' => 1500, 'currency_id' => $plnId],
        ]);

        Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\EventResource\Pages\ManageEventPilot::class, [
                'record' => $event->getKey(),
            ])
            ->assertFormFieldExists('pilot_funds_paid')
            ->assertSee('Zmień / dopłać / dodaj walutę')
            ->assertSee('Rozliczenie — gotówka i wymiana walut')
            ->call('revokePilotOfficePayout')
            ->assertNotified();

        $event->refresh();
        $this->assertFalse((bool) $event->pilot_funds_paid);
    }

    public function test_advance_url_for_targets_merged_settlement_tab(): void
    {
        $event = Event::factory()->create();

        $url = PilotAdvancePage::urlFor($event);

        $this->assertStringContainsString('/pilot/settlement/', $url);
        $this->assertStringNotContainsString('/admin/', $url);
        $this->assertSame(PilotSettlementPage::settleUrl($event), $url);
    }
}
