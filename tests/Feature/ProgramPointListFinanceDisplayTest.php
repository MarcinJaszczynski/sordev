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
        $this->assertSame('płaci pilot', $summary['payerHint']);
    }

    public function test_pilot_payer_hint_shows_without_office_advance(): void
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

        $cache = new ProgramPointSettlementCostCache;
        $cache->warm(collect([$point->fresh(['currency', 'event'])]), $event);

        $summary = app(ProgramPointListFinanceDisplay::class)->summarizePoint(
            $point->fresh(['currency', 'event']),
            $cache,
        );

        $this->assertSame('płaci pilot', $summary['payerHint']);
        $this->assertNull($summary['pilotDueHint']);
        $this->assertSame('pilot', $summary['paidBy']);
    }

    public function test_plan_advance_without_payment_row_or_reservation_shows_hint(): void
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
            'payment_status' => 'advance_paid',
            'advance_amount' => 300,
            'paid_by' => 'office',
        ]);

        $this->assertFalse($point->reservations()->exists());

        $cache = new ProgramPointSettlementCostCache;
        $cache->warm(collect([$point->fresh(['currency', 'event'])]), $event);

        $summary = app(ProgramPointListFinanceDisplay::class)->summarizePoint(
            $point->fresh(['currency', 'event']),
            $cache,
        );

        $this->assertNotNull($summary['paymentHint']);
        $this->assertStringContainsString('Zaliczka', (string) $summary['paymentHint']);
        $this->assertStringContainsString('300', (string) $summary['paymentHint']);
        $this->assertSame('Zaliczka wpłacona', $summary['statusLabel']);
        $this->assertNull($summary['payerHint']);
        $this->assertNotNull($summary['advanceLine']);
        $this->assertStringContainsString('300,00 PLN', $summary['advanceLine']['text']);
    }

    public function test_advance_paid_status_without_money_does_not_look_like_a_paid_advance(): void
    {
        $eur = Currency::factory()->eur()->create(['exchange_rate' => 4.2]);
        $event = Event::factory()->create(['participant_count' => 3]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'name' => 'Luwr bilety dorośli',
            'planned_price' => 66,
            'currency_id' => $eur->id,
            'convert_to_pln' => false,
            'include_in_calculation' => true,
            'include_in_program' => false,
            'active' => true,
        ]);

        $this->seedProgramPointCost($settlement, $point, [
            'planned_amount' => 66,
            'planned_currency_id' => $eur->id,
            'planned_convert_to_pln' => false,
            'planned_rate' => 4.2,
            'payment_status' => 'advance_paid',
            'advance_type' => 'full',
            'advance_amount' => null,
            'paid_by' => 'pilot',
            'paid_at' => '2026-08-06',
        ]);

        $cache = new ProgramPointSettlementCostCache;
        $cache->warm(collect([$point->fresh(['currency', 'event'])]), $event);

        $summary = app(ProgramPointListFinanceDisplay::class)->summarizePoint(
            $point->fresh(['currency', 'event']),
            $cache,
        );

        $this->assertNull($summary['paymentHint']);
        $this->assertNull($summary['advanceLine']);
        $this->assertSame('66,00 EUR', $summary['totalLine']);
        $this->assertSame('Pilot · 66,00 EUR', $summary['remainingLine']['text'] ?? null);
    }

    public function test_paid_office_advance_splits_remainder_to_plan_payer(): void
    {
        $pln = Currency::factory()->pln()->create();
        $event = Event::factory()->create(['participant_count' => 20]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'name' => 'Pilot zagraniczne',
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
            'payment_status' => 'partially_paid',
            'paid_by' => 'pilot',
        ]);

        EventSettlementCost::query()->create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point_payment',
            'source_id' => $point->id,
            'name' => $point->name.' • zaliczka #1',
            'planned_amount' => 0,
            'planned_currency_id' => $pln->id,
            'advance_type' => 'advance',
            'advance_amount' => 500,
            'actual_amount' => 500,
            'actual_currency_id' => $pln->id,
            'actual_rate' => 1,
            'actual_amount_pln' => 500,
            'payment_status' => 'advance_paid',
            'paid_by' => 'office',
            'paid_at' => '2026-09-12',
            'order' => 2,
        ]);

        $cache = new ProgramPointSettlementCostCache;
        $cache->warm(collect([$point->fresh(['currency', 'event'])]), $event);

        $summary = app(ProgramPointListFinanceDisplay::class)->summarizePoint(
            $point->fresh(['currency', 'event']),
            $cache,
        );

        $this->assertSame('1 236,00 PLN', $summary['totalLine']);
        $this->assertSame('paid', $summary['advanceLine']['status'] ?? null);
        $this->assertSame('zapłacona · Biuro · 500,00 PLN · 12.09.2026', $summary['advanceLine']['text'] ?? null);
        $this->assertSame('Pilot · 736,00 PLN', $summary['remainingLine']['text'] ?? null);
    }

    public function test_remaining_line_omits_advance_due_date(): void
    {
        $pln = Currency::factory()->pln()->create();
        $event = Event::factory()->create(['participant_count' => 10]);
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
            'payment_status' => 'partially_paid',
            'paid_by' => 'office',
            'advance_due_date' => '2027-01-31',
        ]);

        EventSettlementCost::query()->create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point_payment',
            'source_id' => $point->id,
            'name' => $point->name.' • zaliczka',
            'planned_currency_id' => $pln->id,
            'advance_type' => 'advance',
            'advance_amount' => 500,
            'actual_amount' => 500,
            'actual_currency_id' => $pln->id,
            'actual_rate' => 1,
            'actual_amount_pln' => 500,
            'payment_status' => 'advance_paid',
            'paid_by' => 'office',
            'paid_at' => '2026-09-12',
            'order' => 2,
        ]);

        $cache = new ProgramPointSettlementCostCache;
        $cache->warm(collect([$point->fresh(['currency', 'event'])]), $event);

        $summary = app(ProgramPointListFinanceDisplay::class)->summarizePoint(
            $point->fresh(['currency', 'event']),
            $cache,
        );

        $this->assertSame('Biuro · 736,00 PLN', $summary['remainingLine']['text'] ?? null);
        $this->assertStringNotContainsString('31.01.2027', (string) ($summary['remainingLine']['text'] ?? ''));
        $this->assertStringNotContainsString('do ', (string) ($summary['remainingLine']['text'] ?? ''));
    }

    public function test_program_points_table_renders_compact_amounts_payer_and_payment_status(): void
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
            'planned_price' => 800,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $this->seedProgramPointCost($settlement, $point, [
            'planned_amount' => 800,
            'planned_amount_pln' => 800,
            'planned_currency_id' => $pln->id,
            'planned_convert_to_pln' => true,
            'planned_rate' => 1,
            'payment_status' => 'advance_paid',
            'advance_amount' => 200,
            'paid_by' => 'pilot',
        ]);

        EventSettlementCost::query()->create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point_payment',
            'source_id' => $point->id,
            'name' => $point->name.' • zaliczka #1',
            'planned_currency_id' => $pln->id,
            'advance_type' => 'advance',
            'advance_amount' => 200,
            'actual_amount' => 200,
            'actual_currency_id' => $pln->id,
            'actual_rate' => 1,
            'actual_amount_pln' => 200,
            'payment_status' => 'advance_paid',
            'paid_by' => 'office',
            'paid_at' => now(),
            'order' => 2,
        ]);

        \Livewire\Livewire::actingAs($user)
            ->test(
                \App\Filament\Resources\EventResource\RelationManagers\ProgramPointsRelationManager::class,
                [
                    'ownerRecord' => $event,
                    'pageClass' => \App\Filament\Resources\EventResource\Pages\EditEventProgram::class,
                ]
            )
            ->assertSee('Płatnik')
            ->assertSee('Pilot')
            ->assertSee('200,00 PLN')
            ->assertSee('Częściowo')
            ->assertDontSee('Zaliczka wpłacona');
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

    public function test_program_points_relation_manager_can_delete_reservation_from_drawer(): void
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

        $reservation = \App\Models\Reservation::query()->create([
            'event_id' => $event->id,
            'program_point_id' => $point->id,
            'settlement_cost_id' => $cost->id,
            'booking_reference' => 'ZLY-KLOCEK',
            'status' => 'pending',
            'participant_count' => 10,
            'reserved_at' => now(),
        ]);

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
            ->call('deleteReservation', $reservation->id)
            ->assertNotified('Usunięto rezerwację');

        $this->assertSoftDeleted('reservations', ['id' => $reservation->id]);
        $this->assertNull(
            \App\Models\Reservation::withTrashed()->find($reservation->id)?->booking_reference
        );
    }

    public function test_drawer_save_reservation_appears_on_event_with_contractor_and_dates(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $pln = Currency::factory()->pln()->create();
        $contractor = \App\Models\Contractor::create(['name' => 'Muzeum Drawer', 'status' => 'active']);
        $event = Event::factory()->create(['participant_count' => 10]);
        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'name' => 'Muzeum',
            'planned_price' => 1236,
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
            ->call('startEditReservation')
            ->assertSet('showReservationForm', true)
            ->set('reservationForm.contractor_id', $contractor->id)
            ->set('reservationForm.status', 'confirmed')
            ->set('reservationForm.booking_reference', 'DRW-1')
            ->set('reservationForm.deposit_due_at', '2026-12-30')
            ->call('saveReservation')
            ->assertNotified('Zapisano rezerwację');

        $reservation = \App\Models\Reservation::query()
            ->where('event_id', $event->id)
            ->where('program_point_id', $point->id)
            ->first();

        $this->assertNotNull($reservation);
        $this->assertSame($contractor->id, (int) $reservation->contractor_id);
        $this->assertSame('confirmed', $reservation->status);
        $this->assertSame('DRW-1', $reservation->booking_reference);
        $this->assertSame('2026-12-30', $reservation->deposit_due_at?->toDateString());
        $this->assertSame($contractor->id, (int) $point->fresh()->contractor_id);
        $this->assertSame($reservation->id, (int) $point->fresh()->reservation_id);
    }

    public function test_drawer_reservation_contractor_is_searchable(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $match = \App\Models\Contractor::create(['name' => 'Pagaj', 'city' => 'Kraków', 'status' => 'active']);
        \App\Models\Contractor::create(['name' => 'Inny Przewoźnik XYZ', 'status' => 'active']);

        $event = Event::factory()->create(['participant_count' => 10]);
        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'name' => 'Parking Paryż 1 dzień',
            'planned_price' => 600,
            'active' => true,
        ]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $cost = $settlement->upsertCostFromProgramPoint($point->fresh(['templatePoint', 'currency', 'event']));

        $component = \Livewire\Livewire::actingAs($user)
            ->test(
                \App\Filament\Resources\EventResource\RelationManagers\ProgramPointsRelationManager::class,
                [
                    'ownerRecord' => $event,
                    'pageClass' => \App\Filament\Resources\EventResource\Pages\EditEventProgram::class,
                ]
            )
            ->call('openCost', (int) $cost->id)
            ->call('startEditReservation')
            ->assertSet('showReservationForm', true)
            ->assertSee('Wyszukaj po nazwie, mieście, NIP')
            ->assertDontSee('— wybierz kontrahenta —')
            ->assertDontSee('Inny Przewoźnik XYZ')
            ->set('reservationContractorSearch', 'Pag')
            ->assertSet('showReservationContractorSearchResults', true);

        $this->assertArrayHasKey($match->id, $component->get('reservationContractorSearchResults'));

        $component
            ->call('selectReservationContractor', $match->id)
            ->assertSet('reservationForm.contractor_id', $match->id)
            ->assertSet('reservationContractorSearch', '')
            ->assertSee('Pagaj');
    }

    public function test_drawer_loads_shared_hotel_reservation_from_other_night(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $hotel = \App\Models\Contractor::create(['name' => 'Hotel Drawer Shared', 'status' => 'active']);
        $event = Event::factory()->create(['duration_days' => 3, 'participant_count' => 10]);
        $pointNight1 = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'name' => 'Nocleg 1',
            'day' => 1,
            'is_hotel' => true,
            'contractor_id' => $hotel->id,
            'planned_price' => 2000,
            'active' => true,
        ]);
        $pointNight2 = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'name' => 'Nocleg 2',
            'day' => 2,
            'is_hotel' => true,
            'contractor_id' => $hotel->id,
            'planned_price' => 2000,
            'active' => true,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $costNight2 = $settlement->upsertCostFromProgramPoint($pointNight2->fresh(['templatePoint', 'currency', 'event']));

        $reservation = \App\Models\Reservation::query()->create([
            'event_id' => $event->id,
            'program_point_id' => $pointNight1->id,
            'contractor_id' => $hotel->id,
            'status' => 'pending',
            'booking_reference' => 'HTL-SHARED',
            'participant_count' => 10,
            'reserved_at' => now(),
        ]);

        \App\Models\EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 2,
            'contractor_id' => $hotel->id,
            'event_program_point_id' => $pointNight2->id,
            'reservation_id' => $reservation->id,
        ]);

        $this->assertNotNull($pointNight2->fresh(['hotelStays.reservation'])->latestVisibleReservation());

        \Livewire\Livewire::actingAs($user)
            ->test(
                \App\Filament\Resources\EventResource\RelationManagers\ProgramPointsRelationManager::class,
                [
                    'ownerRecord' => $event,
                    'pageClass' => \App\Filament\Resources\EventResource\Pages\EditEventProgram::class,
                ]
            )
            ->call('openCost', (int) $costNight2->id)
            ->assertSet('selectedCostId', (int) $costNight2->id)
            ->call('startEditReservation')
            ->assertSet('reservationForm.reservation_id', $reservation->id)
            ->assertSet('reservationForm.booking_reference', 'HTL-SHARED')
            ->assertSet('reservationForm.contractor_id', $hotel->id)
            ->assertSet('reservationForm.coverage_label', 'Wspólna rezerwacja u Hotel Drawer Shared — dni 1, 2');
    }

    public function test_drawer_save_on_pilot_day_shares_reservation_across_template_copies(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $template = \App\Models\EventTemplateProgramPoint::factory()->create(['name' => 'Pilot zagraniczne']);
        $event = Event::factory()->create(['duration_days' => 3, 'participant_count' => 10]);
        $day1 = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'event_template_program_point_id' => $template->id,
            'name' => 'Pilot zagraniczne',
            'day' => 1,
            'planned_price' => 1236,
            'active' => true,
        ]);
        $day2 = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'event_template_program_point_id' => $template->id,
            'name' => 'Pilot zagraniczne',
            'day' => 2,
            'planned_price' => 1236,
            'active' => true,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $costDay2 = $settlement->upsertCostFromProgramPoint($day2->fresh(['templatePoint', 'currency', 'event']));

        \Livewire\Livewire::actingAs($user)
            ->test(
                \App\Filament\Resources\EventResource\RelationManagers\ProgramPointsRelationManager::class,
                [
                    'ownerRecord' => $event,
                    'pageClass' => \App\Filament\Resources\EventResource\Pages\EditEventProgram::class,
                ]
            )
            ->call('openCost', (int) $costDay2->id)
            ->call('startEditReservation')
            ->assertSet('showReservationForm', true)
            ->assertSet('reservationForm.coverage_label', null)
            ->set('reservationForm.status', 'confirmed')
            ->set('reservationForm.booking_reference', 'PILOT-OK')
            ->call('saveReservation')
            ->assertNotified('Zapisano rezerwację');

        $reservation = \App\Models\Reservation::query()->where('event_id', $event->id)->first();
        $this->assertNotNull($reservation);
        $this->assertSame('confirmed', $reservation->status);
        $this->assertSame('PILOT-OK', $reservation->booking_reference);
        $this->assertSame($reservation->id, (int) $day2->fresh()->reservation_id);
        $this->assertNull($day1->fresh()->reservation_id);
        $this->assertSame(1, \App\Models\Reservation::query()->where('event_id', $event->id)->count());
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

    public function test_advance_line_uses_reservation_deposit_due_when_no_payment_row(): void
    {
        $pln = Currency::factory()->pln()->create();
        $event = Event::factory()->create(['participant_count' => 10]);
        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'planned_price' => 800,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $this->seedProgramPointCost($settlement, $point, [
            'planned_amount' => 800,
            'planned_amount_pln' => 800,
            'planned_currency_id' => $pln->id,
            'planned_convert_to_pln' => true,
            'planned_rate' => 1,
            'payment_status' => 'planned',
            'paid_by' => 'office',
        ]);

        \App\Models\Reservation::query()->create([
            'event_id' => $event->id,
            'program_point_id' => $point->id,
            'status' => 'pending',
            'deposit_due_at' => '2026-12-30',
            'participant_count' => 10,
            'reserved_at' => now(),
        ]);

        $fresh = $point->fresh(['currency', 'event', 'reservations']);
        $cache = new ProgramPointSettlementCostCache;
        $cache->warm(collect([$fresh]), $event);

        $summary = app(ProgramPointListFinanceDisplay::class)->summarizePoint($fresh, $cache);

        $this->assertNotNull($summary['advanceLine']);
        $this->assertSame('pending', $summary['advanceLine']['status'] ?? null);
        $this->assertStringContainsString('do 30.12.2026', (string) $summary['advanceLine']['text']);
        $this->assertSame('none', $summary['paidStatus']);
        $this->assertNotNull($summary['remainingLine']);
    }

    public function test_booked_office_advance_does_not_mark_full_plan_as_paid(): void
    {
        $pln = Currency::factory()->pln()->create();
        $event = Event::factory()->create(['participant_count' => 40]);
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
            'payment_status' => 'partially_paid',
            'advance_amount' => 100,
            'paid_by' => 'pilot',
            'advance_due_date' => '2026-08-18',
        ]);

        EventSettlementCost::query()->create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point_payment',
            'source_id' => $point->id,
            'name' => $point->name.' • zaliczka',
            'planned_currency_id' => $pln->id,
            'advance_type' => 'advance',
            'advance_amount' => 100,
            'actual_amount' => 100,
            'actual_currency_id' => $pln->id,
            'actual_rate' => 1,
            'actual_amount_pln' => 100,
            'payment_status' => 'advance_paid',
            'paid_by' => 'office',
            'paid_at' => '2026-08-18',
            'order' => 2,
        ]);

        $cache = new ProgramPointSettlementCostCache;
        $cache->warm(collect([$point->fresh(['currency', 'event'])]), $event);
        $summary = app(ProgramPointListFinanceDisplay::class)->summarizePoint(
            $point->fresh(['currency', 'event']),
            $cache,
        );

        $this->assertSame('100,00 PLN', $summary['paid']);
        $this->assertSame('partial', $summary['paidStatus']);
        $this->assertSame('paid', $summary['advanceLine']['status'] ?? null);
        $this->assertStringContainsString('Biuro', (string) ($summary['advanceLine']['text'] ?? ''));
        $this->assertStringContainsString('100,00 PLN', (string) ($summary['advanceLine']['text'] ?? ''));
        $this->assertSame('Pilot · 400,00 PLN', $summary['remainingLine']['text'] ?? null);
    }

    public function test_reservation_without_payment_is_not_shown_as_fully_paid(): void
    {
        $pln = Currency::factory()->pln()->create();
        $event = Event::factory()->create(['participant_count' => 40]);
        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'planned_price' => 2400,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $this->seedProgramPointCost($settlement, $point, [
            'planned_amount' => 2400,
            'planned_amount_pln' => 2400,
            'planned_currency_id' => $pln->id,
            'planned_convert_to_pln' => true,
            'planned_rate' => 1,
            'payment_status' => 'planned',
            'advance_amount' => 2400,
            'paid_by' => 'office',
            'advance_due_date' => '2026-08-24',
        ]);

        \App\Models\Reservation::query()->create([
            'event_id' => $event->id,
            'program_point_id' => $point->id,
            'status' => 'confirmed',
            'deposit_due_at' => '2026-08-24',
            'participant_count' => 40,
            'reserved_at' => now(),
            'confirmed_at' => now(),
        ]);

        $fresh = $point->fresh(['currency', 'event', 'reservations']);
        $cache = new ProgramPointSettlementCostCache;
        $cache->warm(collect([$fresh]), $event);
        $summary = app(ProgramPointListFinanceDisplay::class)->summarizePoint($fresh, $cache);

        $this->assertSame('none', $summary['paidStatus']);
        $this->assertNotSame('full', $summary['paidStatus']);
        $this->assertNotNull($summary['remainingLine']);
        $this->assertStringContainsString('2 400,00 PLN', (string) ($summary['remainingLine']['text'] ?? ''));
    }
}
