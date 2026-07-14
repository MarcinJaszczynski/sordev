<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorLocation;
use App\Models\ContractorType;
use App\Models\Event;
use App\Models\EventHotelStay;
use App\Services\EventHotelPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventHotelPlanLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_hotel_stay_saves_contractor_location(): void
    {
        [$contractor, $location] = $this->createHotelWithLocations();

        $event = Event::factory()->create([
            'status' => Event::STATUS_CONFIRMED,
            'duration_days' => 2,
        ]);

        $stay = EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 1,
            'contractor_id' => $contractor->id,
            'contractor_location_id' => $location->id,
        ]);

        $stay->refresh();

        $this->assertSame($contractor->id, $stay->contractor_id);
        $this->assertSame($location->id, $stay->contractor_location_id);
    }

    public function test_pilot_payload_uses_operational_hotel_address(): void
    {
        [$contractor, $location] = $this->createHotelWithLocations();

        $event = Event::factory()->create([
            'status' => Event::STATUS_CONFIRMED,
            'duration_days' => 2,
        ]);

        EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 1,
            'contractor_id' => $contractor->id,
            'contractor_location_id' => $location->id,
        ]);

        $event->load(['hotelStays.contractor', 'hotelStays.contractorLocation']);

        $plan = app(EventHotelPlanService::class)->buildHotelPlanForPdf($event);
        $night = $plan->first();

        $this->assertNotNull($night);
        $this->assertSame('Hotel Zakopane', $night['hotel_branch']);
        $this->assertStringContainsString('Górska', (string) $night['hotel_address']);
        $this->assertStringContainsString('Zakopane', (string) $night['hotel_address']);
        $this->assertSame('999888777', $night['hotel_phone']);
    }

    /**
     * @return array{0: Contractor, 1: ContractorLocation}
     */
    private function createHotelWithLocations(): array
    {
        $contractor = Contractor::create([
            'name' => 'Sieć Hoteli',
            'street' => 'ul. Centralna',
            'city' => 'Warszawa',
            'phone' => '111222333',
            'status' => 'active',
            'uses_business_locations' => true,
        ]);

        $type = ContractorType::query()->firstOrCreate(['name' => 'hotel']);
        $contractor->types()->sync([$type->id]);

        $location = ContractorLocation::create([
            'contractor_id' => $contractor->id,
            'name' => 'Hotel Zakopane',
            'street' => 'ul. Górska',
            'house_number' => '10',
            'postal_code' => '34-500',
            'city' => 'Zakopane',
            'phone' => '999888777',
            'status' => 'active',
            'is_primary' => true,
        ]);

        return [$contractor, $location];
    }
}
