<?php

namespace Tests\Unit\Support;

use App\Models\Contact;
use App\Models\Contractor;
use App\Support\ContractorContactDetails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractorContactDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_formats_contractor_address_and_contact_fallback(): void
    {
        $contractor = Contractor::create([
            'name' => 'Hotel Górski',
            'phone' => '111222333',
            'email' => 'hotel@example.com',
            'street' => 'Długa',
            'house_number' => '5',
            'postal_code' => '00-001',
            'city' => 'Warszawa',
            'status' => 'active',
        ]);

        $contact = Contact::create([
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
            'phone' => '999888777',
            'email' => 'anna@example.com',
        ]);

        $meta = ContractorContactDetails::contractorMeta($contractor, $contact);

        $this->assertSame('Długa 5, 00-001 Warszawa', $meta['address']);
        $this->assertSame('999888777', $meta['phone']);
        $this->assertSame('anna@example.com', $meta['email']);
    }

    public function test_contact_label_includes_phone_and_email(): void
    {
        $contact = Contact::create([
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'phone' => '500600700',
            'email' => 'jan@example.com',
        ]);

        $label = app(\App\Services\EventOrderingPartyService::class)->formatContactLabel($contact);

        $this->assertStringContainsString('Jan Kowalski', $label);
        $this->assertStringContainsString('500600700', $label);
        $this->assertStringContainsString('jan@example.com', $label);
    }
}
