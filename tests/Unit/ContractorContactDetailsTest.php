<?php

namespace Tests\Unit;

use App\Models\Contractor;
use App\Models\ContractorLocation;
use App\Support\ContractorContactDetails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractorContactDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_meta_uses_location_address_over_billing_address(): void
    {
        $contractor = Contractor::create([
            'name' => 'Sieć Hoteli Test',
            'street' => 'ul. Siedziby',
            'house_number' => '1',
            'postal_code' => '00-001',
            'city' => 'Warszawa',
            'phone' => '111111111',
            'status' => 'active',
            'uses_business_locations' => true,
        ]);

        $location = ContractorLocation::create([
            'contractor_id' => $contractor->id,
            'name' => 'Oddział Zakopane',
            'street' => 'ul. Górska',
            'house_number' => '5',
            'postal_code' => '34-500',
            'city' => 'Zakopane',
            'phone' => '222222222',
            'email' => 'zakopane@hotel.test',
            'is_primary' => true,
            'status' => 'active',
        ]);

        $meta = ContractorContactDetails::operationalMeta($contractor, $location);

        $this->assertStringContainsString('Górska', (string) $meta['address']);
        $this->assertStringContainsString('Zakopane', (string) $meta['address']);
        $this->assertSame('222222222', $meta['phone']);
        $this->assertSame('zakopane@hotel.test', $meta['email']);
        $this->assertSame('Oddział Zakopane', $meta['branch_name']);
    }

    public function test_operational_meta_falls_back_to_contractor_when_no_location(): void
    {
        $contractor = Contractor::create([
            'name' => 'Hotel Solo',
            'street' => 'ul. Jeden',
            'city' => 'Kraków',
            'phone' => '333333333',
            'status' => 'active',
        ]);

        $meta = ContractorContactDetails::operationalMeta($contractor, null);

        $this->assertStringContainsString('Jeden', (string) $meta['address']);
        $this->assertSame('333333333', $meta['phone']);
        $this->assertNull($meta['branch_name']);
    }
}
