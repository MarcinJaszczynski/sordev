<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorType;
use App\Services\ContractorLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractorLookupServiceTest extends TestCase
{
    use RefreshDatabase;

    private ContractorLookupService $lookup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lookup = app(ContractorLookupService::class);
    }

    public function test_transport_search_excludes_hotel_contractors_by_default(): void
    {
        $carrier = $this->createContractor('Autokar Trans', 'przewoźnik');
        $this->createContractor('Hotel Górski', 'hotel');

        $results = $this->lookup->searchOptions(
            search: '',
            typeNames: ContractorType::transportTypeNames(),
        );

        $this->assertArrayHasKey($carrier->id, $results);
        $this->assertCount(1, $results);
    }

    public function test_transport_search_includes_carrier_and_driver_types(): void
    {
        $carrier = $this->createContractor('Firma Bus', 'przewoźnik');
        $driver = $this->createContractor('Jan Kierowca', 'kierowca');

        $results = $this->lookup->searchOptions(
            search: '',
            typeNames: ContractorType::transportTypeNames(),
        );

        $this->assertArrayHasKey($carrier->id, $results);
        $this->assertArrayHasKey($driver->id, $results);
    }

    public function test_search_all_returns_contractors_outside_filtered_types(): void
    {
        $hotel = $this->createContractor('Hotel Alpin', 'hotel');

        $filtered = $this->lookup->searchOptions(
            search: 'Alpin',
            typeNames: ContractorType::transportTypeNames(),
        );

        $this->assertSame([], $filtered);

        $all = $this->lookup->searchOptions(
            search: 'Alpin',
            typeNames: ContractorType::transportTypeNames(),
            searchAll: true,
        );

        $this->assertArrayHasKey($hotel->id, $all);
    }

    public function test_include_id_returns_selected_contractor_even_with_wrong_type(): void
    {
        $misassigned = $this->createContractor('Zły typ hotelu', 'hotel');

        $results = $this->lookup->searchOptions(
            search: '',
            typeNames: ContractorType::transportTypeNames(),
            includeId: $misassigned->id,
        );

        $this->assertArrayHasKey($misassigned->id, $results);
    }

    public function test_hotel_search_returns_only_hotel_type_by_default(): void
    {
        $hotel = $this->createContractor('Hotel Park', 'hotel');
        $this->createContractor('Przewoźnik XYZ', 'przewoźnik');

        $results = $this->lookup->searchOptions(
            search: 'Hotel',
            typeNames: ContractorType::hotelTypeNames(),
        );

        $this->assertArrayHasKey($hotel->id, $results);
        $this->assertCount(1, $results);
    }

    public function test_restrict_to_ids_limits_hotel_options_to_event_plan(): void
    {
        $eventHotel = $this->createContractor('Hotel imprezy', 'hotel');
        $otherHotel = $this->createContractor('Hotel spoza planu', 'hotel');

        $results = $this->lookup->searchOptions(
            typeNames: ContractorType::hotelTypeNames(),
            restrictToIds: [$eventHotel->id],
        );

        $this->assertArrayHasKey($eventHotel->id, $results);
        $this->assertArrayNotHasKey($otherHotel->id, $results);
    }

    private function createContractor(string $name, string $typeName): Contractor
    {
        $contractor = Contractor::create([
            'name' => $name,
            'status' => 'active',
        ]);

        $type = ContractorType::query()->firstOrCreate(['name' => $typeName]);
        $contractor->types()->sync([$type->id]);

        return $contractor;
    }
}
