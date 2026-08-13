<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\EventParticipantConsents;
use PHPUnit\Framework\TestCase;

class EventParticipantConsentsTest extends TestCase
{
    public function test_required_consents_and_apply_flags(): void
    {
        $flags = [
            EventParticipantConsents::TERMS => true,
            EventParticipantConsents::INSURANCE => true,
            EventParticipantConsents::RODO => true,
            EventParticipantConsents::IMAGE => false,
            EventParticipantConsents::MEDICAL => false,
        ];

        $consents = EventParticipantConsents::applyFlags($flags, null, '2026-08-06T12:00:00+00:00');

        $this->assertTrue(EventParticipantConsents::hasRequired($consents));
        $this->assertSame(3, EventParticipantConsents::completedCount($consents));
        $this->assertSame('2026-08-06T12:00:00+00:00', $consents[EventParticipantConsents::TERMS]);
        $this->assertNull($consents[EventParticipantConsents::IMAGE]);
    }

    public function test_maps_agreement_flow_data_to_rodo(): void
    {
        $flags = EventParticipantConsents::flagsFromAgreementFlow([
            'terms' => true,
            'insurance' => true,
            'data' => true,
            'communication' => true,
        ]);

        $this->assertTrue($flags[EventParticipantConsents::TERMS]);
        $this->assertTrue($flags[EventParticipantConsents::INSURANCE]);
        $this->assertTrue($flags[EventParticipantConsents::RODO]);
        $this->assertFalse($flags[EventParticipantConsents::IMAGE]);
    }
}
