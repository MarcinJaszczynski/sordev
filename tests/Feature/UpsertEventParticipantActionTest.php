<?php

namespace Tests\Feature;

use App\Actions\Events\UpsertEventParticipantAction;
use App\Data\UpsertEventParticipantData;
use App\Livewire\ParticipantPaymentsLedger;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventSettlement;
use App\Models\EventSettlementParticipantPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UpsertEventParticipantActionTest extends TestCase
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

    public function test_creates_participant_and_payment_row(): void
    {
        if (! Schema::hasTable('event_participants') || ! Schema::hasTable('event_settlement_participant_payments')) {
            $this->markTestSkipped('Brak tabel uczestników/wpłat.');
        }

        $event = Event::factory()->create();

        $participant = app(UpsertEventParticipantAction::class)(new UpsertEventParticipantData(
            event: $event,
            firstName: 'Anna',
            lastName: 'Kowalska',
            email: 'anna@example.com',
            bookingReference: 'BK-1',
            ensurePayment: true,
            dueAmountPln: 1200,
        ));

        $this->assertDatabaseHas('event_participants', [
            'id' => $participant->id,
            'event_id' => $event->id,
            'first_name' => 'Anna',
            'last_name' => 'Kowalska',
        ]);

        $this->assertNotNull($participant->participant_payment_id);
        $this->assertDatabaseHas('event_settlement_participant_payments', [
            'id' => $participant->participant_payment_id,
            'participant_name' => 'Anna Kowalska',
            'due_amount_pln' => 1200,
            'booking_reference' => 'BK-1',
        ]);
    }

    public function test_list_participant_appears_on_payments_ledger_mount(): void
    {
        if (! Schema::hasTable('event_participants') || ! Schema::hasTable('event_settlement_participant_payments')) {
            $this->markTestSkipped('Brak tabel uczestników/wpłat.');
        }

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        EventParticipant::query()->create([
            'event_id' => $event->id,
            'first_name' => 'Piotr',
            'last_name' => 'Nowak',
            'source' => EventParticipant::SOURCE_MANUAL,
            'status' => EventParticipant::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseCount('event_settlement_participant_payments', 0);

        Livewire::test(ParticipantPaymentsLedger::class, [
            'settlementId' => $settlement->id,
            'eventId' => $event->id,
        ]);

        $this->assertDatabaseHas('event_settlement_participant_payments', [
            'settlement_id' => $settlement->id,
            'participant_name' => 'Piotr Nowak',
        ]);
    }

    public function test_first_installment_is_optional_and_entries_remain_multi_tranche(): void
    {
        if (! Schema::hasTable('event_settlement_participant_payment_entries')) {
            $this->markTestSkipped('Brak tabeli historii wpłat.');
        }

        $event = Event::factory()->create();

        $participant = app(UpsertEventParticipantAction::class)(new UpsertEventParticipantData(
            event: $event,
            firstName: 'Ewa',
            lastName: 'Lis',
            ensurePayment: true,
            dueAmountPln: 900,
            firstPaymentAmountPln: 300,
            firstPaymentPaidAt: '2026-08-01 12:00:00',
            firstPaymentMethod: 'transfer',
        ));

        $payment = EventSettlementParticipantPayment::query()->findOrFail($participant->participant_payment_id);
        $this->assertCount(1, $payment->entries);
        $this->assertSame(300.0, (float) $payment->paid_amount_pln);
        $this->assertSame(900.0, (float) $payment->due_amount_pln);
    }
}
