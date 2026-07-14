<?php

namespace Tests\Unit;

use App\Services\EventOrderingPartyService;
use Tests\TestCase;

class EventOrderingPartyValidationTest extends TestCase
{
    public function test_validate_for_event_creation_requires_party_and_contact(): void
    {
        $service = app(EventOrderingPartyService::class);

        $errors = $service->validateForEventCreation(null, null, null, null);

        $this->assertArrayHasKey('ordering_parties', $errors);
        $this->assertArrayHasKey('client_name', $errors);
        $this->assertArrayHasKey('client_contact', $errors);
    }

    public function test_validate_for_event_creation_accepts_party_with_phone(): void
    {
        $service = app(EventOrderingPartyService::class);

        $errors = $service->validateForEventCreation(
            [['contractor_id' => 1, 'contact_id' => null, 'department_label' => null]],
            'Jan Kowalski · Firma',
            '500600700',
            null,
        );

        $this->assertSame([], $errors);
    }
}
