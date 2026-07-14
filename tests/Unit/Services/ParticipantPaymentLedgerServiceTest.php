<?php

namespace Tests\Unit\Services;

use App\Models\Contract;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\EventSettlementParticipantPayment;
use App\Models\EventSettlementParticipantPaymentEntry;
use App\Services\ParticipantPaymentBalanceService;
use App\Services\ParticipantPaymentLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ParticipantPaymentLedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_two_entries_updates_aggregate_and_remaining(): void
    {
        if (! Schema::hasTable('event_settlement_participant_payment_entries')) {
            $this->markTestSkipped('Brak tabeli historii wpłat.');
        }

        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $payment = EventSettlementParticipantPayment::query()->create([
            'settlement_id' => $settlement->id,
            'participant_name' => 'Susan Dale',
            'due_amount_pln' => 1500,
            'paid_amount_pln' => 0,
            'payment_status' => 'pending',
        ]);

        $service = app(ParticipantPaymentLedgerService::class);

        $service->addEntry($payment, 150, '2026-06-21', EventSettlementParticipantPaymentEntry::SOURCE_MANUAL, 'transfer');
        $service->addEntry($payment->fresh(), 200, '2026-07-05', EventSettlementParticipantPaymentEntry::SOURCE_MANUAL, 'transfer');

        $payment->refresh()->load('entries');

        $this->assertCount(2, $payment->entries);
        $this->assertSame(350.0, (float) $payment->paid_amount_pln);
        $this->assertSame('partial', $payment->payment_status);

        $balance = app(ParticipantPaymentBalanceService::class)->balanceRow($payment);
        $this->assertSame(1150.0, $balance['remaining_pln']);
    }

    public function test_remove_entry_recalculates_aggregate(): void
    {
        if (! Schema::hasTable('event_settlement_participant_payment_entries')) {
            $this->markTestSkipped('Brak tabeli historii wpłat.');
        }

        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $payment = EventSettlementParticipantPayment::query()->create([
            'settlement_id' => $settlement->id,
            'participant_name' => 'Jan Kowalski',
            'due_amount_pln' => 1000,
            'paid_amount_pln' => 0,
            'payment_status' => 'pending',
        ]);

        $service = app(ParticipantPaymentLedgerService::class);
        $first = $service->addEntry($payment, 150, now(), EventSettlementParticipantPaymentEntry::SOURCE_MANUAL);
        $second = $service->addEntry($payment->fresh(), 250, now(), EventSettlementParticipantPaymentEntry::SOURCE_MANUAL);

        $service->removeEntry($second);

        $payment->refresh()->load('entries');

        $this->assertCount(1, $payment->entries);
        $this->assertSame($first->id, $payment->entries->first()->id);
        $this->assertSame(150.0, (float) $payment->paid_amount_pln);

        $balance = app(ParticipantPaymentBalanceService::class)->balanceRow($payment);
        $this->assertSame(850.0, $balance['remaining_pln']);
    }

    public function test_refresh_derived_data_preserves_ledger_paid_amount(): void
    {
        if (! Schema::hasTable('event_settlement_participant_payment_entries')) {
            $this->markTestSkipped('Brak tabeli historii wpłat.');
        }

        if (! Schema::hasTable('contracts')) {
            $this->markTestSkipped('Brak tabeli contracts.');
        }

        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $payment = EventSettlementParticipantPayment::query()->create([
            'settlement_id' => $settlement->id,
            'participant_name' => 'Susan Dale',
            'due_amount_pln' => 1500,
            'paid_amount_pln' => 0,
            'payment_status' => 'pending',
        ]);

        Contract::query()->create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_INDIVIDUAL,
            'title' => 'Umowa indywidualna',
            'contract_number' => 'UM-TEST-001',
            'reservation_number' => 'UM-TEST-001',
            'participant_payment_id' => $payment->id,
            'total_price' => 1500,
            'amount_paid' => 0,
            'status' => 'signed',
            'payment_status' => 'pending',
            'public_token' => 'token-'.uniqid(),
        ]);

        $service = app(ParticipantPaymentLedgerService::class);
        $service->addEntry($payment, 220, now(), EventSettlementParticipantPaymentEntry::SOURCE_MANUAL, 'transfer');

        $settlement->refreshDerivedData();

        $payment->refresh();
        $settlement->refresh();

        $this->assertSame(220.0, (float) $payment->paid_amount_pln);
        $this->assertSame(220.0, (float) $settlement->participant_paid_pln);
    }

    public function test_add_entry_updates_settlement_participant_paid_total(): void
    {
        if (! Schema::hasTable('event_settlement_participant_payment_entries')) {
            $this->markTestSkipped('Brak tabeli historii wpłat.');
        }

        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $settlement->update(['participant_due_pln' => 2000, 'participant_paid_pln' => 0]);

        $payment = EventSettlementParticipantPayment::query()->create([
            'settlement_id' => $settlement->id,
            'participant_name' => 'Anna Nowak',
            'due_amount_pln' => 2000,
            'paid_amount_pln' => 0,
            'payment_status' => 'pending',
        ]);

        app(ParticipantPaymentLedgerService::class)->addEntry(
            $payment,
            1000,
            now(),
            EventSettlementParticipantPaymentEntry::SOURCE_MANUAL,
            'transfer',
            payerName: 'Jan Nowak',
            paymentKind: EventSettlementParticipantPaymentEntry::KIND_REGULAR,
        );

        $settlement->refresh();
        $this->assertSame(1000.0, (float) $settlement->participant_paid_pln);
    }
}
