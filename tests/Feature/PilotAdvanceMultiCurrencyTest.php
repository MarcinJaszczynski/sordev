<?php

namespace Tests\Feature;

use App\Filament\Pilot\Pages\PilotAdvancePage;
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

    public function test_pilot_can_record_currency_exchange_from_advance_page(): void
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
        ]);

        app(PilotAdvanceService::class)->approvePayment($event, paidLines: [
            ['amount' => 1000, 'currency_id' => $plnId],
        ]);

        Livewire::actingAs($pilot)
            ->test(PilotAdvancePage::class, ['event' => $event])
            ->set('exchangeFromCurrencyId', $plnId)
            ->set('exchangeToCurrencyId', $eurId)
            ->set('exchangeFromAmount', '200')
            ->set('exchangeToAmount', '45')
            ->call('recordCurrencyExchange')
            ->assertNotified();

        $settlement = \App\Models\EventSettlement::findOrCreateActiveForEvent($event->fresh());
        $this->assertSame(1, $settlement->currencyExchanges()->count());

        $row = app(PilotSettlementService::class)->getCashReconciliation($settlement->fresh())->keyBy('currency_id');
        $this->assertSame(800.0, $row[$plnId]->office_provided);
        $this->assertSame(45.0, $row[$eurId]->office_provided);
    }

    public function test_advance_url_for_targets_pilot_panel_not_admin(): void
    {
        $event = Event::factory()->create();

        $url = PilotAdvancePage::urlFor($event);

        $this->assertStringContainsString('/pilot/advance/', $url);
        $this->assertStringNotContainsString('/admin/', $url);
    }
}
