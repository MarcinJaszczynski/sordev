<?php

namespace Tests\Feature\Contracts;

use App\Models\Contract;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventSettlementParticipantPayment;
use App\Models\User;
use App\Services\ContractPaymentSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupIndividualPaymentStrategyTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_contract_with_individual_payments_creates_participant_ledgers(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'code' => '25PAY001',
            'participant_count' => 3,
        ]);

        EventParticipant::create([
            'event_id' => $event->id,
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
            'status' => EventParticipant::STATUS_ACTIVE,
            'source' => EventParticipant::SOURCE_MANUAL,
        ]);

        EventParticipant::create([
            'event_id' => $event->id,
            'first_name' => 'Piotr',
            'last_name' => 'Kowalski',
            'status' => EventParticipant::STATUS_ACTIVE,
            'source' => EventParticipant::SOURCE_MANUAL,
        ]);

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_GROUP,
            'payment_scheme' => Contract::PAYMENT_SCHEME_INDIVIDUAL,
            'title' => 'Umowa grupowa z wpłatami osobno',
            'contract_date' => now()->toDateString(),
            'participant_count' => 3,
            'unit_price' => 1000,
            'total_price' => 3000,
            'amount_paid' => 0,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        app(ContractPaymentSyncService::class)->sync($contract->fresh());

        $contract->refresh();
        $payments = EventSettlementParticipantPayment::query()->get();

        $this->assertSame('25PAY001', $contract->operational_number);
        $this->assertGreaterThanOrEqual(2, $payments->count());
        $this->assertNull($contract->participant_payment_id);
        $this->assertNotEmpty($contract->meta['linked_participant_payment_ids'] ?? []);

        $firstPayment = $payments->first();
        $this->assertSame(1000.0, (float) $firstPayment->due_amount_pln);
        $this->assertStringContainsString('25PAY001U', (string) $firstPayment->booking_reference);
    }

    public function test_group_individual_excluded_from_unsynced_settlement_totals(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['participant_count' => 2]);

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_GROUP,
            'payment_scheme' => Contract::PAYMENT_SCHEME_INDIVIDUAL,
            'title' => 'Grupowa osobno',
            'contract_date' => now()->toDateString(),
            'participant_count' => 2,
            'unit_price' => 500,
            'total_price' => 1000,
            'amount_paid' => 0,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        app(ContractPaymentSyncService::class)->sync($contract->fresh());

        $settlement = $event->fresh()->activeSettlement;
        $settlement->recalculateTotals();

        $this->assertSame(1000.0, (float) $settlement->participant_due_pln);
        $this->assertSame(0.0, (float) $settlement->participant_paid_pln);
    }
}
