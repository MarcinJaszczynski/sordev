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

    public function test_planned_falls_back_to_point_price_when_settlement_plan_is_zero(): void
    {
        $eur = Currency::factory()->eur()->create(['exchange_rate' => 4.35]);
        $event = Event::factory()->create(['participant_count' => 45]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'unit_price' => 66,
            'group_size' => 1,
            'planned_price' => 66,
            'currency_id' => $eur->id,
            'convert_to_pln' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $this->seedProgramPointCost($settlement, $point, [
            'planned_amount' => 0,
            'planned_amount_pln' => 0,
            'planned_currency_id' => $eur->id,
            'planned_convert_to_pln' => true,
            'planned_rate' => 4.35,
            'payment_status' => 'advance_paid',
            'advance_amount' => 30,
            'paid_by' => 'office',
        ]);

        EventSettlementCost::query()->create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point_payment',
            'source_id' => $point->id,
            'name' => $point->name.' • zaliczka #1',
            'planned_currency_id' => $eur->id,
            'advance_type' => 'advance',
            'advance_amount' => 30,
            'actual_amount' => 30,
            'actual_currency_id' => $eur->id,
            'actual_rate' => 4.35,
            'actual_amount_pln' => 130.5,
            'payment_status' => 'advance_paid',
            'paid_by' => 'office',
            'paid_at' => now(),
            'order' => 2,
        ]);

        $cache = new ProgramPointSettlementCostCache;
        $cache->warm(collect([$point->fresh(['currency', 'event'])]), $event);

        $summary = app(ProgramPointListFinanceDisplay::class)->summarizePoint(
            $point->fresh(['currency', 'event']),
            $cache,
        );

        $this->assertStringContainsString('66', $summary['planned']);
        $this->assertStringContainsString('EUR', $summary['planned']);
        $this->assertNotNull($summary['plannedSub']);
        $this->assertStringContainsString('≈', (string) $summary['plannedSub']);
        $this->assertStringContainsString('30', $summary['paid']);
        $this->assertSame('partial', $summary['paidStatus']);
        $this->assertSame('Zaliczka wpłacona', $summary['statusLabel']);
    }

    public function test_foreign_labels_omit_pln_when_convert_disabled(): void
    {
        $eur = Currency::factory()->eur()->create(['exchange_rate' => 4.35]);
        $event = Event::factory()->create(['participant_count' => 10]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'event_template_program_point_id' => null,
            'unit_price' => 50,
            'group_size' => 1,
            'planned_price' => 500,
            'currency_id' => $eur->id,
            'convert_to_pln' => false,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $this->seedProgramPointCost($settlement, $point, [
            'planned_amount' => 500,
            'planned_amount_pln' => null,
            'planned_currency_id' => $eur->id,
            'planned_convert_to_pln' => false,
            'planned_rate' => 4.35,
            'payment_status' => 'planned',
            'paid_by' => 'office',
        ]);

        $cache = new ProgramPointSettlementCostCache;
        $cache->warm(collect([$point->fresh(['currency', 'event'])]), $event);

        $summary = app(ProgramPointListFinanceDisplay::class)->summarizePoint(
            $point->fresh(['currency', 'event']),
            $cache,
        );

        $this->assertSame('500,00 EUR', $summary['planned']);
        $this->assertStringNotContainsString('≈', $summary['planned']);
        $this->assertStringNotContainsString('≈', $summary['calc']);
    }

    public function test_foreign_labels_include_pln_when_convert_enabled(): void
    {
        $eur = Currency::factory()->eur()->create(['exchange_rate' => 4.35]);
        $event = Event::factory()->create(['participant_count' => 10]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'event_template_program_point_id' => null,
            'unit_price' => 50,
            'group_size' => 1,
            'planned_price' => 500,
            'currency_id' => $eur->id,
            'convert_to_pln' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $this->seedProgramPointCost($settlement, $point, [
            'planned_amount' => 500,
            'planned_amount_pln' => 2175,
            'planned_currency_id' => $eur->id,
            'planned_convert_to_pln' => true,
            'planned_rate' => 4.35,
            'payment_status' => 'planned',
            'paid_by' => 'office',
        ]);

        $cache = new ProgramPointSettlementCostCache;
        $cache->warm(collect([$point->fresh(['currency', 'event'])]), $event);

        $summary = app(ProgramPointListFinanceDisplay::class)->summarizePoint(
            $point->fresh(['currency', 'event']),
            $cache,
        );

        $this->assertSame('500,00 EUR', $summary['planned']);
        $this->assertSame('≈ 2 175,00 PLN', $summary['plannedSub']);
        $this->assertSame('500,00 EUR', $summary['calc']);
        $this->assertSame('≈ 2 175,00 PLN', $summary['calcSub']);
    }

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
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole('admin');
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
            'advance_due_date' => '2026-05-12',
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
        $this->assertStringContainsString('Zaliczka', $summary['advanceHtml']);
        $this->assertStringContainsString('Biuro', $summary['advanceHtml']);
        $this->assertStringContainsString('do 12.05.2026', $summary['advanceHtml']);
        $this->assertSame('Do dopłaty 500,00 PLN', $summary['remainingHint']);
    }

    public function test_summarize_point_shows_pilot_due_after_office_advance(): void
    {
        $pln = Currency::factory()->pln()->create();
        $event = Event::factory()->create(['participant_count' => 20]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'planned_price' => 500,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $this->seedProgramPointCost($settlement, $point, [
            'planned_amount' => 500,
            'planned_amount_pln' => 500,
            'planned_currency_id' => $pln->id,
            'planned_convert_to_pln' => true,
            'planned_rate' => 1,
            'paid_by' => 'pilot',
        ]);

        EventSettlementCost::query()->create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point_payment',
            'source_id' => $point->id,
            'name' => $point->name.' • zaliczka biuro',
            'planned_amount' => 0,
            'planned_currency_id' => $pln->id,
            'planned_convert_to_pln' => true,
            'planned_rate' => 1,
            'advance_type' => 'advance',
            'actual_amount' => 100,
            'actual_currency_id' => $pln->id,
            'actual_rate' => 1,
            'actual_amount_pln' => 100,
            'payment_status' => 'advance_paid',
            'paid_by' => 'office',
            'paid_at' => now(),
            'order' => 2,
        ]);

        $cache = new ProgramPointSettlementCostCache;
        $cache->warm(collect([$point->fresh(['currency', 'event'])]), $event);

        $summary = app(ProgramPointListFinanceDisplay::class)->summarizePoint(
            $point->fresh(['currency', 'event']),
            $cache,
        );

        $this->assertStringContainsString('Zaliczka Biuro', (string) $summary['paymentHint']);
        $this->assertStringContainsString('Do pilota', (string) $summary['pilotDueHint']);
        $this->assertStringContainsString('400', (string) $summary['pilotDueHint']);
    }

    public function test_program_points_relation_manager_opens_finance_drawer(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $pln = Currency::factory()->pln()->create();
        $event = Event::factory()->create(['participant_count' => 10]);
        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'name' => 'Muzeum',
            'planned_price' => 500,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $cost = $settlement->upsertCostFromProgramPoint($point->fresh(['templatePoint', 'currency', 'event']));

        \Livewire\Livewire::actingAs($user)
            ->test(
                \App\Filament\Resources\EventResource\RelationManagers\ProgramPointsRelationManager::class,
                [
                    'ownerRecord' => $event,
                    'pageClass' => \App\Filament\Resources\EventResource\Pages\EditEventProgram::class,
                ]
            )
            ->callTableAction('open_finance', $point)
            ->assertSet('selectedCostId', (int) $cost->id)
            ->assertSee('Zamknij')
            ->call('startAddAdvance', 'office')
            ->assertSet('showPaymentForm', true)
            ->assertSet('paymentForm.paid_by', 'office')
            ->assertSet('paymentForm.advance_type', 'advance');
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
