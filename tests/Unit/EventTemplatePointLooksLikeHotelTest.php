<?php

namespace Tests\Unit;

use App\Models\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EventTemplatePointLooksLikeHotelTest extends TestCase
{
    #[DataProvider('hotelNameProvider')]
    public function test_template_point_looks_like_hotel(string $name, bool $expected): void
    {
        $this->assertSame(
            $expected,
            Event::templatePointLooksLikeHotel((object) ['name' => $name]),
            "name={$name}"
        );
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function hotelNameProvider(): array
    {
        return [
            'hotel' => ['Hotel', true],
            'nocleg' => ['Nocleg', true],
            'zakwaterowanie' => ['Zakwaterowanie', true],
            'przejazd do hotelu' => ['Przejazd do hotelu', false],
            'dojazd do hotelu' => ['Dojazd do hotelu', false],
            'transfer hotelowy' => ['Transfer hotelowy', false],
            'powrot do hotelu' => ['Powrót do hotelu', false],
            'empty' => ['', false],
        ];
    }
}
