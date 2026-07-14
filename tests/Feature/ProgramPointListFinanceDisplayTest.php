<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Concerns\ManagesProgramPointSettlementFinance;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\User;
use App\Services\ProgramPointListFinanceDisplay;
use App\Services\ProgramPointSetFinanceAggregator;
use App\Services\ProgramPointSettlementCostCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramPointListFinanceDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_planned_label_uses_settlement_planned_amount_when_point_planned_price_differs(): void
    {
        $pln = Currency::factory()->pln()->create();

        $event = Event::factory()->create(['participant_count' => 20]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'planned_price' => 100,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        EventSettlementCost::query()->updateOrCreate(
            [
                'settlement_id' => $settlement->id,
                'source_type' => 'program_point',
                'source_id' => $point->id,
            ],
            [
                'name' => $point->name,
                'planned_amount' => 750,
                'planned_currency_id' => $pln->id,
                'planned_convert_to_pln' => true,
                'planned_rate' => 1,
                'planned_amount_pln' => 750,
                'payment_status' => 'planned',
                'paid_by' => 'office',
                'order' => 1,
            ],
        );

        $cache = new ProgramPointSettlementCostCache;
        $cache->warm(collect([$point->fresh(['currency', 'event'])]), $event);

        $summary = app(ProgramPointListFinanceDisplay::class)->summarizePoint(
            $point->fresh(['currency', 'event']),
            $cache,
        );

        $this->assertStringContainsString('750', $summary['planned']);
        $this->assertStringNotContainsString('100', $summary['planned']);
    }

    public function test_persist_settle_point_finance_syncs_planned_price_on_point(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $pln = Currency::factory()->pln()->create();

        $event = Event::factory()->create(['participant_count' => 20]);
        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'planned_price' => 100,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $helper = new class($event)
        {
            use ManagesProgramPointSettlementFinance;

            public function __construct(private Event $event) {}

            protected function settlementOwnerEvent(): Event
            {
                return $this->event;
            }

            protected function afterSettlePointFinanceSaved(): void {}

            public function save(EventProgramPoint $point, array $data): array
            {
                return $this->persistSettlePointFinance($point, $data, false);
            }
        };

        $helper->save($point->fresh(['currency', 'templatePoint', 'event']), [
            'settlement_planned_amount' => 420,
            'settlement_planned_currency_id' => $pln->id,
            'settlement_planned_convert_to_pln' => true,
            'settlement_planned_rate' => 1,
            'settlement_planned_amount_pln' => 420,
            'settlement_paid_by' => 'office',
        ]);

        $this->assertSame(420.0, (float) $point->fresh()->planned_price);

        $cost = EventSettlementCost::query()
            ->where('source_type', 'program_point')
            ->where('source_id', $point->id)
            ->first();

        $this->assertNotNull($cost);
        $this->assertSame(420.0, (float) $cost->planned_amount);
    }

    public function test_fresh_cache_reflects_updated_settlement_planned_amount(): void
    {
        $pln = Currency::factory()->pln()->create();

        $event = Event::factory()->create(['participant_count' => 20]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'planned_price' => 1236,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $this->seedProgramPointCost($settlement, $point, [
            'planned_amount' => 1236,
            'planned_amount_pln' => 1236,
            'planned_currency_id' => $pln->id,
            'planned_convert_to_pln' => true,
            'planned_rate' => 1,
        ]);

        $staleCache = new ProgramPointSettlementCostCache;
        $staleCache->warm(collect([$point->fresh(['currency', 'event'])]), $event);

        EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->where('source_type', 'program_point')
            ->where('source_id', $point->id)
            ->update(['planned_amount' => 1500, 'planned_amount_pln' => 1500]);

        $point->update(['planned_price' => 1500]);

        $freshCache = new ProgramPointSettlementCostCache;
        $freshCache->warm(collect([$point->fresh(['currency', 'event'])]), $event);

        $staleSummary = app(ProgramPointListFinanceDisplay::class)->summarizePoint(
            $point->fresh(['currency', 'event']),
            $staleCache,
        );

        $freshSummary = app(ProgramPointListFinanceDisplay::class)->summarizePoint(
            $point->fresh(['currency', 'event']),
            $freshCache,
        );

        $this->assertStringContainsString('1 236', $staleSummary['planned']);
        $this->assertStringContainsString('1 500', $freshSummary['planned']);
    }

    public function test_set_rollup_reflects_updated_child_settlement_planned_amount(): void
    {
        $pln = Currency::factory()->pln()->create();

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
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
        ]);

        $this->seedProgramPointCost($settlement, $parent, [
            'planned_amount' => 100,
            'planned_amount_pln' => 100,
            'planned_currency_id' => $pln->id,
            'planned_convert_to_pln' => true,
            'planned_rate' => 1,
        ]);

        $this->seedProgramPointCost($settlement, $child, [
            'planned_amount' => 50,
            'planned_amount_pln' => 50,
            'planned_currency_id' => $pln->id,
            'planned_convert_to_pln' => true,
            'planned_rate' => 1,
        ]);

        $parent = $parent->fresh()->loadCount('children');
        $cache = new ProgramPointSettlementCostCache;
        $cache->warm(collect([$parent]), $event);

        $summaryBefore = app(ProgramPointSetFinanceAggregator::class, [
            'costCache' => $cache,
        ])->summarize($parent, $event);

        $this->assertStringContainsString('150', $summaryBefore->plannedLabel);

        EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->where('source_type', 'program_point')
            ->where('source_id', $child->id)
            ->update(['planned_amount' => 200, 'planned_amount_pln' => 200]);

        $cacheAfter = new ProgramPointSettlementCostCache;
        $cacheAfter->warm(collect([$parent->fresh()->loadCount('children')]), $event);

        $summaryAfter = app(ProgramPointSetFinanceAggregator::class, [
            'costCache' => $cacheAfter,
        ])->summarize($parent->fresh()->loadCount('children'), $event);

        $this->assertStringContainsString('300', $summaryAfter->plannedLabel);
        $this->assertStringNotContainsString('150', $summaryAfter->plannedLabel);
    }

    public function test_advance_line_shows_remaining_to_pay_as_planned_minus_paid(): void
    {
        $pln = Currency::factory()->pln()->create();

        $event = Event::factory()->create(['participant_count' => 20]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'planned_price' => 1000,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $this->seedProgramPointCost($settlement, $point, [
            'planned_amount' => 1000,
            'planned_amount_pln' => 1000,
            'planned_currency_id' => $pln->id,
            'planned_convert_to_pln' => true,
            'planned_rate' => 1,
            'advance_amount' => 300,
        ]);

        EventSettlementCost::query()->create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point_payment',
            'source_id' => $point->id,
            'name' => $point->name.' • zaliczka #1',
            'planned_amount' => 0,
            'planned_currency_id' => $pln->id,
            'planned_convert_to_pln' => true,
            'planned_rate' => 1,
            'advance_type' => 'advance',
            'advance_amount' => 300,
            'actual_amount' => 300,
            'actual_currency_id' => $pln->id,
            'actual_rate' => 1,
            'actual_amount_pln' => 300,
            'payment_status' => 'advance_paid',
            'paid_by' => 'office',
            'paid_at' => now(),
            'order' => 2,
        ]);

        EventSettlementCost::query()->create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point_payment',
            'source_id' => $point->id,
            'name' => $point->name.' • wpłata #1',
            'planned_amount' => 0,
            'planned_currency_id' => $pln->id,
            'planned_convert_to_pln' => true,
            'planned_rate' => 1,
            'advance_type' => 'full',
            'actual_amount' => 200,
            'actual_currency_id' => $pln->id,
            'actual_rate' => 1,
            'actual_amount_pln' => 200,
            'payment_status' => 'paid',
            'paid_by' => 'office',
            'paid_at' => now(),
            'order' => 3,
        ]);

        $cache = new ProgramPointSettlementCostCache;
        $cache->warm(collect([$point->fresh(['currency', 'event'])]), $event);

        $summary = app(ProgramPointListFinanceDisplay::class)->summarizePoint(
            $point->fresh(['currency', 'event']),
            $cache,
        );

        $this->assertNotNull($summary['advanceHtml']);
        $this->assertStringContainsString('do dop.', $summary['advanceHtml']);
        $this->assertStringContainsString('500', $summary['advanceHtml']);
        $this->assertStringContainsString('Zaliczka', $summary['advanceHtml']);
    }

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
                'paid_by' => 'office',
                'order' => 1,
                ...$attributes,
            ],
        );
    }
}
