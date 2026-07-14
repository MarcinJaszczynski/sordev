<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\EventSettlementParticipantPayment;
use App\Models\VendorInvoice;
use App\Services\FinancialReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_aggregates_participant_payments_and_invoices(): void
    {
        $event = Event::factory()->create(['code' => 'KRK2026']);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        EventSettlementParticipantPayment::query()->create([
            'settlement_id' => $settlement->id,
            'participant_name' => 'Anna Nowak',
            'due_amount_pln' => 500,
            'paid_amount_pln' => 500,
            'payment_status' => 'paid',
            'payment_date' => now()->subDay(),
        ]);

        VendorInvoice::query()->create([
            'invoice_number' => 'FV/1/2026',
            'seller_name' => 'Hotel Test',
            'seller_nip' => '1234567890',
            'gross_amount' => 1200,
            'net_amount' => 1000,
            'vat_amount' => 200,
            'payment_status' => 'due',
            'approval_status' => 'approved',
            'matching_status' => 'manual',
            'event_id' => $event->id,
            'due_date' => now()->addWeek(),
        ]);

        $service = app(FinancialReportService::class);
        $rows = $service->rows(['event_id' => $event->id]);
        $summary = $service->summarize($rows);

        $this->assertCount(2, $rows);
        $this->assertSame(500.0, $summary['amount_in']);
        $this->assertSame(1200.0, $summary['amount_out']);
        $this->assertSame(-700.0, $summary['balance']);
    }

    public function test_report_filters_by_operation_type(): void
    {
        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        EventSettlementParticipantPayment::query()->create([
            'settlement_id' => $settlement->id,
            'participant_name' => 'Jan Test',
            'due_amount_pln' => 100,
            'paid_amount_pln' => 100,
            'payment_status' => 'paid',
            'payment_date' => now(),
        ]);

        VendorInvoice::query()->create([
            'invoice_number' => 'FV/2/2026',
            'seller_name' => 'Dostawca',
            'gross_amount' => 300,
            'payment_status' => 'due',
            'approval_status' => 'approved',
            'matching_status' => 'manual',
            'event_id' => $event->id,
        ]);

        $rows = app(FinancialReportService::class)->rows([
            'event_id' => $event->id,
            'type' => 'participant_payment',
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame('participant_payment', $rows->first()['operation_type']);
    }
}
