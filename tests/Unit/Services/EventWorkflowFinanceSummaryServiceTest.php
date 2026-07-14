<?php

namespace Tests\Unit\Services;

use App\Models\Event;
use App\Models\EventSettlement;
use App\Services\EventWorkflowFinanceSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventWorkflowFinanceSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_finance_summary_for_event(): void
    {
        $event = Event::factory()->create();
        EventSettlement::findOrCreateActiveForEvent($event);

        $summary = app(EventWorkflowFinanceSummaryService::class)->forEvent($event);

        $this->assertNotNull($summary);
        $this->assertArrayHasKey('planned', $summary);
        $this->assertArrayHasKey('paid', $summary);
        $this->assertArrayHasKey('remaining', $summary);
        $this->assertArrayHasKey('pilot_cash', $summary);
        $this->assertStringContainsString('/settlement', $summary['settlement_url']);
    }
}
