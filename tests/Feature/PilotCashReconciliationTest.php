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
            'payment_status' => 'paid',
        ]);

        $service = app(PilotSettlementService::class);
        $row = $service->getCashReconciliation($settlement->fresh())->first();

        $this->assertNotNull($row);
        $this->assertSame(2000.0, $row->office_provided);
        $this->assertSame(1832.0, $row->planned_expenses);
        $this->assertSame(1726.0, $row->actual_spent);
        $this->assertSame(274.0, $row->to_return);
        $this->assertSame(1726.0, (float) $row->cash->fresh()->spent_amount);
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
}
