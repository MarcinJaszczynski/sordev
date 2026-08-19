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

    public function test_full_name_search_finds_driver_by_name_tokens(): void
    {
        $driver = $this->createContractor('Michał Chruściel', 'kierowca');
        $this->createContractor('Tomek Chruściel', 'kierowca');

        $results = $this->lookup->searchOptions(
            search: 'Michał Chruściel',
            typeNames: ['kierowca'],
        );

        $this->assertArrayHasKey($driver->id, $results);
        $this->assertCount(1, $results);
    }

    public function test_search_matches_firstname_and_surname_when_company_name_differs(): void
    {
        $driver = Contractor::create([
            'name' => 'Kol Travel',
            'firstname' => 'Henryk',
            'surname' => 'Chruściel',
            'status' => 'active',
        ]);
        $type = ContractorType::query()->firstOrCreate(['name' => 'kierowca']);
        $driver->types()->sync([$type->id]);

        $bySurname = $this->lookup->searchOptions(
            search: 'Chruściel',
            typeNames: ['kierowca'],
        );
        $byFullPerson = $this->lookup->searchOptions(
            search: 'Henryk Chruściel',
            typeNames: ['kierowca'],
        );

        $this->assertArrayHasKey($driver->id, $bySurname);
        $this->assertArrayHasKey($driver->id, $byFullPerson);
    }

    public function test_search_by_firstname_finds_driver_when_name_is_surname_only(): void
    {
        $driver = Contractor::create([
            'name' => 'Chruściel',
            'firstname' => 'Michał',
            'surname' => 'Chruściel',
            'status' => 'active',
        ]);
        $type = ContractorType::query()->firstOrCreate(['name' => 'kierowca']);
        $driver->types()->sync([$type->id]);

        $results = $this->lookup->searchOptions(
            search: 'Michał',
            typeNames: ['kierowca'],
        );

        $this->assertArrayHasKey($driver->id, $results);
        $this->assertStringContainsString('Michał', $results[$driver->id]);
    }

    public function test_multi_token_search_requires_all_tokens(): void
    {
        $match = $this->createContractor('Michał Chruściel', 'kierowca');
        $this->createContractor('Michał Kowalski', 'kierowca');

        $results = $this->lookup->searchOptions(
            search: 'Michał Chruściel',
            typeNames: ['kierowca'],
        );

        $this->assertArrayHasKey($match->id, $results);
        $this->assertCount(1, $results);
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
