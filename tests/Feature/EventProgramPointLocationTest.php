<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorLocation;
use App\Models\ContractorType;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Services\ContractorLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventProgramPointLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_point_stores_contractor_location(): void
    {
        [$contractor, $location] = $this->createContractorWithLocation();

        $event = Event::factory()->create(['status' => Event::STATUS_CONFIRMED]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Zwiedzanie muzeum',
            'day' => 1,
            'order' => 1,
            'contractor_id' => $contractor->id,
            'contractor_location_id' => $location->id,
            'include_in_program' => true,
            'include_in_calculation' => false,
            'active' => true,
        ]);

        $point->refresh();

        $this->assertSame($contractor->id, $point->contractor_id);
        $this->assertSame($location->id, $point->contractor_location_id);
    }

    public function test_changing_contractor_clears_location(): void
    {
        [$contractor, $location] = $this->createContractorWithLocation();
        $other = Contractor::create(['name' => 'Inny wykonawca', 'status' => 'active']);

        $event = Event::factory()->create(['status' => Event::STATUS_CONFIRMED]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Restauracja',
            'day' => 1,
            'order' => 1,
            'contractor_id' => $contractor->id,
            'contractor_location_id' => $location->id,
            'include_in_program' => true,
            'include_in_calculation' => false,
            'active' => true,
        ]);

        $point->update(['contractor_id' => $other->id]);
        $point->refresh();

        $this->assertSame($other->id, $point->contractor_id);
        $this->assertNull($point->contractor_location_id);
    }

    public function test_location_must_belong_to_contractor(): void
    {
        [$contractor, $location] = $this->createContractorWithLocation();
        $other = Contractor::create(['name' => 'Inna firma', 'status' => 'active', 'uses_business_locations' => true]);

        $service = app(ContractorLocationService::class);

        $this->assertTrue($service->validateLocationBelongsToContractor($contractor->id, $location->id));
        $this->assertFalse($service->validateLocationBelongsToContractor($other->id, $location->id));
    }

    /**
     * @return array{0: Contractor, 1: ContractorLocation}
     */
    private function createContractorWithLocation(): array
    {
        $contractor = Contractor::create([
            'name' => 'Restauracja Sieć',
            'city' => 'Warszawa',
            'status' => 'active',
            'uses_business_locations' => true,
        ]);

        $type = ContractorType::query()->firstOrCreate(['name' => 'restauracja']);
        $contractor->types()->sync([$type->id]);

        $location = ContractorLocation::create([
            'contractor_id' => $contractor->id,
            'name' => 'Centrum',
            'street' => 'ul. Długa',
            'city' => 'Kraków',
            'phone' => '123456789',
            'status' => 'active',
            'is_primary' => true,
        ]);

        return [$contractor, $location];
    }
}
