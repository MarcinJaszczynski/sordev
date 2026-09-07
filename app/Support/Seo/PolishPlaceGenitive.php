<?php

namespace App\Support\Seo;

/**
 * Lekka odmiana miejscowości do dopełniacza (do X / z Y) pod SEO i llms.txt.
 * Celowo konserwatywna: przy wątpliwości zostawia formę mianownika.
 */
class PolishPlaceGenitive
{
    /** @var array<string, string> */
    private const OVERRIDES = [
        'Warszawa' => 'Warszawy',
        'Kraków' => 'Krakowa',
        'Krakow' => 'Krakowa',
        'Gdańsk' => 'Gdańska',
        'Gdansk' => 'Gdańska',
        'Toruń' => 'Torunia',
        'Torun' => 'Torunia',
        'Poznań' => 'Poznania',
        'Poznan' => 'Poznania',
        'Wrocław' => 'Wrocławia',
        'Wroclaw' => 'Wrocławia',
        'Lublin' => 'Lublina',
        'Katowice' => 'Katowic',
        'Gdynia' => 'Gdyni',
        'Sopot' => 'Sopotu',
        'Łódź' => 'Łodzi',
        'Lodz' => 'Łodzi',
        'Białystok' => 'Białegostoku',
        'Bialystok' => 'Białegostoku',
        'Rzeszów' => 'Rzeszowa',
        'Rzeszow' => 'Rzeszowa',
        'Kielce' => 'Kielc',
        'Olsztyn' => 'Olsztyna',
        'Bydgoszcz' => 'Bydgoszczy',
        'Szczecin' => 'Szczecina',
        'Częstochowa' => 'Częstochowy',
        'Czestochowa' => 'Częstochowy',
        'Radom' => 'Radomia',
        'Płock' => 'Płocka',
        'Plock' => 'Płocka',
        'Bytom' => 'Bytomia',
        'Chełm' => 'Chełma',
        'Chelm' => 'Chełma',
        'Elbląg' => 'Elbląga',
        'Elblag' => 'Elbląga',
        'Kalisz' => 'Kalisza',
        'Konin' => 'Konina',
        'Koszalin' => 'Koszalina',
        'Krosno' => 'Krosna',
        'Legionowo' => 'Legionowa',
        'Legnica' => 'Legnicy',
        'Leszno' => 'Leszna',
        'Paryż' => 'Paryża',
        'PARYŻ' => 'Paryża',
        'Berlin' => 'Berlina',
        'Praga' => 'Pragi',
        'Wiedeń' => 'Wiednia',
        'Rzym' => 'Rzymu',
        'Londyn' => 'Londynu',
    ];

    public static function of(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);

        if ($name === '') {
            return $name;
        }

        if (isset(self::OVERRIDES[$name])) {
            return self::OVERRIDES[$name];
        }

        // Nazwy złożone / techniczne — bez zgadywania.
        if (preg_match('/[\s\/,0-9\-]/u', $name) === 1) {
            return $name;
        }

        if (str_ends_with($name, 'ń')) {
            return mb_substr($name, 0, -1).'nia';
        }

        if (str_ends_with($name, 'sk') || str_ends_with($name, 'ck')) {
            return $name.'a';
        }

        if (str_ends_with($name, 'ów')) {
            return mb_substr($name, 0, -2).'owa';
        }

        if (str_ends_with($name, 'awa')) {
            return mb_substr($name, 0, -1).'y';
        }

        return $name;
    }

    /**
     * Bezpieczna fraza z przyimkiem: „do Torunia” / „z Gdańska”.
     * Dla nazw złożonych bez pewnej odmiany: „do miejscowości X” / „z miasta X”.
     */
    public static function withPreposition(string $preposition, string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
        $preposition = mb_strtolower(trim($preposition));

        if ($name === '') {
            return $preposition;
        }

        $genitive = self::of($name);
        $isCompoundOrOpaque = $genitive === $name && preg_match('/[\s\/,0-9\-]/u', $name) === 1;

        if ($isCompoundOrOpaque) {
            return $preposition === 'z'
                ? 'z miasta '.$name
                : 'do miejscowości '.$name;
        }

        return $preposition.' '.$genitive;
    }
}
