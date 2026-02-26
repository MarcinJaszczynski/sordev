<?php

namespace Tests\Unit;

use App\Services\PriceRoundingService;
use PHPUnit\Framework\TestCase;

class PriceRoundingServiceTest extends TestCase
{
    /** @dataProvider plnProvider */
    public function testPlnRounding($input, $expected)
    {
        $this->assertSame($expected, PriceRoundingService::roundPerPerson($input, 'PLN'));
    }

    public static function plnProvider(): array
    {
        return [
            [0, 0.0],
            [1, 5.0],
            [4.99, 5.0],
            [5.0, 5.0],
            [5.01, 10.0],
            [9.99, 10.0],
            [10.0, 10.0],
            [12.49, 15.0],
            [15.01, 20.0],
            [99.0, 100.0],
            [101.0, 105.0],
        ];
    }

    /** @dataProvider foreignProvider */
    public function testForeignRounding($input, $expected)
    {
        $this->assertSame($expected, PriceRoundingService::roundPerPerson($input, 'EUR'));
    }

    public static function foreignProvider(): array
    {
        return [
            [0, 0.0],
            [1, 10.0],
            [9.99, 10.0],
            [10.0, 10.0],
            [10.01, 20.0],
            [19.99, 20.0],
            [20.0, 20.0],
            [21.0, 30.0],
            [99.0, 100.0],
            [101.0, 110.0],
        ];
    }
}
