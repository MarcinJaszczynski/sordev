<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractPaymentSchedule;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\EventSettlementParticipantPayment;
use App\Services\ParticipantPaymentBalanceService;
use App\Services\SettlementPaymentHealthService;
use App\Services\ContractPaymentSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ParticipantPaymentBalanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_ordering_installment_due_status(): void
    {
        if (! Schema::hasTable('contracts') || ! Schema::hasTable('contract_payment_schedules')) {
            $this->markTestSkipped('Brak tabel umów.');
        }

        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $payment = EventSettlementParticipantPayment::query()->create([
            'settlement_id' => $settlement->id,
            'participant_name' => 'Szkoła XYZ',
            'due_amount_pln' => 3000,
            'paid_amount_pln' => 500,
            'payment_status' => 'partial',
        ]);

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_GROUP,
            'payment_scheme' => Contract::PAYMENT_SCHEME_INSTALLMENTS,
            'title' => 'Umowa grupowa',
            'contract_date' => now()->toDateString(),
            'customer_name' => 'Szkoła',
            'total_price' => 3000,
            'currency' => 'PLN',
            'status' => 'signed',
            'payment_status' => 'partial',
            'participant_payment_id' => $payment->id,
        ]);

        ContractPaymentSchedule::query()->create([
            'contract_id' => $contract->id,
            'sort_order' => 0,
            'label' => 'Zaliczka',
            'amount' => 1000,
            'due_date' => now()->addWeek(),
        ]);
        ContractPaymentSchedule::query()->create([
            'contract_id' => $contract->id,
            'sort_order' => 1,
            'label' => 'Reszta',
            'amount' => 2000,
            'due_date' => now()->addMonth(),
        ]);

        $contract->update(['amount_paid' => 500, 'payment_status' => 'partial']);
        app(ContractPaymentSyncService::class)->sync($contract->fresh());

        $payment = $payment->fresh(['contracts.paymentSchedules']);
        $balance = app(ParticipantPaymentBalanceService::class)->balanceRow($payment);
        $this->assertSame(SettlementPaymentHealthService::STATUS_DUE, $balance['coverage_status']);
        $this->assertSame(2500.0, $balance['remaining_pln']);
        $this->assertSame('Zaliczka', $balance['installment_label']);
    }

    public function test_paid_participant_is_ok(): void
    {
        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $payment = EventSettlementParticipantPayment::query()->create([
            'settlement_id' => $settlement->id,
            'participant_name' => 'Jan Kowalski',
            'due_amount_pln' => 1200,
            'paid_amount_pln' => 1200,
            'payment_status' => 'paid',
        ]);

        $balance = app(ParticipantPaymentBalanceService::class)->balanceRow($payment);

        $this->assertSame(SettlementPaymentHealthService::STATUS_OK, $balance['coverage_status']);
        $this->assertSame(0.0, $balance['remaining_pln']);
    }
}
