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
        $this->assertArrayHasKey('client_remaining', $summary);
        $this->assertArrayHasKey('pilot_cash', $summary);
        $this->assertArrayHasKey('pilot_cash_paid', $summary);
        $this->assertIsString($summary['price_per_person']);
        $this->assertIsString($summary['pilot_cash']);
        $this->assertIsString($summary['pilot_cash_paid']);
        $this->assertArrayHasKey('pilot_cash_lines', $summary);
        $this->assertSame('Cena za osobę (umowa / kalkulacja)', $summary['labels']['price_per_person']);
        $this->assertSame('Koszty (kalkulacja)', $summary['labels']['calculation']);
        $this->assertArrayHasKey('price_per_person_hint', $summary);
        $this->assertSame('Zapłacone dostawcom', $summary['labels']['paid']);
        $this->assertSame('Wpłacono od klientów', $summary['labels']['client_paid']);
        $this->assertSame('Gotówka dla pilota (plan)', $summary['labels']['pilot_cash']);
        $this->assertSame('Wypłacono pilotowi', $summary['labels']['pilot_cash_paid']);
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
        $this->assertSame('—', $summary['pilot_cash_paid']);
        $this->assertSame([], $summary['pilot_cash_lines']);
        $this->assertStringContainsString('/finance', $summary['settlement_url']);
        $this->assertSame(0, $event->fresh()->settlements()->count());
    }

    public function test_pilot_cash_paid_shows_office_payout_amount(): void
    {
        $event = Event::factory()->create([
            'assigned_to' => \App\Models\User::factory(),
            'pilot_funds_paid' => true,
            'pilot_advance_paid_amount' => 2500,
        ]);
        EventSettlement::findOrCreateActiveForEvent($event);

        $pln = \App\Models\Currency::query()->firstOrCreate(
            ['code' => 'PLN'],
            ['name' => 'PLN', 'symbol' => 'PLN', 'exchange_rate' => 1],
        );
        $event->update(['pilot_advance_paid_currency_id' => $pln->id]);

        $summary = app(EventWorkflowFinanceSummaryService::class)->forEvent($event->fresh());

        $this->assertNotNull($summary);
        $normalized = preg_replace('/\s+/u', '', $summary['pilot_cash_paid']);
        $this->assertStringContainsString('2500', $normalized);
    }

    public function test_pilot_cash_paid_includes_eur_from_cash_preparations(): void
    {
        $event = Event::factory()->create([
            'assigned_to' => \App\Models\User::factory(),
            'pilot_funds_paid' => true,
            // Legacy bez paid_amount — jak w danych produkcyjnych (tylko planned).
            'pilot_advance_paid_amount' => null,
            'pilot_advance_planned_amount' => 2500,
        ]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $pln = \App\Models\Currency::query()->firstOrCreate(
            ['symbol' => 'PLN'],
            ['name' => 'PLN', 'code' => 'PLN', 'exchange_rate' => 1],
        );
        $eur = \App\Models\Currency::query()->firstOrCreate(
            ['symbol' => 'EUR'],
            ['name' => 'EUR', 'code' => 'EUR', 'exchange_rate' => 4.35],
        );

        $settlement->pilotCashPreparations()->create([
            'currency_id' => $pln->id,
            'calculated_amount' => 3658,
            'provided_amount' => 2500,
            'status' => 'provided',
        ]);
        $settlement->pilotCashPreparations()->create([
            'currency_id' => $eur->id,
            'calculated_amount' => 632.5,
            'provided_amount' => 800,
            'pln_equivalent' => 3480,
            'status' => 'provided',
        ]);

        $summary = app(EventWorkflowFinanceSummaryService::class)->forEvent($event->fresh());

        $this->assertNotNull($summary);
        $normalized = preg_replace('/\s+/u', '', $summary['pilot_cash_paid']);
        $this->assertStringContainsString('2500', $normalized);
        $this->assertStringContainsString('PLN', $normalized);
        $this->assertStringContainsString('800', $normalized);
        $this->assertStringContainsString('EUR', $normalized);
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
