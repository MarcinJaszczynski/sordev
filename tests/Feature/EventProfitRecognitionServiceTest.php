<?php

namespace Tests\Feature;

use App\Enums\EventAnalyticsPhase;
use App\Enums\ProfitRecognitionMode;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Services\EventProfitRecognitionService;
use App\Services\ExecutiveProfitLossService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EventProfitRecognitionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_future_event_uses_planned_cost_and_due_revenue(): void
    {
        Carbon::setTestNow('2026-06-01');

        $event = Event::factory()->create([
            'code' => 'FUT2026',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-15',
            'status' => Event::STATUS_CONFIRMED,
            'total_cost' => 5000,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $settlement->forceFill([
            'planned_cost_pln' => 8000,
            'actual_cost_pln' => 500,
            'participant_due_pln' => 12000,
            'participant_paid_pln' => 2000,
            'status' => 'active',
        ])->save();

        $snapshot = app(EventProfitRecognitionService::class)->forEvent($event->fresh());

        $this->assertSame(EventAnalyticsPhase::Future, $snapshot->phase);
        $this->assertSame(ProfitRecognitionMode::Planned, $snapshot->recognitionMode);
        $this->assertSame(8000.0, $snapshot->costRecognizedPln);
        $this->assertSame(12000.0, $snapshot->revenueRecognizedPln);
        $this->assertSame(4000.0, $snapshot->marginRecognizedPln);

        Carbon::setTestNow();
    }

    public function test_completed_open_settlement_blends_paid_plus_remaining_plan(): void
    {
        Carbon::setTestNow('2026-09-01');

        $event = Event::factory()->create([
            'code' => 'DONE2026',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-05',
            'status' => Event::STATUS_TO_SETTLE,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $settlement->forceFill([
            'planned_cost_pln' => 10000,
            'actual_cost_pln' => 2000,
            'participant_due_pln' => 15000,
            'participant_paid_pln' => 15000,
            'status' => 'active',
        ])->save();

        EventSettlementCost::query()->create([
            'settlement_id' => $settlement->id,
            'source_type' => 'manual',
            'name' => 'Hotel',
            'planned_amount' => 10000,
            'planned_amount_pln' => 10000,
            'payment_status' => 'planned',
            'paid_by' => 'office',
            'order' => 1,
        ]);

        EventSettlementCost::query()->create([
            'settlement_id' => $settlement->id,
            'source_type' => 'manual_payment',
            'source_id' => EventSettlementCost::query()
                ->where('settlement_id', $settlement->id)
                ->where('source_type', 'manual')
                ->value('id'),
            'name' => 'Hotel — zaliczka',
            'actual_amount' => 2000,
            'actual_amount_pln' => 2000,
            'payment_status' => 'advance_paid',
            'advance_type' => 'partial',
            'paid_by' => 'office',
            'order' => 2,
        ]);

        $settlement->load('costs');
        $event->setRelation('latestSettlement', $settlement);

        $snapshot = app(EventProfitRecognitionService::class)->forEvent($event);

        $this->assertSame(EventAnalyticsPhase::Completed, $snapshot->phase);
        $this->assertSame(ProfitRecognitionMode::Blend, $snapshot->recognitionMode);
        $this->assertSame(2000.0, $snapshot->costPaidPln);
        $this->assertSame(8000.0, $snapshot->costOutstandingPln);
        $this->assertSame(10000.0, $snapshot->costRecognizedPln);
        $this->assertSame(15000.0, $snapshot->revenueRecognizedPln);
        $this->assertSame(5000.0, $snapshot->marginRecognizedPln);

        Carbon::setTestNow();
    }

    public function test_closed_settlement_uses_only_paid_amounts(): void
    {
        Carbon::setTestNow('2026-09-01');

        $event = Event::factory()->create([
            'code' => 'CLS2026',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-05',
            'status' => Event::STATUS_SETTLED,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $settlement->forceFill([
            'planned_cost_pln' => 10000,
            'actual_cost_pln' => 9500,
            'participant_due_pln' => 15000,
            'participant_paid_pln' => 14800,
            'status' => 'closed',
        ])->save();

        $snapshot = app(EventProfitRecognitionService::class)->forEvent($event->fresh(['latestSettlement']));

        $this->assertSame(ProfitRecognitionMode::Paid, $snapshot->recognitionMode);
        $this->assertSame(9500.0, $snapshot->costRecognizedPln);
        $this->assertSame(14800.0, $snapshot->revenueRecognizedPln);

        Carbon::setTestNow();
    }

    public function test_event_without_settlement_falls_back_to_offer_totals(): void
    {
        Carbon::setTestNow('2026-06-01');

        $event = Event::factory()->create([
            'code' => 'OFFER2026',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-05',
            'status' => Event::STATUS_OFFER,
            'total_cost' => 7777.5,
            'participant_count' => 20,
        ]);

        // Event tworzy puste rozliczenie — brak danych planu/paid → fallback oferty.
        $event->settlements()->delete();

        $snapshot = app(EventProfitRecognitionService::class)->forEvent($event->fresh());

        $this->assertTrue($snapshot->fromOfferFallback);
        $this->assertSame(ProfitRecognitionMode::Planned, $snapshot->recognitionMode);
        $this->assertGreaterThan(0, $snapshot->costRecognizedPln);

        Carbon::setTestNow();
    }

    public function test_empty_settlement_also_falls_back_to_offer_totals(): void
    {
        Carbon::setTestNow('2026-06-01');

        $event = Event::factory()->create([
            'code' => 'EMPTY2026',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-05',
            'status' => Event::STATUS_OFFER,
            'total_cost' => 4321,
            'participant_count' => 10,
        ]);

        $snapshot = app(EventProfitRecognitionService::class)->forEvent($event->fresh());

        $this->assertTrue($snapshot->fromOfferFallback);
        $this->assertSame(ProfitRecognitionMode::Planned, $snapshot->recognitionMode);
        $this->assertGreaterThan(0, $snapshot->costRecognizedPln);

        Carbon::setTestNow();
    }

    public function test_executive_pl_summarizes_recognized_profit_for_future_event(): void
    {
        Carbon::setTestNow('2026-06-01');

        $event = Event::factory()->create([
            'code' => 'EXE2026',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-03',
            'status' => Event::STATUS_CONFIRMED,
        ]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $settlement->forceFill([
            'participant_paid_pln' => 1000,
            'participant_due_pln' => 10000,
            'actual_cost_pln' => 100,
            'planned_cost_pln' => 7000,
            'status' => 'active',
        ])->save();

        $rows = app(ExecutiveProfitLossService::class)->eventRows(['search' => 'EXE2026']);
        $summary = app(ExecutiveProfitLossService::class)->summarize($rows);

        $this->assertSame(1, $summary['events']);
        $this->assertSame(10000.0, $summary['revenue_pln']);
        $this->assertSame(7000.0, $summary['costs_pln']);
        $this->assertSame(3000.0, $summary['net_result_pln']);

        Carbon::setTestNow();
    }
}
