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
        $this->assertArrayHasKey('price_per_person', $summary);
        $this->assertArrayHasKey('calculation', $summary);
        $this->assertArrayHasKey('planned', $summary);
        $this->assertArrayHasKey('paid', $summary);
        $this->assertArrayHasKey('remaining', $summary);
        $this->assertArrayHasKey('client_due', $summary);
        $this->assertArrayHasKey('client_paid', $summary);
        $this->assertArrayHasKey('pilot_cash', $summary);
        $this->assertIsString($summary['price_per_person']);
        $this->assertIsString($summary['pilot_cash']);
        $this->assertArrayHasKey('pilot_cash_lines', $summary);
        $this->assertSame('Cena za osobę (umowa / kalkulacja)', $summary['labels']['price_per_person']);
        $this->assertSame('Koszty (kalkulacja)', $summary['labels']['calculation']);
        $this->assertArrayHasKey('price_per_person_hint', $summary);
        $this->assertSame('Zapłacone dostawcom', $summary['labels']['paid']);
        $this->assertSame('Wpłacono od klientów', $summary['labels']['client_paid']);
        $this->assertStringContainsString('/finance', $summary['settlement_url']);
    }

    public function test_summary_without_settlement_returns_zeros_without_creating(): void
    {
        $event = Event::factory()->create();
        // Observer tworzy draft settlement — usuwamy, żeby sprawdzić czysty odczyt.
        $event->settlements()->delete();
        $this->assertSame(0, $event->fresh()->settlements()->count());

        $summary = app(EventWorkflowFinanceSummaryService::class)->forEvent($event);

        $this->assertNotNull($summary);
        $this->assertStringContainsString('0', $summary['planned']);
        $this->assertStringContainsString('0', $summary['paid']);
        $this->assertStringContainsString('0', $summary['pilot_cash']);
        $this->assertSame([], $summary['pilot_cash_lines']);
        $this->assertStringContainsString('/finance', $summary['settlement_url']);
        $this->assertSame(0, $event->fresh()->settlements()->count());
    }

    public function test_pilot_cash_field_sums_pilot_paid_costs(): void
    {
        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Bilety na miejscu',
            'planned_amount' => 1200,
            'planned_amount_pln' => 1200,
            'paid_by' => 'pilot',
            'payment_status' => 'advance_required',
            'order' => 1,
        ]);

        $summary = app(EventWorkflowFinanceSummaryService::class)->forEvent($event->fresh());

        $this->assertNotNull($summary);
        $normalized = preg_replace('/\s+/u', '', $summary['pilot_cash']);
        $this->assertStringContainsString('1200', $normalized);
    }
}
