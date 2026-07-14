<?php

namespace Tests\Feature;

use App\Livewire\ParticipantPaymentsLedger;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\EventSettlementParticipantPayment;
use App\Models\User;
use App\Services\ParticipantPaymentBalanceService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ParticipantPaymentsLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
    }

    public function test_livewire_add_entry_updates_remaining_balance(): void
    {
        if (! Schema::hasTable('event_settlement_participant_payment_entries')) {
            $this->markTestSkipped('Brak tabeli historii wpłat.');
        }

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $payment = EventSettlementParticipantPayment::query()->create([
            'settlement_id' => $settlement->id,
            'participant_name' => 'Susan Dale',
            'due_amount_pln' => 1500,
            'paid_amount_pln' => 0,
            'payment_status' => 'pending',
        ]);

        Livewire::test(ParticipantPaymentsLedger::class, [
            'settlementId' => $settlement->id,
            'eventId' => $event->id,
        ])
            ->callAction('addEntry', data: [
                'paid_at' => '2026-07-05 10:00:00',
                'amount_pln' => 150,
                'payment_method' => 'transfer',
            ], arguments: ['paymentId' => $payment->id])
            ->assertHasNoErrors();

        $payment->refresh()->load('entries');

        $this->assertCount(1, $payment->entries);
        $this->assertSame(150.0, (float) $payment->paid_amount_pln);

        $balance = app(ParticipantPaymentBalanceService::class)->balanceRow($payment);
        $this->assertSame(1350.0, $balance['remaining_pln']);
    }

    public function test_livewire_rejects_payment_from_other_event(): void
    {
        if (! Schema::hasTable('event_settlement_participant_payment_entries')) {
            $this->markTestSkipped('Brak tabeli historii wpłat.');
        }

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $eventA = Event::factory()->create();
        $eventB = Event::factory()->create();
        $settlementA = EventSettlement::findOrCreateActiveForEvent($eventA);
        $settlementB = EventSettlement::findOrCreateActiveForEvent($eventB);

        $paymentB = EventSettlementParticipantPayment::query()->create([
            'settlement_id' => $settlementB->id,
            'participant_name' => 'Obcy uczestnik',
            'due_amount_pln' => 500,
            'paid_amount_pln' => 0,
            'payment_status' => 'pending',
        ]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(ParticipantPaymentsLedger::class, [
            'settlementId' => $settlementA->id,
            'eventId' => $eventA->id,
        ])
            ->callAction('addEntry', data: [
                'paid_at' => '2026-07-05 10:00:00',
                'amount_pln' => 100,
                'payment_method' => 'transfer',
            ], arguments: ['paymentId' => $paymentB->id]);
    }

    public function test_livewire_rejects_mismatched_settlement_event_on_mount(): void
    {
        $eventA = Event::factory()->create();
        $eventB = Event::factory()->create();
        $settlementB = EventSettlement::findOrCreateActiveForEvent($eventB);

        Livewire::test(ParticipantPaymentsLedger::class, [
            'settlementId' => $settlementB->id,
            'eventId' => $eventA->id,
        ])->assertStatus(404);
    }
}
