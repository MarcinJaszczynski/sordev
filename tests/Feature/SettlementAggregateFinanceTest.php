<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventSettlement;
use App\Services\SettlementAggregateFinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettlementAggregateFinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_transport_plan_advance_and_payment_persist(): void
    {
        $event = Event::factory()->create([
            'transfer_km' => 100,
            'program_km' => 50,
            'participant_count' => 30,
            'use_manual_transport_cost' => true,
            'manual_transport_cost' => 5000,
        ]);

        $service = app(SettlementAggregateFinanceService::class);
        $base = $service->ensureBaseCost($event, 'transport');

        $this->assertNotNull($base);
        $this->assertGreaterThan(0, (float) $base->planned_amount_pln);

        $form = $service->buildFormData($event->fresh(), 'transport');
        $form['settlement_planned_amount'] = (float) $base->planned_amount;
        $form['settlement_advance_amount'] = 500;
        $form['settlement_advance_paid_amount'] = 500;
        $form['settlement_advance_paid_amount_pln'] = 500;

        $service->persist($event->fresh(), 'transport', $form, false);

        $paymentType = $service->paymentSourceType('transport');
        $settlement = EventSettlement::findOrCreateActiveForEvent($event->fresh());

        $this->assertTrue($settlement->costs()
            ->where('source_type', $paymentType)
            ->whereNull('source_id')
            ->where('advance_amount', 500)
            ->exists());

        $form = $service->buildFormData($event->fresh(), 'transport');
        $form['payment_entries'] = [[
            'paid_by' => 'office',
            'due_date' => null,
            'actual_amount' => 1000,
            'actual_currency_id' => $form['settlement_planned_currency_id'],
            'actual_rate' => 1,
            'actual_amount_pln' => 1000,
            'payment_method' => 'transfer',
            'document_id' => null,
            'document_type' => null,
            'document_number' => 'FV/1',
            'document_files' => [],
            'paid_at' => now()->toDateString(),
            'notes' => null,
        ]];

        $summary = $service->persist($event->fresh(), 'transport', $form, true);

        $this->assertGreaterThan(0, $summary['paid_pln']);
    }
}
