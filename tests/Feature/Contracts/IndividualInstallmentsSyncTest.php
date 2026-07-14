<?php

namespace Tests\Feature\Contracts;

use App\Models\Contract;
use App\Models\Event;
use App\Models\User;
use App\Services\ContractPaymentScheduleService;
use App\Services\ContractPaymentSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndividualInstallmentsSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_individual_contract_can_store_installment_schedules(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['code' => '25INS001']);

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_INDIVIDUAL,
            'title' => 'Umowa indywidualna z ratami',
            'contract_date' => now()->toDateString(),
            'participant_count' => 1,
            'total_price' => 1200,
            'payment_scheme' => Contract::PAYMENT_SCHEME_INSTALLMENTS,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        app(ContractPaymentScheduleService::class)->syncForContract($contract, [
            ['label' => 'Zaliczka', 'amount' => 400, 'due_date' => now()->addWeek()->toDateString()],
            ['label' => 'Reszta', 'amount' => 800, 'due_date' => now()->addMonth()->toDateString()],
        ], Contract::PAYMENT_SCHEME_INSTALLMENTS);

        $contract->refresh();

        $this->assertCount(2, $contract->paymentSchedules);
        $this->assertSame('25INS001U001', $contract->operational_number);

        app(ContractPaymentSyncService::class)->sync($contract->fresh());

        $this->assertDatabaseHas('event_settlement_participant_payments', [
            'booking_reference' => '25INS001U001',
            'due_amount_pln' => 1200,
        ]);
    }
}
