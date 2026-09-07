<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\PilotFeeLine;
use App\Models\User;
use App\Services\PilotFeeService;
use App\Services\PilotSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PilotFeeAndOfficeSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'pilot']);
    }

    public function test_fee_lines_support_multiple_currencies_and_remaining(): void
    {
        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
        ]);

        $plnId = Currency::query()->create([
            'name' => 'PLN', 'code' => 'PLN', 'symbol' => 'PLN', 'exchange_rate' => 1,
        ])->id;
        $eurId = Currency::query()->create([
            'name' => 'EUR', 'code' => 'EUR', 'symbol' => 'EUR', 'exchange_rate' => 4.3,
        ])->id;

        $fee = app(PilotFeeService::class);
        $fee->syncDueLines($event, [
            ['amount' => 1200, 'currency_id' => $plnId],
            ['amount' => 100, 'currency_id' => $eurId],
        ]);

        $event->refresh();
        $this->assertCount(2, $fee->dueLines($event));
        $this->assertTrue($fee->hasOutstandingFee($event));
        $this->assertStringContainsString('PLN', $fee->formatDueLabel($event));
        $this->assertStringContainsString('EUR', $fee->formatDueLabel($event));

        $fee->markPaid($event->fresh(), [
            ['amount' => 1200, 'currency_id' => $plnId],
        ]);

        $event->refresh();
        $this->assertCount(1, $fee->paidLines($event));
        $remaining = $fee->remainingLines($event);
        $this->assertCount(1, $remaining);
        $this->assertSame($eurId, (int) $remaining->first()['currency_id']);
        $this->assertSame(100.0, (float) $remaining->first()['amount']);
        $this->assertFalse($fee->isFullyPaid($event));

        $fee->markPaid($event->fresh());
        $event->refresh();
        $this->assertTrue($fee->isFullyPaid($event));
        $this->assertFalse($fee->hasOutstandingFee($event));
    }

    public function test_office_can_close_and_reopen_pilot_settlement(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'super_admin']);

        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
        ]);

        $settlement = app(PilotSettlementService::class)->getOrCreateSettlement($event);
        $this->assertSame('active', $settlement->status);

        $closed = app(PilotSettlementService::class)->confirmOfficeClose($event->fresh());
        $this->assertSame('closed', $closed->status);
        $this->assertFalse($closed->isEditableByPilot());

        // Zamknięcie nie tworzy drugiego settlementu.
        $this->assertSame(1, EventSettlement::query()->where('event_id', $event->id)->count());

        $reopened = app(PilotSettlementService::class)->reopenOfficeSettlement($event->fresh());
        $this->assertSame('active', $reopened->status);
        $this->assertTrue($reopened->isEditableByPilot());
        $this->assertSame($closed->id, $reopened->id);
    }

    public function test_clear_paid_fee_keeps_due_lines(): void
    {
        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
        ]);

        $plnId = Currency::query()->create([
            'name' => 'PLN', 'code' => 'PLN', 'symbol' => 'PLN', 'exchange_rate' => 1,
        ])->id;

        $fee = app(PilotFeeService::class);
        $fee->syncDueLines($event, [['amount' => 900, 'currency_id' => $plnId]]);
        $fee->markPaid($event->fresh());

        $this->assertSame(1, PilotFeeLine::query()->where('event_id', $event->id)->where('phase', 'paid')->count());

        $fee->clearPaid($event->fresh());
        $event->refresh();

        $this->assertSame(0, PilotFeeLine::query()->where('event_id', $event->id)->where('phase', 'paid')->count());
        $this->assertSame(1, PilotFeeLine::query()->where('event_id', $event->id)->where('phase', 'due')->count());
        $this->assertTrue($fee->hasOutstandingFee($event));
    }
}
