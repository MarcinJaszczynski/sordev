<?php

namespace Tests\Unit;

use App\Models\EventParticipant;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EventParticipantGenderTest extends TestCase
{
    #[DataProvider('genderInputs')]
    public function test_normalize_gender(string $input, ?string $expected): void
    {
        $this->assertSame($expected, EventParticipant::normalizeGender($input));
    }

    /** @return array<string, array{0: string, 1: ?string}> */
    public static function genderInputs(): array
    {
        return [
            'meska' => ['Męska', EventParticipant::GENDER_MALE],
            'male' => ['male', EventParticipant::GENDER_MALE],
            'm' => ['M', EventParticipant::GENDER_MALE],
            'zenska' => ['Żeńska', EventParticipant::GENDER_FEMALE],
            'female' => ['female', EventParticipant::GENDER_FEMALE],
            'inna' => ['Inna', EventParticipant::GENDER_OTHER],
            'empty' => ['', null],
            'unknown' => ['xyz', null],
        ];
    }

    public function test_gender_label(): void
    {
        $participant = new EventParticipant(['gender' => EventParticipant::GENDER_FEMALE]);

        $this->assertSame('Żeńska', $participant->genderLabel());
        $this->assertNull((new EventParticipant)->genderLabel());
    }
}
