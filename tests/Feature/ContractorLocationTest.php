<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorLocation;
use App\Models\ContractorType;
use App\Services\ContractorLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractorLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_contractor_can_have_multiple_active_locations(): void
    {
        $contractor = $this->createHotelChain();

        $zakopane = ContractorLocation::create([
            'contractor_id' => $contractor->id,
            'name' => 'Zakopane',
            'city' => 'Zakopane',
            'status' => 'active',
            'is_primary' => true,
        ]);

        $sopot = ContractorLocation::create([
            'contractor_id' => $contractor->id,
            'name' => 'Sopot',
            'city' => 'Sopot',
            'status' => 'active',
        ]);

        $this->assertCount(2, $contractor->fresh()->activeLocations);
        $this->assertSame($zakopane->id, $contractor->fresh()->defaultLocation()?->id);
    }

    public function test_location_service_returns_options_only_for_contractor(): void
    {
        $chain = $this->createHotelChain();
        $other = Contractor::create(['name' => 'Inny hotel', 'status' => 'active']);

        $location = ContractorLocation::create([
            'contractor_id' => $chain->id,
            'name' => 'Centrum',
            'city' => 'Warszawa',
            'status' => 'active',
        ]);

        ContractorLocation::create([
            'contractor_id' => $other->id,
            'name' => 'Obcy',
            'city' => 'Gdańsk',
            'status' => 'active',
        ]);

        $options = app(ContractorLocationService::class)->optionsForContractor($chain->id);

        $this->assertArrayHasKey($location->id, $options);
        $this->assertCount(1, $options);
    }

    public function test_contractor_requires_location_selection_when_flag_enabled(): void
    {
        $contractor = $this->createHotelChain();

        ContractorLocation::create([
            'contractor_id' => $contractor->id,
            'name' => 'Centrum',
            'city' => 'Warszawa',
            'status' => 'active',
        ]);

        $service = app(ContractorLocationService::class);

        $this->assertTrue($service->contractorRequiresLocationSelection($contractor->id));
        $this->assertFalse($service->contractorRequiresLocationSelection(
            Contractor::create(['name' => 'Bez oddziałów', 'status' => 'active'])->id
        ));
    }

    private function createHotelChain(): Contractor
    {
        $contractor = Contractor::create([
            'name' => 'Hotel Chain Sp. z o.o.',
            'street' => 'ul. Centralna 1',
            'city' => 'Warszawa',
            'status' => 'active',
            'uses_business_locations' => true,
        ]);

        $type = ContractorType::query()->firstOrCreate(['name' => 'hotel']);
        $contractor->types()->sync([$type->id]);

        return $contractor;
    }
}
