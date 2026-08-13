<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractPaymentSchedule;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\User;
use App\Models\VendorInvoice;
use App\Services\PendingPaymentAggregator;
use App\Services\PendingPaymentCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PendingPaymentAggregatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_collects_settlement_costs_and_contract_schedules(): void
    {
        if (! Schema::hasTable('contract_payment_schedules')) {
            $this->markTestSkipped('contract_payment_schedules table not available.');
        }

        $user = User::factory()->create();
        $event = Event::factory()->create(['code' => 'TST-01']);
        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'source_type' => 'manual',
            'name' => 'Zaliczka hotelu',
            'payment_status' => 'advance_required',
            'advance_due_date' => now()->addDays(5),
            'planned_amount_pln' => 1200,
            'paid_by' => 'office',
            'advance_type' => 'advance',
        ]);

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_GROUP,
            'title' => 'Umowa testowa',
            'contract_date' => now()->toDateString(),
            'total_price' => 500,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        ContractPaymentSchedule::create([
            'contract_id' => $contract->id,
            'sort_order' => 1,
            'label' => 'Rata 1',
            'amount' => 500,
            'due_date' => now()->addDays(10),
        ]);

        $entries = app(PendingPaymentAggregator::class)->collect();

        $this->assertTrue($entries->contains(fn (array $row) => $row['type'] === 'settlement'));
        $this->assertTrue($entries->contains(fn (array $row) => $row['type'] === 'contract'));
    }

    public function test_excludes_fully_paid_contract_installments(): void
    {
        if (! Schema::hasTable('contract_payment_schedules')) {
            $this->markTestSkipped('contract_payment_schedules table not available.');
        }

        $user = User::factory()->create();
        $event = Event::factory()->create(['code' => 'TST-02']);

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_GROUP,
            'title' => 'Umowa opłacona częściowo',
            'contract_date' => now()->toDateString(),
            'total_price' => 1000,
            'amount_paid' => 500,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        $first = ContractPaymentSchedule::create([
            'contract_id' => $contract->id,
            'sort_order' => 1,
            'label' => 'Rata 1',
            'amount' => 500,
            'due_date' => now()->addDays(3),
        ]);

        ContractPaymentSchedule::create([
            'contract_id' => $contract->id,
            'sort_order' => 2,
            'label' => 'Rata 2',
            'amount' => 500,
            'due_date' => now()->addDays(20),
        ]);

        $entries = app(PendingPaymentAggregator::class)->collect();

        $this->assertFalse($entries->contains(fn (array $row) => $row['id'] === 'contract-'.$first->id));
        $this->assertTrue($entries->contains(fn (array $row) => $row['type'] === 'contract' && str_contains($row['title'], 'Rata 2')));
    }

    public function test_collects_vendor_invoices_and_program_point_payments(): void
    {
        if (! Schema::hasTable('vendor_invoices')) {
            $this->markTestSkipped('vendor_invoices table not available.');
        }

        $user = User::factory()->create();
        $event = Event::factory()->create(['code' => 'TST-03']);
        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'name' => 'Zaliczka przewodnika',
            'source_type' => 'program_point_payment',
            'source_id' => 99,
            'payment_status' => 'advance_required',
            'advance_due_date' => now()->addDays(4),
            'planned_amount_pln' => 350,
            'paid_by' => 'office',
            'advance_type' => 'advance',
        ]);

        VendorInvoice::create([
            'event_id' => $event->id,
            'invoice_number' => 'FV/2026/12',
            'gross_amount' => 840,
            'paid_amount' => 0,
            'currency' => 'PLN',
            'due_date' => now()->addDays(7),
            'payment_status' => 'due',
            'approval_status' => 'approved',
            'matching_status' => 'manual',
        ]);

        $entries = app(PendingPaymentAggregator::class)->collect();

        $this->assertTrue($entries->contains(fn (array $row) => $row['type'] === 'program_payment'));
        $this->assertTrue($entries->contains(fn (array $row) => $row['type'] === 'vendor_invoice'));
    }

    public function test_excludes_advance_paid_from_inbox(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['code' => 'TST-ADV']);
        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $cost = EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'source_type' => 'manual',
            'name' => 'Zaliczka wpłacona',
            'payment_status' => 'advance_paid',
            'advance_due_date' => now()->addDays(2),
            'planned_amount_pln' => 500,
            'actual_amount_pln' => 200,
            'paid_by' => 'office',
            'advance_type' => 'advance',
        ]);

        $entries = app(PendingPaymentAggregator::class)->collect();

        $this->assertFalse($entries->contains(fn (array $row) => $row['id'] === 'cost-'.$cost->id));
    }

    public function test_inbox_amount_uses_remaining_not_full_plan(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['code' => 'TST-REM']);
        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $cost = EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'source_type' => 'manual',
            'name' => 'Hotel częściowo',
            'payment_status' => 'partially_paid',
            'advance_due_date' => now()->addDays(3),
            'planned_amount_pln' => 1000,
            'actual_amount_pln' => 400,
            'paid_by' => 'office',
            'advance_type' => 'advance',
        ]);

        $entries = app(PendingPaymentAggregator::class)->collect();
        $row = $entries->first(fn (array $r) => $r['id'] === 'cost-'.$cost->id);

        $this->assertNotNull($row);
        $this->assertEquals(600.0, (float) $row['amount']);
    }

    public function test_mark_completed_marks_settlement_cost_as_paid(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();
        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $cost = EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'name' => 'Hotel',
            'payment_status' => 'advance_required',
            'advance_due_date' => now()->addDay(),
            'planned_amount_pln' => 900,
            'paid_by' => 'office',
            'advance_type' => 'advance',
        ]);

        app(PendingPaymentCompletionService::class)->complete('cost-'.$cost->id);

        $cost->refresh();
        $this->assertSame('paid', $cost->payment_status);
        $this->assertNotNull($cost->paid_at);
    }
}
