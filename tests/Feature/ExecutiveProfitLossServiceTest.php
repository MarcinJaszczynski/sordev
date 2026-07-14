<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventSettlement;
use App\Services\ExecutiveProfitLossService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveProfitLossServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summarizes_revenue_costs_and_net_result(): void
    {
        $event = Event::factory()->create(['code' => 'EXE2026']);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $settlement->forceFill([
            'participant_paid_pln' => 10000,
            'participant_due_pln' => 10000,
            'actual_cost_pln' => 7200,
            'planned_cost_pln' => 7000,
            'status' => 'active',
        ])->save();

        $rows = app(ExecutiveProfitLossService::class)->eventRows(['search' => 'EXE2026']);
        $summary = app(ExecutiveProfitLossService::class)->summarize($rows);

        $this->assertSame(1, $summary['events']);
        $this->assertSame(10000.0, $summary['revenue_pln']);
        $this->assertSame(7200.0, $summary['costs_pln']);
        $this->assertSame(2800.0, $summary['net_result_pln']);
    }
}
