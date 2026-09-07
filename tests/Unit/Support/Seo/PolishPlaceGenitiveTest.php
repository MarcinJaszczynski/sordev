<?php

namespace Tests\Unit\Support\Seo;

use App\Support\Seo\PolishPlaceGenitive;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PolishPlaceGenitiveTest extends TestCase
{
    #[DataProvider('places')]
    public function test_genitive_forms(string $nominative, string $expected): void
    {
        $this->assertSame($expected, PolishPlaceGenitive::of($nominative));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function places(): array
    {
        return [
            'torun' => ['Toruń', 'Torunia'],
            'gdansk' => ['Gdańsk', 'Gdańska'],
            'warszawa' => ['Warszawa', 'Warszawy'],
            'krakow' => ['Kraków', 'Krakowa'],
            'compound_left_alone' => ['Biała Podlaska', 'Biała Podlaska'],
            'trim' => ['  Gdańsk  ', 'Gdańska'],
        ];
    }

    public function test_with_preposition_uses_safe_phrase_for_compounds(): void
    {
        $this->assertSame('z Gdańska', PolishPlaceGenitive::withPreposition('z', 'Gdańsk'));
        $this->assertSame('do Torunia', PolishPlaceGenitive::withPreposition('do', 'Toruń'));
        $this->assertSame('z miasta Biała Podlaska', PolishPlaceGenitive::withPreposition('z', 'Biała Podlaska'));
        $this->assertSame('do miejscowości Bielsko-Biała', PolishPlaceGenitive::withPreposition('do', 'Bielsko-Biała'));
    }
}
