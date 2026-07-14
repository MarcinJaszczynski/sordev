<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventParticipantCountSettlementSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_count_change_updates_planned_cost_and_keeps_payments(): void
    {
        $event = Event::factory()->create([
            'participant_count' => 20,
            'use_manual_transport_cost' => true,
            'manual_transport_cost' => 2000,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $event->refreshActiveSettlementCosts();

        $planBefore = (float) $settlement->fresh()->planned_cost_pln;

        EventSettlementCost::query()->create([
            'settlement_id' => $settlement->id,
            'source_type' => 'transport_payment',
            'name' => 'Wpłata',
            'actual_amount_pln' => 500,
            'payment_status' => 'partially_paid',
            'paid_by' => 'office',
        ]);

        $event->update(['participant_count' => 25]);
        $settlement->refresh();

        $paymentStillExists = $settlement->costs()
            ->where('source_type', 'transport_payment')
            ->where('actual_amount_pln', 500)
            ->exists();

        $this->assertTrue($paymentStillExists);
        $settlement->recalculateTotals();
        $this->assertSame(500.0, (float) $settlement->fresh()->actual_cost_pln);
        $this->assertGreaterThan(0, (float) $settlement->fresh()->planned_cost_pln);
    }
}
