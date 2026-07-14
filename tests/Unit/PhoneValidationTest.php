<?php

namespace Tests\Unit;

use App\Support\PhoneValidation;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PhoneValidationTest extends TestCase
{
    /**
     * @dataProvider validInternationalPhonesProvider
     */
    public function test_accepts_international_formats(string $phone): void
    {
        $validator = Validator::make(
            ['phone' => $phone],
            ['phone' => PhoneValidation::optionalRules()],
        );

        $this->assertTrue($validator->passes(), "Expected valid: {$phone}");
    }

    /** @return array<string, array{string}> */
    public static function validInternationalPhonesProvider(): array
    {
        return [
            'uk with spaces' => ['+44 3423423423423'],
            'pl with spaces' => ['+48 606 102 243'],
            'uk with parens' => ['+44 (0) 7700 900123'],
            'local digits' => ['606102243'],
            'us dashed' => ['+1-555-123-4567'],
        ];
    }

    public function test_rejects_invalid_characters(): void
    {
        $validator = Validator::make(
            ['phone' => 'abc-not-a-phone'],
            ['phone' => PhoneValidation::optionalRules()],
        );

        $this->assertTrue($validator->fails());
    }

    public function test_allows_empty_value(): void
    {
        $validator = Validator::make(
            ['phone' => null],
            ['phone' => PhoneValidation::optionalRules()],
        );

        $this->assertTrue($validator->passes());
    }
}
