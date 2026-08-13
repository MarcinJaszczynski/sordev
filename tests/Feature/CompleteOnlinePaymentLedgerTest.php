<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Finance\CompleteOnlinePaymentAction;
use App\Actions\Finance\InitiateOnlinePaymentAction;
use App\Models\Contract;
use App\Models\ContractPaymentSchedule;
use App\Models\Event;
use App\Models\EventAgreement;
use App\Models\EventAgreementPaymentSchedule;
use App\Models\EventSettlementParticipantPayment;
use App\Models\EventSettlementParticipantPaymentEntry;
use App\Models\OnlinePaymentSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CompleteOnlinePaymentLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_online_payment_writes_participant_ledger_entry(): void
    {
        if (! Schema::hasTable('online_payment_sessions')
            || ! Schema::hasTable('contract_payment_schedules')
            || ! Schema::hasTable('event_settlement_participant_payment_entries')
        ) {
            $this->markTestSkipped('Brak tabel płatności online / ledger.');
        }

        $user = User::factory()->create();
        $event = Event::factory()->create(['participant_count' => 1]);

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_INDIVIDUAL,
            'payment_scheme' => Contract::PAYMENT_SCHEME_INSTALLMENTS,
            'title' => 'Umowa test online',
            'contract_date' => now()->toDateString(),
            'participant_count' => 1,
            'total_price' => 1000,
            'amount_paid' => 0,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'customer_name' => 'Jan Kowalski',
            'created_by' => $user->id,
        ]);

        $schedule = ContractPaymentSchedule::create([
            'contract_id' => $contract->id,
            'sort_order' => 1,
            'label' => 'Zaliczka',
            'amount' => 400,
            'paid_amount' => 0,
            'due_date' => now()->addDays(7)->toDateString(),
        ]);

        $session = OnlinePaymentSession::create([
            'driver' => 'fake',
            'status' => OnlinePaymentSession::STATUS_PENDING,
            'payable_type' => ContractPaymentSchedule::class,
            'payable_id' => $schedule->id,
            'event_id' => $event->id,
            'amount' => 400,
            'currency' => 'PLN',
            'description' => 'Zaliczka',
            'payer_email' => 'jan@example.com',
            'expires_at' => now()->addDay(),
        ]);

        $completed = app(CompleteOnlinePaymentAction::class)($session);

        $this->assertSame(OnlinePaymentSession::STATUS_PAID, $completed->status);

        $schedule->refresh();
        $this->assertSame(400.0, (float) $schedule->paid_amount);

        $contract->refresh();
        $this->assertSame(400.0, (float) $contract->amount_paid);
        $this->assertNotNull($contract->participant_payment_id);

        $payment = EventSettlementParticipantPayment::query()->findOrFail($contract->participant_payment_id);
        $this->assertSame(400.0, (float) $payment->paid_amount_pln);

        $entry = EventSettlementParticipantPaymentEntry::query()
            ->where('participant_payment_id', $payment->id)
            ->where('source', EventSettlementParticipantPaymentEntry::SOURCE_ONLINE)
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame(400.0, (float) $entry->amount_pln);
        $this->assertStringContainsString('session:'.$session->uuid, (string) $entry->notes);
    }

    public function test_complete_online_payment_for_agreement_updates_schedule_and_ledger(): void
    {
        if (! Schema::hasTable('online_payment_sessions')
            || ! Schema::hasTable('event_agreement_payment_schedules')
            || ! Schema::hasTable('event_settlement_participant_payment_entries')
            || ! Schema::hasColumn('event_agreement_payment_schedules', 'paid_amount')
        ) {
            $this->markTestSkipped('Brak tabel agreement payment / ledger.');
        }

        $event = Event::factory()->create(['participant_count' => 1]);

        $agreement = EventAgreement::create([
            'event_id' => $event->id,
            'agreement_type' => 'individual',
            'status' => 'sent',
            'payment_status' => 'pending',
            'amount_due' => 1000,
            'amount_paid' => 0,
            'currency' => 'PLN',
            'customer_name' => 'Anna Nowak',
            'agreement_date' => now()->toDateString(),
        ]);

        $schedule = EventAgreementPaymentSchedule::create([
            'event_agreement_id' => $agreement->id,
            'sort_order' => 1,
            'label' => 'Zaliczka',
            'amount' => 300,
            'paid_amount' => 0,
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        $session = OnlinePaymentSession::create([
            'driver' => 'fake',
            'status' => OnlinePaymentSession::STATUS_PENDING,
            'payable_type' => EventAgreementPaymentSchedule::class,
            'payable_id' => $schedule->id,
            'event_id' => $event->id,
            'amount' => 300,
            'currency' => 'PLN',
            'description' => 'Zaliczka',
            'payer_email' => 'anna@example.com',
            'expires_at' => now()->addDay(),
        ]);

        app(CompleteOnlinePaymentAction::class)($session);

        $schedule->refresh();
        $this->assertSame(300.0, (float) $schedule->paid_amount);

        $agreement->refresh();
        $this->assertSame(300.0, (float) $agreement->amount_paid);
        $this->assertNotNull($agreement->participant_payment_id);

        $payment = EventSettlementParticipantPayment::query()->findOrFail($agreement->participant_payment_id);
        $this->assertSame(300.0, (float) $payment->paid_amount_pln);

        $this->assertSame(
            1,
            EventSettlementParticipantPaymentEntry::query()
                ->where('participant_payment_id', $payment->id)
                ->where('source', EventSettlementParticipantPaymentEntry::SOURCE_ONLINE)
                ->count()
        );

        // Initiate must see remaining 0 — no double pay of same installment.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rata jest już opłacona.');
        app(InitiateOnlinePaymentAction::class)($schedule->fresh());
    }

    public function test_complete_online_payment_is_idempotent(): void
    {
        if (! Schema::hasTable('online_payment_sessions')
            || ! Schema::hasTable('contract_payment_schedules')
            || ! Schema::hasTable('event_settlement_participant_payment_entries')
        ) {
            $this->markTestSkipped('Brak tabel płatności online / ledger.');
        }

        $user = User::factory()->create();
        $event = Event::factory()->create(['participant_count' => 1]);

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_INDIVIDUAL,
            'payment_scheme' => Contract::PAYMENT_SCHEME_INSTALLMENTS,
            'title' => 'Umowa idempotencja',
            'contract_date' => now()->toDateString(),
            'participant_count' => 1,
            'total_price' => 500,
            'amount_paid' => 0,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'customer_name' => 'Idempotent',
            'created_by' => $user->id,
        ]);

        $schedule = ContractPaymentSchedule::create([
            'contract_id' => $contract->id,
            'sort_order' => 1,
            'label' => 'Rata',
            'amount' => 500,
            'paid_amount' => 0,
            'due_date' => now()->addDays(3)->toDateString(),
        ]);

        $session = OnlinePaymentSession::create([
            'driver' => 'fake',
            'status' => OnlinePaymentSession::STATUS_PENDING,
            'payable_type' => ContractPaymentSchedule::class,
            'payable_id' => $schedule->id,
            'event_id' => $event->id,
            'amount' => 500,
            'currency' => 'PLN',
            'description' => 'Rata',
            'expires_at' => now()->addDay(),
        ]);

        $action = app(CompleteOnlinePaymentAction::class);
        $action($session);
        $action($session->fresh());

        $schedule->refresh();
        $this->assertSame(500.0, (float) $schedule->paid_amount);

        $contract->refresh();
        $this->assertSame(500.0, (float) $contract->amount_paid);

        $this->assertSame(
            1,
            EventSettlementParticipantPaymentEntry::query()
                ->where('participant_payment_id', $contract->participant_payment_id)
                ->where('source', EventSettlementParticipantPaymentEntry::SOURCE_ONLINE)
                ->count()
        );
    }
}
