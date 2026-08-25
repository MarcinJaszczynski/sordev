<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventHotelStay;
use App\Models\EventQty;
use App\Models\Place;
use App\Models\Vehicle;
use App\Services\EventTransportCostCalculator;
use App\Services\HotelStaySettlementSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresje pod uwagi użytkowników z 23.08.2026.
 */
class FeedbackFixes20260824Test extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_capacity_label_shows_passengers_plus_crew(): void
    {
        $vehicle = Vehicle::factory()->create([
            'capacity' => 49,
            'crew_seats' => 2,
            'brand' => 'Setra',
            'model' => 'S 516',
            'registration_number' => 'WW 1111A',
        ]);

        $this->assertSame('49+2', $vehicle->capacityLabel());
        $this->assertStringContainsString('49+2', $vehicle->displayLabel());
    }

    public function test_manual_transport_keeps_bus_calculation_separate(): void
    {
        $bus = Bus::factory()->create([
            'capacity' => 49,
            'package_price_per_day' => 1000,
            'package_km_per_day' => 5000,
            'extra_km_price' => 10,
            'currency' => 'PLN',
        ]);

        $event = Event::factory()->create([
            'bus_id' => $bus->id,
            'transfer_km' => 0,
            'program_km' => 0,
            'duration_days' => 2,
            'use_manual_transport_cost' => true,
            'manual_transport_cost' => 9999,
        ]);
        $event->setRelation('bus', $bus);

        $calculator = new EventTransportCostCalculator($event);

        $this->assertSame(9999.0, $calculator->effectiveTransportCost());
        $this->assertSame(2000.0, $calculator->busCalculatedTransportCost());
    }

    public function test_flat_stay_per_person_creates_hotel_settlement_cost(): void
    {
        $hotel = Contractor::create(['name' => 'Hotel Flat Test', 'status' => 'active']);

        $event = Event::factory()->create([
            'duration_days' => 3,
            'participant_count' => 10,
            'hotel_pricing_mode' => 'flat_stay_per_person',
            'hotel_flat_stay_amount' => 200,
        ]);

        EventQty::create([
            'event_id' => $event->id,
            'qty' => 10,
            'gratis' => 0,
            'staff' => 0,
            'driver' => 0,
        ]);

        EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 1,
            'contractor_id' => $hotel->id,
            'pricing_mode' => 'lines',
        ]);
        EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 2,
            'contractor_id' => $hotel->id,
            'pricing_mode' => 'lines',
        ]);

        app(HotelStaySettlementSync::class)->syncForEvent($event->fresh(['hotelStays.roomLines']));

        $cost = $event->fresh()->activeSettlement?->costs()
            ->where('source_type', HotelStaySettlementSync::SOURCE_HOTEL)
            ->where('source_id', $hotel->id)
            ->first();

        $this->assertNotNull($cost, 'Przy flat per person musi powstać koszt rozliczeniowy hotelu');
        $this->assertSame(2000.0, (float) ($cost->planned_amount_pln ?? $cost->planned_amount));
    }

    public function test_workflow_wyjazd_combines_catalog_place_and_pickup_details(): void
    {
        $place = Place::factory()->create(['name' => 'Warszawa']);
        $event = Event::factory()->create([
            'start_place_id' => $place->id,
            'pickup_place_details' => '<p>Parking pod Halą Torwar, brama B</p>',
            'name' => 'Test wyjazd',
        ]);

        $page = new class
        {
            use \App\Filament\Resources\EventResource\Concerns\HasEventWorkflowContext;

            public $record;
        };
        $page->record = $event->fresh(['startPlace']);

        $context = $page->getWorkflowContext();

        $this->assertNotNull($context);
        $this->assertStringContainsString('Warszawa', (string) $context['start_place']);
        $this->assertStringContainsString('Parking pod Halą Torwar', (string) $context['start_place']);
        $this->assertStringContainsString('→', (string) $context['start_place']);
    }
}
