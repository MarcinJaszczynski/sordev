<?php

namespace Tests\Feature;

use App\Actions\Finance\RecordSettlementCostPaymentAction;
use App\Data\RecordSettlementCostPaymentData;
use App\Models\Contractor;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventHotelRoomLine;
use App\Models\EventHotelStay;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\User;
use App\Services\EventFinanceOverviewService;
use App\Services\HotelStaySettlementSync;
use App\Services\ProgramPointListFinanceDisplay;
use App\Services\ProgramPointSettlementCostCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HotelProgramPointFinanceVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('admin');
        $this->actingAs($user);
    }

    public function test_hotel_payment_shows_on_program_point_and_opens_accommodation_cost(): void
    {
        $hotel = Contractor::create(['name' => 'Hotel Visibility', 'status' => 'active']);
        $event = Event::factory()->create(['duration_days' => 3, 'participant_count' => 10]);

        $night1 = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'name' => 'Nocleg D1',
            'day' => 1,
            'is_hotel' => true,
            'contractor_id' => $hotel->id,
            'active' => true,
            'include_in_calculation' => true,
            'planned_price' => 0,
        ]);
        $night2 = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'name' => 'Nocleg D2',
            'day' => 2,
            'is_hotel' => true,
            'contractor_id' => $hotel->id,
            'active' => true,
            'include_in_calculation' => true,
            'planned_price' => 0,
        ]);

        foreach ([1, 2] as $day) {
            $stay = EventHotelStay::create([
                'event_id' => $event->id,
                'day' => $day,
                'contractor_id' => $hotel->id,
                'event_program_point_id' => $day === 1 ? $night1->id : $night2->id,
            ]);
            $stay->roomLines()->create([
                'label' => 'Double',
                'role' => 'qty',
                'quantity' => 5,
                'people_count' => 2,
                'unit_price' => 100,
                'price_basis' => EventHotelRoomLine::PRICE_BASIS_PER_ROOM,
                'convert_to_pln' => true,
                'order' => 0,
            ]);
        }

        app(HotelStaySettlementSync::class)->syncForEvent($event->fresh(['hotelStays.roomLines']));

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $hotelCost = $settlement->costs()
            ->where('source_type', HotelStaySettlementSync::SOURCE_HOTEL)
            ->where('source_id', $hotel->id)
            ->first();

        $this->assertNotNull($hotelCost);
        $this->assertEqualsWithDelta(1000.0, (float) $hotelCost->planned_amount_pln, 0.01);

        app(RecordSettlementCostPaymentAction::class)(new RecordSettlementCostPaymentData(
            planCost: $hotelCost,
            amountPln: 400,
            advanceType: 'advance',
            paidBy: 'office',
        ));

        $event = $event->fresh(['hotelStays', 'activeSettlement.costs']);
        $cache = new ProgramPointSettlementCostCache;
        $cache->warm(collect([$night1->fresh(), $night2->fresh()]), $event);

        $this->assertSame((int) $hotelCost->id, (int) $cache->baseCost((int) $night1->id)?->id);
        $this->assertSame((int) $hotelCost->id, (int) $cache->baseCost((int) $night2->id)?->id);

        $summary = app(ProgramPointListFinanceDisplay::class)->summarizePoint(
            $night1->fresh(['currency', 'event']),
            $cache,
        );

        $this->assertStringContainsString('400', $summary['paid']);
        $this->assertContains($summary['paidStatus'], ['partial', 'advance']);

        $resolved = app(HotelStaySettlementSync::class)->findForProgramPoint($event, $night2->fresh(['hotelStays']));
        $this->assertNotNull($resolved);
        $this->assertSame((int) $hotelCost->id, (int) $resolved->id);
    }

    public function test_finance_overview_hides_hotel_program_point_duplicates_and_groups_by_contractor(): void
    {
        $hotel = Contractor::create(['name' => 'Hotel Rollup', 'status' => 'active']);
        $event = Event::factory()->create(['duration_days' => 2, 'participant_count' => 8]);

        $night = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'name' => 'Nocleg',
            'day' => 1,
            'is_hotel' => true,
            'contractor_id' => $hotel->id,
            'active' => true,
            'include_in_calculation' => true,
            'planned_price' => 500,
        ]);
        $service = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'name' => 'Bankiet',
            'day' => 1,
            'is_hotel' => false,
            'is_hotel_service' => true,
            'contractor_id' => $hotel->id,
            'active' => true,
            'include_in_calculation' => true,
            'planned_price' => 300,
        ]);

        $stay = EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 1,
            'contractor_id' => $hotel->id,
            'event_program_point_id' => $night->id,
        ]);
        $stay->roomLines()->create([
            'label' => 'Twin',
            'role' => 'qty',
            'quantity' => 4,
            'people_count' => 2,
            'unit_price' => 150,
            'price_basis' => EventHotelRoomLine::PRICE_BASIS_PER_ROOM,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        $event->refreshActiveSettlementCosts();

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $settlement->upsertCostFromProgramPoint($service->fresh(['templatePoint', 'currency', 'event', 'reservations']));

        // Stary zdublowany koszt noclegu jako program_point — overview ma go ukryć.
        $settlement->upsertCostFromProgramPoint($night->fresh(['templatePoint', 'currency', 'event', 'reservations']));

        $hotelCost = $settlement->fresh()->costs()
            ->where('source_type', HotelStaySettlementSync::SOURCE_HOTEL)
            ->where('source_id', $hotel->id)
            ->first();
        $this->assertNotNull($hotelCost);

        app(RecordSettlementCostPaymentAction::class)(new RecordSettlementCostPaymentData(
            planCost: $hotelCost,
            amountPln: 200,
            advanceType: 'advance',
            paidBy: 'office',
        ));

        EventFinanceOverviewService::forgetOverviewCacheForEvent((int) $event->id);
        $overview = app(EventFinanceOverviewService::class)->forEvent(
            $event->fresh(),
            EventFinanceOverviewService::FILTER_ALL,
            null,
            hideZero: false,
        );

        $hotelProgramPointRows = collect($overview['rows'])
            ->filter(fn (array $row): bool => ($row['source_type'] ?? '') === 'program_point'
                && str_contains((string) ($row['name'] ?? ''), 'Nocleg'));

        $this->assertTrue($hotelProgramPointRows->isEmpty(), 'Duplikat program_point noclegu nie powinien być w overview');

        $hotelRows = collect($overview['rows'])
            ->filter(fn (array $row): bool => ($row['source_type'] ?? '') === HotelStaySettlementSync::SOURCE_HOTEL);
        $this->assertCount(1, $hotelRows);

        $rollups = collect($overview['contractor_rollups'] ?? []);
        $this->assertTrue($rollups->isNotEmpty());
        $hotelRollup = $rollups->firstWhere('contractor_id', $hotel->id);
        $this->assertNotNull($hotelRollup);
        $this->assertGreaterThanOrEqual(2, (int) $hotelRollup['cost_count']);
        $this->assertEqualsWithDelta(200.0, (float) $hotelRollup['paid_pln'], 0.01);
        $this->assertGreaterThan(0.01, (float) $hotelRollup['planned_pln']);
        $this->assertGreaterThan(0.01, (float) $hotelRollup['remaining_pln']);
    }

    public function test_hotel_eur_with_convert_keeps_foreign_amount_on_settlement_and_finance_label(): void
    {
        $eur = Currency::query()->firstOrCreate(
            ['symbol' => 'EUR'],
            ['name' => 'Euro', 'code' => 'EUR', 'exchange_rate' => 4.30]
        );
        $eur->forceFill(['exchange_rate' => 4.30, 'code' => 'EUR'])->save();
        Currency::clearPlnIdsCache();

        $hotel = Contractor::create(['name' => 'Hotel EUR', 'status' => 'active']);
        $event = Event::factory()->create(['duration_days' => 1, 'participant_count' => 10]);

        $stay = EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 1,
            'contractor_id' => $hotel->id,
        ]);
        $stay->roomLines()->create([
            'label' => 'Double',
            'role' => 'qty',
            'quantity' => 5,
            'people_count' => 2,
            'unit_price' => 100,
            'price_basis' => EventHotelRoomLine::PRICE_BASIS_PER_ROOM,
            'currency_id' => $eur->id,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        app(HotelStaySettlementSync::class)->syncForEvent($event->fresh(['hotelStays.roomLines.currency']));

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $hotelCost = $settlement->costs()
            ->where('source_type', HotelStaySettlementSync::SOURCE_HOTEL)
            ->where('source_id', $hotel->id)
            ->first();

        $this->assertNotNull($hotelCost);
        $this->assertSame((int) $eur->id, (int) $hotelCost->planned_currency_id);
        $this->assertTrue((bool) $hotelCost->planned_convert_to_pln);
        $this->assertEqualsWithDelta(500.0, (float) $hotelCost->planned_amount, 0.01);
        $this->assertEqualsWithDelta(2150.0, (float) $hotelCost->planned_amount_pln, 0.01);
        $this->assertEqualsWithDelta(4.30, (float) $hotelCost->planned_rate, 0.01);

        EventFinanceOverviewService::forgetOverviewCacheForEvent((int) $event->id);
        $overview = app(EventFinanceOverviewService::class)->forEvent(
            $event->fresh(),
            EventFinanceOverviewService::FILTER_ALL,
            null,
            hideZero: false,
        );

        $row = collect($overview['rows'])
            ->first(fn (array $r): bool => (int) ($r['cost_id'] ?? 0) === (int) $hotelCost->id);

        $this->assertNotNull($row);
        $this->assertStringContainsString('EUR', (string) $row['planned_label']);
        $this->assertStringContainsString('500', (string) $row['planned_label']);
        $this->assertStringContainsString('PLN', (string) $row['planned_label']);
        $this->assertStringContainsString('2 150', (string) $row['planned_label']);
    }

    public function test_hotel_eur_without_convert_stores_foreign_amount_without_pln(): void
    {
        $eur = Currency::query()->firstOrCreate(
            ['symbol' => 'EUR'],
            ['name' => 'Euro', 'code' => 'EUR', 'exchange_rate' => 4.30]
        );
        $eur->forceFill(['exchange_rate' => 4.30, 'code' => 'EUR'])->save();
        Currency::clearPlnIdsCache();

        $hotel = Contractor::create(['name' => 'Hotel EUR raw', 'status' => 'active']);
        $event = Event::factory()->create(['duration_days' => 1, 'participant_count' => 10]);

        $stay = EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 1,
            'contractor_id' => $hotel->id,
            'pricing_mode' => 'flat_night',
            'flat_amount' => 80,
            'flat_currency_id' => $eur->id,
            'flat_convert_to_pln' => false,
        ]);
        $stay->roomLines()->create([
            'label' => 'placeholder',
            'role' => 'qty',
            'quantity' => 1,
            'people_count' => 1,
            'unit_price' => 0,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        app(HotelStaySettlementSync::class)->syncForEvent($event->fresh(['hotelStays.roomLines.currency']));

        $hotelCost = EventSettlement::findOrCreateActiveForEvent($event)->costs()
            ->where('source_type', HotelStaySettlementSync::SOURCE_HOTEL)
            ->where('source_id', $hotel->id)
            ->first();

        $this->assertNotNull($hotelCost);
        $this->assertSame((int) $eur->id, (int) $hotelCost->planned_currency_id);
        $this->assertFalse((bool) $hotelCost->planned_convert_to_pln);
        $this->assertEqualsWithDelta(80.0, (float) $hotelCost->planned_amount, 0.01);
        $this->assertNull($hotelCost->planned_amount_pln);
    }
}
