<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\EventStatusChanged;
use App\Models\Event;
use App\Models\EventHotelRoomLine;
use App\Models\EventHotelStay;
use App\Models\EventTemplate;
use App\Models\EventTemplateHotelDay;
use App\Models\HotelRoom;
use App\Models\Place;
use App\Models\User;
use App\Services\EventHotelPlanService;
use App\Services\EventStatusAutomationService;
use App\Support\HotelCalculationSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HotelDualPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_seeds_offer_and_negotiated_from_catalog(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $place = Place::create(['name' => 'Gdańsk']);
        $template = EventTemplate::factory()->create([
            'duration_days' => 2,
            'start_place_id' => $place->id,
        ]);

        $twin = HotelRoom::create([
            'name' => 'Pokój 2-osobowy',
            'people_count' => 2,
            'capacity' => 2,
            'price' => 400,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);

        EventTemplateHotelDay::create([
            'event_template_id' => $template->id,
            'day' => 1,
            'hotel_room_ids_qty' => [$twin->id],
            'hotel_room_ids_gratis' => [],
            'hotel_room_ids_staff' => [],
            'hotel_room_ids_driver' => [],
        ]);

        $event = Event::createFromTemplate($template, [
            'name' => 'Wycieczka dual pricing',
            'client_name' => 'Klient',
            'start_date' => now()->format('Y-m-d'),
            'participant_count' => 2,
        ]);

        $line = $event->hotelStays()->first()?->roomLines()->first();
        $offerLine = $event->hotelStays()->first()?->offerRoomLines()->first();
        $this->assertNotNull($line);
        $this->assertNotNull($offerLine);
        $this->assertEqualsWithDelta(400.0, (float) $line->unit_price, 0.01);
        $this->assertEqualsWithDelta(400.0, (float) $offerLine->unit_price, 0.01);
        $this->assertSame(EventHotelRoomLine::LAYER_NEGOTIATED, $line->price_layer);
        $this->assertSame(EventHotelRoomLine::LAYER_OFFER, $offerLine->price_layer);
        $this->assertSame(
            HotelCalculationSource::OFFER,
            HotelCalculationSource::normalize($event->fresh()->hotel_calculation_source)
        );
    }

    public function test_negotiated_price_does_not_change_offer_calculation_by_default(): void
    {
        $event = $this->makeEventWithTwinLine(offer: 400, negotiated: 400);

        $line = $event->hotelStays->first()->roomLines->first();
        $line->update(['unit_price' => 360]);

        $service = app(EventHotelPlanService::class);
        $event = $event->fresh(['hotelStays.roomLines', 'hotelStays.offerRoomLines']);

        $offerPln = (float) ($service->offerTotalsByCurrencyForEvent($event)['PLN'] ?? 0);
        $negotiatedPln = (float) ($service->negotiatedTotalsByCurrencyForEvent($event)['PLN'] ?? 0);
        $calcPln = (float) ($service->totalsByCurrencyForEvent($event)['PLN'] ?? 0);

        $this->assertEqualsWithDelta(400.0, $offerPln, 0.01);
        $this->assertEqualsWithDelta(360.0, $negotiatedPln, 0.01);
        $this->assertEqualsWithDelta(400.0, $calcPln, 0.01);

        $service->setCalculationSource($event, HotelCalculationSource::NEGOTIATED);
        $calcAfter = (float) ($service->totalsByCurrencyForEvent($event->fresh())['PLN'] ?? 0);
        $this->assertEqualsWithDelta(360.0, $calcAfter, 0.01);
    }

    public function test_apply_negotiated_room_rates_updates_only_negotiated_layer(): void
    {
        $event = $this->makeEventWithTwinLine(offer: 400, negotiated: 400);
        $service = app(EventHotelPlanService::class);

        $service->applyNegotiatedRoomRates($event, [2 => 360], allStays: true);

        $negotiated = EventHotelRoomLine::query()
            ->whereHas('stay', fn ($q) => $q->where('event_id', $event->id))
            ->negotiated()
            ->first();
        $offer = EventHotelRoomLine::query()
            ->whereHas('stay', fn ($q) => $q->where('event_id', $event->id))
            ->offer()
            ->first();

        $this->assertEqualsWithDelta(360.0, (float) $negotiated->unit_price, 0.01);
        $this->assertEqualsWithDelta(400.0, (float) $offer->unit_price, 0.01);
    }

    public function test_status_confirmed_switches_calculation_source_to_negotiated(): void
    {
        $event = $this->makeEventWithTwinLine(offer: 400, negotiated: 360);
        $this->assertSame(HotelCalculationSource::OFFER, HotelCalculationSource::normalize($event->hotel_calculation_source));

        app(EventStatusAutomationService::class)->handle(new EventStatusChanged(
            event: $event->fresh(),
            previousStatus: Event::STATUS_OFFER,
            newStatus: Event::STATUS_CONFIRMED,
        ));

        $fresh = $event->fresh();
        $this->assertSame(HotelCalculationSource::NEGOTIATED, HotelCalculationSource::normalize($fresh->hotel_calculation_source));

        $calc = (float) (app(EventHotelPlanService::class)->totalsByCurrencyForEvent($fresh)['PLN'] ?? 0);
        $this->assertEqualsWithDelta(360.0, $calc, 0.01);
    }

    public function test_independent_structures_and_variant_uses_negotiated_room_types(): void
    {
        $twin = HotelRoom::create([
            'name' => 'Twin 2',
            'people_count' => 2,
            'capacity' => 2,
            'price' => 400,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);
        $single = HotelRoom::create([
            'name' => 'Single 1',
            'people_count' => 1,
            'capacity' => 1,
            'price' => 300,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);

        $user = User::factory()->create();
        $event = Event::factory()->create([
            'participant_count' => 2,
            'duration_days' => 2,
            'status' => Event::STATUS_OFFER,
            'created_by' => $user->id,
            'hotel_calculation_source' => HotelCalculationSource::NEGOTIATED,
            'hotel_pricing_mode' => 'lines',
        ]);

        $stay = EventHotelStay::query()->create([
            'event_id' => $event->id,
            'day' => 1,
            'pricing_mode' => 'lines',
        ]);

        // S: 1× twin @ 400
        EventHotelRoomLine::query()->create([
            'event_hotel_stay_id' => $stay->id,
            'price_layer' => EventHotelRoomLine::LAYER_OFFER,
            'hotel_room_id' => $twin->id,
            'role' => 'qty',
            'quantity' => 1,
            'people_count' => 2,
            'unit_price' => 400,
            'price_basis' => EventHotelRoomLine::PRICE_BASIS_PER_ROOM,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        // P: 2× single @ 280 (zupełnie inna struktura)
        EventHotelRoomLine::query()->create([
            'event_hotel_stay_id' => $stay->id,
            'price_layer' => EventHotelRoomLine::LAYER_NEGOTIATED,
            'hotel_room_id' => $single->id,
            'role' => 'qty',
            'quantity' => 2,
            'people_count' => 1,
            'unit_price' => 280,
            'price_basis' => EventHotelRoomLine::PRICE_BASIS_PER_ROOM,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        $service = app(EventHotelPlanService::class);
        $event = $event->fresh(['hotelStays.roomLines', 'hotelStays.offerRoomLines']);

        $this->assertEqualsWithDelta(400.0, (float) ($service->offerTotalsByCurrencyForEvent($event)['PLN'] ?? 0), 0.01);
        $this->assertEqualsWithDelta(560.0, (float) ($service->negotiatedTotalsByCurrencyForEvent($event)['PLN'] ?? 0), 0.01);

        // Wariant qty=4 przy źródle P: realokacja z typów uzgodnionych (single @ 280).
        $variant = $service->totalsByCurrencyForVariant($event, [
            'qty' => 4,
            'gratis' => 0,
            'staff' => 0,
            'driver' => 0,
        ]);
        $this->assertEqualsWithDelta(1120.0, (float) ($variant['PLN'] ?? 0), 0.01);
    }

    private function makeEventWithTwinLine(float $offer, float $negotiated): Event
    {
        $twin = HotelRoom::create([
            'name' => 'Pokój 2-osobowy',
            'people_count' => 2,
            'capacity' => 2,
            'price' => $offer,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);

        $user = User::factory()->create();
        $event = Event::factory()->create([
            'participant_count' => 2,
            'duration_days' => 2,
            'status' => Event::STATUS_OFFER,
            'created_by' => $user->id,
            'hotel_calculation_source' => HotelCalculationSource::OFFER,
        ]);

        $stay = EventHotelStay::query()->create([
            'event_id' => $event->id,
            'day' => 1,
            'pricing_mode' => 'lines',
        ]);

        EventHotelRoomLine::query()->create([
            'event_hotel_stay_id' => $stay->id,
            'price_layer' => EventHotelRoomLine::LAYER_OFFER,
            'hotel_room_id' => $twin->id,
            'role' => 'qty',
            'quantity' => 1,
            'people_count' => 2,
            'unit_price' => $offer,
            'offer_unit_price' => $offer,
            'price_basis' => EventHotelRoomLine::PRICE_BASIS_PER_ROOM,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        EventHotelRoomLine::query()->create([
            'event_hotel_stay_id' => $stay->id,
            'price_layer' => EventHotelRoomLine::LAYER_NEGOTIATED,
            'hotel_room_id' => $twin->id,
            'role' => 'qty',
            'quantity' => 1,
            'people_count' => 2,
            'unit_price' => $negotiated,
            'offer_unit_price' => $offer,
            'price_basis' => EventHotelRoomLine::PRICE_BASIS_PER_ROOM,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        return $event->fresh(['hotelStays.roomLines', 'hotelStays.offerRoomLines']);
    }
}
