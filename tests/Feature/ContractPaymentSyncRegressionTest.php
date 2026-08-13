<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Event;
use App\Models\EventAgreement;
use App\Models\EventSettlement;
use App\Models\EventSettlementParticipantPayment;
use App\Models\User;
use App\Services\AgreementPaymentSyncService;
use App\Services\ContractPaymentSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regresja buga: istniejący wiersz płatności uczestnika z paid=0
 * nie może „przykryć” amount_paid z umowy (demo / rata częściowa).
 */
class ContractPaymentSyncRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_agreement_demo_pay_updates_existing_zero_ledger_row(): void
    {
        if (! Schema::hasTable('event_agreements')) {
            $this->markTestSkipped('Brak event_agreements');
        }

        $event = Event::factory()->create(['participant_count' => 1]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $ledger = EventSettlementParticipantPayment::create([
            'settlement_id' => $settlement->id,
            'participant_name' => 'Jan Kowalski',
            'booking_reference' => 'TMP-ZERO',
            'due_amount_pln' => 1500,
            'paid_amount_pln' => 0,
            'payment_status' => 'pending',
        ]);

        $agreement = EventAgreement::create([
            'event_id' => $event->id,
            'agreement_type' => EventAgreement::TYPE_INDIVIDUAL,
            'agreement_number' => 'UMOWA-DEMO-1',
            'public_token' => 'tok-demo-'.uniqid(),
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'demo_transfer',
            'amount_due' => 1500,
            'amount_paid' => 1500,
            'paid_at' => now(),
            'signer_name' => 'Jan Kowalski',
            'participant_count' => 1,
            'participant_payment_id' => $ledger->id,
        ]);

        app(AgreementPaymentSyncService::class)->sync($agreement);

        $ledger->refresh();
        $settlement->refresh();

        $this->assertSame(1500.0, (float) $ledger->paid_amount_pln);
        $this->assertSame('paid', $ledger->payment_status);
        $this->assertSame(1500.0, (float) $settlement->participant_paid_pln);
    }

    public function test_agreement_partial_installment_keeps_partial_status_over_zero_ledger(): void
    {
        if (! Schema::hasTable('event_agreements')) {
            $this->markTestSkipped('Brak event_agreements');
        }

        $event = Event::factory()->create(['participant_count' => 1]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $ledger = EventSettlementParticipantPayment::create([
            'settlement_id' => $settlement->id,
            'participant_name' => 'Anna Nowak',
            'booking_reference' => 'TMP-PARTIAL',
            'due_amount_pln' => 2000,
            'paid_amount_pln' => 0,
            'payment_status' => 'pending',
        ]);

        $agreement = EventAgreement::create([
            'event_id' => $event->id,
            'agreement_type' => EventAgreement::TYPE_INDIVIDUAL,
            'agreement_number' => 'UMOWA-PARTIAL-1',
            'public_token' => 'tok-partial-'.uniqid(),
            'status' => 'signed',
            'payment_status' => 'partial',
            'payment_method' => 'demo_transfer',
            'amount_due' => 2000,
            'amount_paid' => 600,
            'paid_at' => now(),
            'signer_name' => 'Anna Nowak',
            'participant_count' => 1,
            'participant_payment_id' => $ledger->id,
        ]);

        app(AgreementPaymentSyncService::class)->sync($agreement);

        $ledger->refresh();
        $settlement->refresh();

        $this->assertSame(600.0, (float) $ledger->paid_amount_pln);
        $this->assertSame('partial', $ledger->payment_status);
        $this->assertSame(600.0, (float) $settlement->participant_paid_pln);
    }

    public function test_contract_demo_pay_updates_existing_zero_ledger_row(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['participant_count' => 1]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $ledger = EventSettlementParticipantPayment::create([
            'settlement_id' => $settlement->id,
            'participant_name' => 'Piotr Zieliński',
            'booking_reference' => 'CTR-ZERO',
            'due_amount_pln' => 1200,
            'paid_amount_pln' => 0,
            'payment_status' => 'pending',
        ]);

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_INDIVIDUAL,
            'title' => 'Umowa demo sync',
            'contract_date' => now()->toDateString(),
            'participant_count' => 1,
            'total_price' => 1200,
            'amount_paid' => 1200,
            'currency' => 'PLN',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'demo_transfer',
            'paid_at' => now(),
            'customer_name' => 'Piotr Zieliński',
            'signer_name' => 'Piotr Zieliński',
            'created_by' => $user->id,
            'participant_payment_id' => $ledger->id,
            'contract_number' => 'CTR-DEMO-1',
        ]);

        app(ContractPaymentSyncService::class)->sync($contract);

        $ledger->refresh();
        $settlement->refresh();

        $this->assertSame(1200.0, (float) $ledger->paid_amount_pln);
        $this->assertSame('paid', $ledger->payment_status);
        $this->assertSame(1200.0, (float) $settlement->participant_paid_pln);
    }

    public function test_contract_partial_installment_syncs_over_zero_ledger(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['participant_count' => 1]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $ledger = EventSettlementParticipantPayment::create([
            'settlement_id' => $settlement->id,
            'participant_name' => 'Ewa Lis',
            'booking_reference' => 'CTR-PARTIAL',
            'due_amount_pln' => 1800,
            'paid_amount_pln' => 0,
            'payment_status' => 'pending',
        ]);

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_INDIVIDUAL,
            'payment_scheme' => Contract::PAYMENT_SCHEME_INSTALLMENTS,
            'title' => 'Umowa rata częściowa',
            'contract_date' => now()->toDateString(),
            'participant_count' => 1,
            'total_price' => 1800,
            'amount_paid' => 450,
            'currency' => 'PLN',
            'status' => 'signed',
            'payment_status' => 'partial',
            'payment_method' => 'transfer',
            'paid_at' => now(),
            'customer_name' => 'Ewa Lis',
            'signer_name' => 'Ewa Lis',
            'created_by' => $user->id,
            'participant_payment_id' => $ledger->id,
            'contract_number' => 'CTR-PARTIAL-1',
        ]);

        app(ContractPaymentSyncService::class)->sync($contract);

        $ledger->refresh();
        $settlement->refresh();

        $this->assertSame(450.0, (float) $ledger->paid_amount_pln);
        $this->assertSame('partial', $ledger->payment_status);
        $this->assertSame(450.0, (float) $settlement->participant_paid_pln);
    }
}
