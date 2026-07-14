<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Services\SettlementPayerBreakdownService;
use App\Services\SettlementPaymentHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettlementPayerBreakdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_office_and_pilot_totals_are_split(): void
    {
        $event = Event::factory()->create(['use_manual_transport_cost' => true, 'manual_transport_cost' => 1000]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $event->refreshActiveSettlementCosts();

        $transport = $settlement->costs()->where('source_type', 'transport')->first();
        $this->assertNotNull($transport);
        $transport->update(['paid_by' => 'office', 'planned_amount_pln' => 1000, 'planned_amount' => 1000]);

        EventSettlementCost::query()->create([
            'settlement_id' => $settlement->id,
            'source_type' => 'accommodation',
            'name' => 'Nocleg',
            'planned_amount' => 800,
            'planned_amount_pln' => 800,
            'paid_by' => 'pilot',
            'payment_status' => 'planned',
        ]);

        EventSettlementCost::query()->create([
            'settlement_id' => $settlement->id,
            'source_type' => 'transport_payment',
            'name' => 'Transport wpłata',
            'actual_amount_pln' => 400,
            'paid_by' => 'office',
            'payment_status' => 'partially_paid',
        ]);

        EventSettlementCost::query()->create([
            'settlement_id' => $settlement->id,
            'source_type' => 'accommodation_payment',
            'name' => 'Nocleg wpłata',
            'actual_amount_pln' => 800,
            'paid_by' => 'pilot',
            'payment_status' => 'paid',
        ]);

        $settlement->unsetRelation('costs');
        $dashboard = app(SettlementPayerBreakdownService::class)->forSettlement($settlement->fresh(['costs']));

        $this->assertSame(1000.0, $dashboard['office']['planned_pln']);
        $this->assertSame(400.0, $dashboard['office']['paid_pln']);
        $this->assertSame(800.0, $dashboard['pilot']['planned_pln']);
        $this->assertSame(800.0, $dashboard['pilot']['paid_pln']);
        $this->assertSame(1, $dashboard['pilot']['counts'][SettlementPaymentHealthService::STATUS_OK]);
    }
}
