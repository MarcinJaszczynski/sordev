<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Services\ProgramPointSetFinanceAggregator;
use App\Services\ProgramPointSettlementCostCache;
use App\Support\EventProgramPointPricesSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramPointSetFinanceAggregatorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function seedProgramPointCost(EventSettlement $settlement, EventProgramPoint $point, array $attributes): EventSettlementCost
    {
        return EventSettlementCost::query()->updateOrCreate(
            [
                'settlement_id' => $settlement->id,
                'source_type' => 'program_point',
                'source_id' => $point->id,
            ],
            [
                'name' => $point->name,
                'payment_status' => 'planned',
                'order' => 1,
                ...$attributes,
            ],
        );
    }

    private function summarizeSet(Event $event, EventProgramPoint $parent): \App\Support\ProgramPointSetFinanceSummary
    {
        $parent = $parent->fresh()->loadCount('children');
        $cache = new ProgramPointSettlementCostCache;
        $cache->warm(collect([$parent]), $event);

        return app(ProgramPointSetFinanceAggregator::class, [
            'costCache' => $cache,
        ])->summarize($parent, $event);
    }

    public function test_aggregates_pln_parent_and_foreign_child_without_conversion(): void
    {
        $pln = Currency::factory()->pln()->create();
        $eur = Currency::factory()->eur()->create(['exchange_rate' => 4.5]);

        $event = Event::factory()->create(['participant_count' => 20]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $parent = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'include_in_calculation' => true,
            'active' => true,
            'planned_price' => 100,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
        ]);

        $child = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'parent_id' => $parent->id,
            'include_in_calculation' => true,
            'active' => true,
            'planned_price' => 50,
            'currency_id' => $eur->id,
            'convert_to_pln' => false,
        ]);

        $this->seedProgramPointCost($settlement, $parent, [
            'planned_amount' => 100,
            'planned_amount_pln' => 100,
            'planned_currency_id' => $pln->id,
            'planned_rate' => 1,
            'paid_by' => 'office',
        ]);

        $this->seedProgramPointCost($settlement, $child, [
            'planned_amount' => 50,
            'planned_currency_id' => $eur->id,
            'planned_convert_to_pln' => false,
            'planned_rate' => 4.5,
            'paid_by' => 'office',
        ]);

        $summary = $this->summarizeSet($event->fresh(), $parent->fresh());

        $this->assertStringContainsString('100 PLN', $summary->plannedLabel);
        $this->assertStringContainsString('50 EUR', $summary->plannedLabel);
    }

    public function test_aggregates_two_pln_children(): void
    {
        $pln = Currency::factory()->pln()->create();
        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $parent = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'include_in_calculation' => false,
            'active' => true,
            'planned_price' => 0,
            'currency_id' => $pln->id,
        ]);

        foreach ([100, 200] as $index => $amount) {
            $child = EventProgramPoint::factory()->create([
                'event_id' => $event->id,
                'parent_id' => $parent->id,
                'order' => $index + 1,
                'include_in_calculation' => true,
                'active' => true,
                'planned_price' => $amount,
                'currency_id' => $pln->id,
            ]);

            $this->seedProgramPointCost($settlement, $child, [
                'planned_amount' => $amount,
                'planned_amount_pln' => $amount,
                'planned_currency_id' => $pln->id,
                'planned_rate' => 1,
                'paid_by' => 'office',
                'order' => $index + 1,
            ]);
        }

        $summary = $this->summarizeSet($event->fresh(), $parent->fresh());

        $this->assertSame('300 PLN', $summary->plannedLabel);
    }

    public function test_partial_paid_status_when_only_part_settled(): void
    {
        $pln = Currency::factory()->pln()->create();
        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $parent = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'include_in_calculation' => true,
            'active' => true,
            'planned_price' => 200,
            'currency_id' => $pln->id,
        ]);

        $this->seedProgramPointCost($settlement, $parent, [
            'planned_amount' => 200,
            'planned_amount_pln' => 200,
            'planned_currency_id' => $pln->id,
            'planned_rate' => 1,
            'actual_amount' => 80,
            'actual_amount_pln' => 80,
            'paid_by' => 'office',
            'payment_status' => 'partial',
        ]);

        $summary = $this->summarizeSet($event->fresh(), $parent->fresh());

        $this->assertSame(EventProgramPointPricesSummary::STATUS_PARTIAL, $summary->paidStatus);
    }

    public function test_payer_lines_include_office_and_pilot(): void
    {
        $pln = Currency::factory()->pln()->create();
        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $parent = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'include_in_calculation' => true,
            'active' => true,
            'planned_price' => 100,
            'currency_id' => $pln->id,
        ]);

        $child = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'parent_id' => $parent->id,
            'include_in_calculation' => true,
            'active' => true,
            'planned_price' => 200,
            'currency_id' => $pln->id,
        ]);

        $this->seedProgramPointCost($settlement, $parent, [
            'planned_amount' => 100,
            'planned_amount_pln' => 100,
            'planned_currency_id' => $pln->id,
            'planned_rate' => 1,
            'paid_by' => 'office',
        ]);

        $this->seedProgramPointCost($settlement, $child, [
            'planned_amount' => 200,
            'planned_amount_pln' => 200,
            'planned_currency_id' => $pln->id,
            'planned_rate' => 1,
            'paid_by' => 'pilot',
        ]);

        $summary = $this->summarizeSet($event->fresh(), $parent->fresh());

        $this->assertCount(2, $summary->payerLines);
        $this->assertStringContainsString('Biuro:', $summary->payerLines[0]);
        $this->assertStringContainsString('Pilot:', $summary->payerLines[1]);
        $this->assertTrue($summary->hasOfficeShare);
        $this->assertTrue($summary->hasPilotShare);
    }

    public function test_excludes_child_not_in_calculation(): void
    {
        $pln = Currency::factory()->pln()->create();
        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $parent = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'include_in_calculation' => true,
            'active' => true,
            'planned_price' => 100,
            'currency_id' => $pln->id,
        ]);

        $includedChild = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'parent_id' => $parent->id,
            'include_in_calculation' => true,
            'active' => true,
            'planned_price' => 50,
            'currency_id' => $pln->id,
        ]);

        $excludedChild = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'parent_id' => $parent->id,
            'include_in_calculation' => false,
            'active' => true,
            'planned_price' => 999,
            'currency_id' => $pln->id,
        ]);

        foreach ([$parent, $includedChild] as $index => $point) {
            $this->seedProgramPointCost($settlement, $point, [
                'planned_amount' => (float) $point->planned_price,
                'planned_amount_pln' => (float) $point->planned_price,
                'planned_currency_id' => $pln->id,
                'planned_rate' => 1,
                'paid_by' => 'office',
                'order' => $index + 1,
            ]);
        }

        $this->seedProgramPointCost($settlement, $excludedChild, [
            'planned_amount' => 999,
            'planned_amount_pln' => 999,
            'planned_currency_id' => $pln->id,
            'planned_rate' => 1,
            'paid_by' => 'office',
        ]);

        $summary = $this->summarizeSet($event->fresh(), $parent->fresh());

        $this->assertSame('150 PLN', $summary->plannedLabel);
    }
}
