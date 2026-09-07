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

    public function test_normalize_strips_separators(): void
    {
        $this->assertSame('123456789', PhoneValidation::normalize('123 456 789'));
        $this->assertSame('48606102243', PhoneValidation::normalize('+48 606-102-243'));
        $this->assertSame('123456789', PhoneValidation::normalize("123\u{00A0}456\u{00A0}789"));
        $this->assertNull(PhoneValidation::normalize('   '));
        $this->assertNull(PhoneValidation::normalize(null));
    }

    public function test_looks_like_phone_requires_phone_only_characters(): void
    {
        $this->assertTrue(PhoneValidation::looksLikePhone('123 456 789'));
        $this->assertTrue(PhoneValidation::looksLikePhone('123456789'));
        $this->assertTrue(PhoneValidation::looksLikePhone('+48 606 102 243'));
        $this->assertFalse(PhoneValidation::looksLikePhone('Kowalski 603846062'));
        $this->assertFalse(PhoneValidation::looksLikePhone('12345'));
        $this->assertFalse(PhoneValidation::looksLikePhone(''));
    }

    public function test_digits_sql_nests_replace_calls(): void
    {
        $sql = PhoneValidation::digitsSql('phone');

        $this->assertStringContainsString("REPLACE(phone, ' ', '')", $sql);
        $this->assertStringStartsWith('REPLACE(', $sql);
    }
}
