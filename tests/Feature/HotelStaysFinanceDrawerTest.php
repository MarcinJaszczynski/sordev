<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\EventHotelStaysFinancePanel;
use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventHotelRoomLine;
use App\Models\EventHotelStay;
use App\Models\EventSettlement;
use App\Models\User;
use App\Services\EventFinanceOverviewService;
use App\Services\HotelStaySettlementSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HotelStaysFinanceDrawerTest extends TestCase
{
    use RefreshDatabase;

    public function test_hotel_drawer_shows_offer_s_planned_p_and_payment_actions(): void
    {
        Role::findOrCreate('admin');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $hotel = Contractor::create(['name' => 'Hotel Drawer S/P', 'status' => 'active']);
        $event = Event::factory()->create([
            'duration_days' => 2,
            'participant_count' => 2,
        ]);

        $stay = EventHotelStay::query()->create([
            'event_id' => $event->id,
            'day' => 1,
            'contractor_id' => $hotel->id,
            'pricing_mode' => 'lines',
        ]);

        EventHotelRoomLine::query()->create([
            'event_hotel_stay_id' => $stay->id,
            'price_layer' => EventHotelRoomLine::LAYER_OFFER,
            'label' => 'Twin S',
            'role' => 'qty',
            'quantity' => 1,
            'people_count' => 2,
            'unit_price' => 400,
            'price_basis' => EventHotelRoomLine::PRICE_BASIS_PER_ROOM,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        EventHotelRoomLine::query()->create([
            'event_hotel_stay_id' => $stay->id,
            'price_layer' => EventHotelRoomLine::LAYER_NEGOTIATED,
            'label' => 'Twin P',
            'role' => 'qty',
            'quantity' => 1,
            'people_count' => 2,
            'unit_price' => 360,
            'price_basis' => EventHotelRoomLine::PRICE_BASIS_PER_ROOM,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        $sync = app(HotelStaySettlementSync::class);
        $cost = $sync->ensureForContractor($event->fresh([
            'hotelStays.roomLines',
            'hotelStays.offerRoomLines',
        ]), (int) $hotel->id);

        $this->assertNotNull($cost);
        $this->assertEqualsWithDelta(360.0, (float) $cost->planned_amount_pln, 0.01);
        $this->assertEqualsWithDelta(
            400.0,
            $sync->offerTotalPlnForContractor($event->fresh([
                'hotelStays.roomLines',
                'hotelStays.offerRoomLines',
            ]), (int) $hotel->id),
            0.01,
        );

        EventFinanceOverviewService::forgetOverviewCacheForEvent((int) $event->id);
        $overview = app(EventFinanceOverviewService::class)->forEvent(
            $event->fresh(),
            EventFinanceOverviewService::FILTER_ALL,
            null,
            hideZero: false,
        );
        $row = collect($overview['rows'])
            ->first(fn (array $candidate): bool => (int) ($candidate['cost_id'] ?? 0) === (int) $cost->id);

        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(400.0, (float) $row['calculation_pln'], 0.01);
        $this->assertEqualsWithDelta(360.0, (float) $row['planned_pln'], 0.01);
        $this->assertStringContainsString('Oferta S', (string) ($row['pricing_hint'] ?? ''));

        Livewire::actingAs($user)
            ->test(EventHotelStaysFinancePanel::class, [
                'eventId' => (int) $event->id,
                'variant' => 'overview',
            ])
            ->assertSee('Płatności wg hotelu')
            ->assertSee('Hotel Drawer S/P')
            ->call('openHotelGroupFinance', (int) $hotel->id)
            ->assertSet('selectedCostId', (int) $cost->id)
            ->assertSet('selectedRow.cost_id', (int) $cost->id)
            ->assertSee('Szablon (S)')
            ->assertSee('Planowane (P)')
            ->assertSee('Wpłaty')
            ->assertSee('Dodaj zaliczkę')
            ->assertDontSee('Nie udało się wczytać pozycji kosztu');
    }

    public function test_hotel_drawer_records_advance_like_other_finance_drawers(): void
    {
        Role::findOrCreate('admin');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $hotel = Contractor::create(['name' => 'Hotel Wpłata', 'status' => 'active']);
        $event = Event::factory()->create([
            'duration_days' => 2,
            'participant_count' => 2,
        ]);

        $stay = EventHotelStay::query()->create([
            'event_id' => $event->id,
            'day' => 1,
            'contractor_id' => $hotel->id,
            'pricing_mode' => 'lines',
        ]);

        EventHotelRoomLine::query()->create([
            'event_hotel_stay_id' => $stay->id,
            'price_layer' => EventHotelRoomLine::LAYER_NEGOTIATED,
            'label' => 'Twin',
            'role' => 'qty',
            'quantity' => 2,
            'people_count' => 2,
            'unit_price' => 250,
            'price_basis' => EventHotelRoomLine::PRICE_BASIS_PER_ROOM,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        $cost = app(HotelStaySettlementSync::class)->ensureForContractor(
            $event->fresh(['hotelStays.roomLines']),
            (int) $hotel->id,
        );
        $this->assertNotNull($cost);

        $pln = \App\Models\Currency::query()->firstOrCreate(
            ['symbol' => 'PLN'],
            ['name' => 'Złoty', 'code' => 'PLN', 'exchange_rate' => 1]
        );
        \App\Models\Currency::clearPlnIdsCache();

        Livewire::actingAs($user)
            ->test(EventHotelStaysFinancePanel::class, [
                'eventId' => (int) $event->id,
                'variant' => 'overview',
            ])
            ->call('openHotelGroupFinance', (int) $hotel->id)
            ->assertSet('selectedCostId', (int) $cost->id)
            ->call('startAddAdvance', 'office')
            ->assertSet('showPaymentForm', true)
            ->set('paymentForm.currency_id', $pln->id)
            ->set('paymentForm.amount_pln', 100)
            ->set('paymentForm.payment_method', 'transfer')
            ->set('paymentForm.paid_at', now()->toDateString())
            ->call('savePayment')
            ->assertHasNoErrors();

        $settlement = EventSettlement::findOrCreateActiveForEvent($event->fresh());
        $paid = (float) app(\App\Services\SettlementPaymentHealthService::class)
            ->paidPlnForPlanCost($cost->fresh(), $settlement->costs()->get());

        $this->assertEqualsWithDelta(100.0, $paid, 0.01);
    }

    public function test_flat_stay_drawer_matches_event_flat_total(): void
    {
        Role::findOrCreate('admin');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $eur = \App\Models\Currency::query()->firstOrCreate(
            ['symbol' => 'EUR'],
            ['name' => 'Euro', 'code' => 'EUR', 'exchange_rate' => 4.0]
        );
        $eur->forceFill(['exchange_rate' => 4.0, 'code' => 'EUR'])->save();
        \App\Models\Currency::clearPlnIdsCache();

        $hotel = Contractor::create(['name' => 'Hotel Flat', 'status' => 'active']);
        $event = Event::factory()->create([
            'duration_days' => 3,
            'participant_count' => 10,
            'hotel_pricing_mode' => 'flat_stay',
            'hotel_flat_stay_amount' => 1000,
            'hotel_offer_flat_stay_amount' => 1000,
            'hotel_flat_stay_currency_id' => $eur->id,
            'hotel_flat_stay_convert_to_pln' => true,
            'hotel_calculation_source' => \App\Support\HotelCalculationSource::OFFER,
        ]);

        foreach ([1, 2] as $day) {
            EventHotelStay::query()->create([
                'event_id' => $event->id,
                'day' => $day,
                'contractor_id' => $hotel->id,
                'pricing_mode' => 'lines',
            ]);
        }

        $event = $event->fresh(['hotelStays.roomLines', 'hotelStays.offerRoomLines']);
        $plan = app(\App\Services\EventHotelPlanService::class);
        $sync = app(HotelStaySettlementSync::class);

        $this->assertEqualsWithDelta(4000.0, (float) ($plan->offerTotalsByCurrencyForEvent($event)['PLN'] ?? 0), 0.01);
        $this->assertEqualsWithDelta(4000.0, (float) ($plan->negotiatedTotalsByCurrencyForEvent($event)['PLN'] ?? 0), 0.01);

        $cost = $sync->ensureForContractor($event, (int) $hotel->id);
        $this->assertNotNull($cost);
        $this->assertEqualsWithDelta(4000.0, (float) $cost->planned_amount_pln, 0.01);
        $this->assertEqualsWithDelta(4000.0, $sync->offerTotalPlnForContractor($event, (int) $hotel->id), 0.01);

        EventFinanceOverviewService::forgetOverviewCacheForEvent((int) $event->id);
        $row = collect(app(EventFinanceOverviewService::class)->forEvent($event->fresh(), hideZero: false)['rows'])
            ->first(fn (array $candidate): bool => (int) ($candidate['cost_id'] ?? 0) === (int) $cost->id);

        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(4000.0, (float) $row['calculation_pln'], 0.01);
        $this->assertEqualsWithDelta(4000.0, (float) $row['planned_pln'], 0.01);
    }

    public function test_flat_stay_skips_nights_without_hotel(): void
    {
        Role::findOrCreate('admin');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $eur = \App\Models\Currency::query()->firstOrCreate(
            ['symbol' => 'EUR'],
            ['name' => 'Euro', 'code' => 'EUR', 'exchange_rate' => 4.0]
        );
        $eur->forceFill(['exchange_rate' => 4.0, 'code' => 'EUR'])->save();
        \App\Models\Currency::clearPlnIdsCache();

        $hotel = Contractor::create(['name' => 'Hotel Billable', 'status' => 'active']);
        $event = Event::factory()->create([
            'duration_days' => 3,
            'participant_count' => 10,
            'hotel_pricing_mode' => 'flat_stay',
            'hotel_flat_stay_amount' => 1000,
            'hotel_offer_flat_stay_amount' => 1000,
            'hotel_flat_stay_currency_id' => $eur->id,
            'hotel_flat_stay_convert_to_pln' => true,
            'hotel_calculation_source' => \App\Support\HotelCalculationSource::OFFER,
        ]);

        // Noc 1 — dojazd, bez hotelu, 0 zł
        $freeNight = EventHotelStay::query()->create([
            'event_id' => $event->id,
            'day' => 1,
            'contractor_id' => null,
            'pricing_mode' => 'lines',
        ]);

        foreach ([2, 3] as $day) {
            EventHotelStay::query()->create([
                'event_id' => $event->id,
                'day' => $day,
                'contractor_id' => $hotel->id,
                'pricing_mode' => 'lines',
            ]);
        }

        $event = $event->fresh(['hotelStays.roomLines', 'hotelStays.offerRoomLines']);
        $sync = app(HotelStaySettlementSync::class);
        $sync->syncForEvent($event);

        $hotelCost = $sync->findForContractor($event->fresh(), (int) $hotel->id);
        $freeCost = $sync->findForStay($event->fresh(), $freeNight->fresh());

        $this->assertNotNull($hotelCost);
        // Cały flat (1000 EUR × 4 = 4000 PLN) na noce z hotelem — bez udziału nocy 1.
        $this->assertEqualsWithDelta(4000.0, (float) $hotelCost->planned_amount_pln, 0.01);
        $this->assertNull($freeCost, 'Noc bez hotelu nie powinna mieć kosztu flat');
        $this->assertEqualsWithDelta(0.0, $sync->offerTotalPlnForStay($event, $freeNight->fresh()), 0.01);
        $this->assertEqualsWithDelta(4000.0, $sync->offerTotalPlnForContractor($event, (int) $hotel->id), 0.01);
    }

    public function test_overview_group_sums_identical_night_plan_totals(): void
    {
        Role::findOrCreate('admin');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $hotel = Contractor::create(['name' => 'Hotel Sum Nights', 'status' => 'active']);
        $event = Event::factory()->create([
            'duration_days' => 3,
            'participant_count' => 2,
            'hotel_pricing_mode' => 'lines',
        ]);

        foreach ([1, 2, 3] as $day) {
            $stay = EventHotelStay::query()->create([
                'event_id' => $event->id,
                'day' => $day,
                'contractor_id' => $hotel->id,
                'pricing_mode' => 'lines',
            ]);

            EventHotelRoomLine::query()->create([
                'event_hotel_stay_id' => $stay->id,
                'price_layer' => EventHotelRoomLine::LAYER_NEGOTIATED,
                'label' => 'Twin',
                'role' => 'qty',
                'quantity' => 1,
                'people_count' => 2,
                'unit_price' => 100,
                'price_basis' => EventHotelRoomLine::PRICE_BASIS_PER_ROOM,
                'convert_to_pln' => true,
                'order' => 0,
            ]);
        }

        $component = Livewire::actingAs($user)
            ->test(EventHotelStaysFinancePanel::class, [
                'eventId' => (int) $event->id,
                'variant' => 'overview',
            ]);

        $groups = $component->instance()->hotelFinanceGroups();
        $hotelGroup = collect($groups)->first(
            fn (array $group): bool => (int) ($group['contractor_id'] ?? 0) === (int) $hotel->id
        );

        $this->assertNotNull($hotelGroup);
        // 3 × 100 PLN — earlier unique() implode would collapse to a single "100 PLN".
        $this->assertSame('300 PLN', $hotelGroup['finance']['stayTotal'] ?? null);
        $this->assertStringContainsString('300', (string) ($hotelGroup['finance']['planned'] ?? ''));
    }
}
