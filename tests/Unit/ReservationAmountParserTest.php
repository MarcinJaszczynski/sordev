<?php

namespace Tests\Unit;

use App\Support\ReservationAmountParser;
use PHPUnit\Framework\TestCase;

class ReservationAmountParserTest extends TestCase
{
    /** @dataProvider perPersonProvider */
    public function test_resolves_per_person_notation(mixed $input, int $participants, float $expected): void
    {
        $this->assertSame($expected, ReservationAmountParser::resolve($input, $participants));
    }

    public static function perPersonProvider(): array
    {
        return [
            '18/os × 30' => ['18/os', 30, 540.0],
            '18 / os × 5' => ['18 / os', 5, 90.0],
            'flat amount' => ['180', 30, 180.0],
            'numeric input' => [18, 30, 18.0],
        ];
    }
}
