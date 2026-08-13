<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Models\Contract;
use App\Models\ContractPaymentSchedule;
use App\Models\Event;
use App\Services\ContractInstallmentCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContractInstallmentCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('contracts') || ! Schema::hasTable('contract_payment_schedules')) {
            $this->markTestSkipped('Brak tabel umów/rat.');
        }
    }

    public function test_demo_payment_charges_only_next_installment(): void
    {
        $event = Event::factory()->create([
            'start_date' => now()->addDays(60)->toDateString(),
        ]);

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_INDIVIDUAL,
            'title' => 'Umowa',
            'status' => 'signed',
            'payment_status' => 'pending',
            'payment_scheme' => Contract::PAYMENT_SCHEME_INSTALLMENTS,
            'participant_count' => 1,
            'amount_due' => 1000,
            'amount_paid' => 0,
            'currency' => 'PLN',
            'public_token' => 'tok-installment-1',
        ]);

        ContractPaymentSchedule::query()->create([
            'contract_id' => $contract->id,
            'sort_order' => 0,
            'label' => 'Zaliczka',
            'amount' => 300,
            'paid_amount' => 0,
            'paid_by' => 'office',
        ]);
        ContractPaymentSchedule::query()->create([
            'contract_id' => $contract->id,
            'sort_order' => 1,
            'label' => 'Dopłata',
            'amount' => 700,
            'paid_amount' => 0,
            'paid_by' => 'office',
        ]);
        ContractPaymentSchedule::query()->create([
            'contract_id' => $contract->id,
            'sort_order' => 2,
            'label' => 'EUR',
            'amount' => 0,
            'amount_foreign' => 50,
            'currency_code' => 'EUR',
            'paid_by' => 'pilot',
        ]);

        $service = app(ContractInstallmentCheckoutService::class);
        $result = $service->applyDemoPlnPayment($contract, 'demo_transfer', [
            // zmiana FX: biuro zamiast autokaru
            (string) $contract->paymentSchedules()->where('amount_foreign', '>', 0)->value('id') => 'office',
        ]);

        $this->assertSame(300.0, $result['charged']);
        $this->assertSame('partial', $contract->fresh()->payment_status);
        $this->assertSame(300.0, (float) $contract->fresh()->amount_paid);
        $this->assertSame(300.0, (float) $contract->paymentSchedules()->where('label', 'Zaliczka')->value('paid_amount'));
        $this->assertSame(0.0, (float) $contract->paymentSchedules()->where('label', 'Dopłata')->value('paid_amount'));
        $this->assertSame('office', $contract->paymentSchedules()->where('amount_foreign', '>', 0)->value('paid_by'));
        $this->assertSame('pay_pln', $result['snapshot']['next_step']);

        $second = $service->applyDemoPlnPayment($contract->fresh(), 'demo_transfer');
        $this->assertSame(700.0, $second['charged']);
        $this->assertSame('paid', $contract->fresh()->payment_status);
        $this->assertSame(1000.0, (float) $contract->fresh()->amount_paid);
        $this->assertSame('pay_fx_info', $second['snapshot']['next_step']);
    }

    public function test_aligns_schedules_when_contract_total_changes(): void
    {
        $event = Event::factory()->create();
        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_INDIVIDUAL,
            'title' => 'Umowa',
            'status' => 'signed',
            'payment_status' => 'pending',
            'payment_scheme' => Contract::PAYMENT_SCHEME_INSTALLMENTS,
            'amount_due' => 2000,
            'currency' => 'PLN',
            'public_token' => 'tok-align-1',
        ]);

        ContractPaymentSchedule::query()->create([
            'contract_id' => $contract->id,
            'sort_order' => 0,
            'label' => 'A',
            'amount' => 300,
            'paid_amount' => 0,
        ]);
        ContractPaymentSchedule::query()->create([
            'contract_id' => $contract->id,
            'sort_order' => 1,
            'label' => 'B',
            'amount' => 700,
            'paid_amount' => 0,
        ]);

        $aligned = app(ContractInstallmentCheckoutService::class)->alignSchedulesWithContractTotal($contract->fresh());
        $this->assertTrue($aligned);

        $sum = (float) $contract->paymentSchedules()->sum('amount');
        $this->assertEqualsWithDelta(2000.0, $sum, 0.05);
    }

    public function test_align_preserves_already_paid_installment(): void
    {
        $event = Event::factory()->create();
        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_INDIVIDUAL,
            'title' => 'Umowa',
            'status' => 'signed',
            'payment_status' => 'partial',
            'payment_scheme' => Contract::PAYMENT_SCHEME_INSTALLMENTS,
            'amount_due' => 1500,
            'amount_paid' => 300,
            'currency' => 'PLN',
            'public_token' => 'tok-align-paid-1',
        ]);

        ContractPaymentSchedule::query()->create([
            'contract_id' => $contract->id,
            'sort_order' => 0,
            'label' => 'Zaliczka',
            'amount' => 300,
            'paid_amount' => 300,
        ]);
        ContractPaymentSchedule::query()->create([
            'contract_id' => $contract->id,
            'sort_order' => 1,
            'label' => 'Dopłata',
            'amount' => 700,
            'paid_amount' => 0,
        ]);

        $aligned = app(ContractInstallmentCheckoutService::class)->alignSchedulesWithContractTotal($contract->fresh());
        $this->assertTrue($aligned);

        $this->assertEqualsWithDelta(300.0, (float) $contract->paymentSchedules()->where('label', 'Zaliczka')->value('amount'), 0.05);
        $this->assertEqualsWithDelta(300.0, (float) $contract->paymentSchedules()->where('label', 'Zaliczka')->value('paid_amount'), 0.05);
        $this->assertEqualsWithDelta(1200.0, (float) $contract->paymentSchedules()->where('label', 'Dopłata')->value('amount'), 0.05);
        $this->assertEqualsWithDelta(1500.0, (float) $contract->paymentSchedules()->sum('amount'), 0.05);
    }
}
