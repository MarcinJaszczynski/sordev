<?php

declare(strict_types=1);

namespace App\Services\Documents;

/**
 * Wspólna treść i reguły formatowania oferty DOCX (impreza + WWW).
 *
 * Kontrolery PhpWord zostają cienkie — tu trzymamy teksty biznesowe i reguły cennika.
 */
final class WordOfferContent
{
    public const OFFER_VALIDITY_DAYS = 10;

    public const ORGANIZER_LINES = [
        'Biuro Podróży RAFA',
        'ul. Marii Konopnickiej 6',
        '00-491 Warszawa',
    ];

    public const DEFAULT_ACCOMMODATION = 'Zakwaterowanie w hotelu lub pensjonacie o standardzie ***/**, pokoje maksymalnie 4-osobowe, wszystkie pokoje z łazienkami.';

    public const ACCOMMODATION_ROOM_NOTE = 'pokoje maksymalnie 4-osobowe, wszystkie pokoje z łazienkami.';

    /**
     * Sztywne progi oferty imprezy (kolejność malejąca — najniższa cena na górze).
     * Dwa pierwsze wiersze dzielą tę samą cenę z qty=40.
     *
     * @return list<array{qty: int, from: int, to: int, gratis: int}>
     */
    public function eventOfferTiers(): array
    {
        return [
            ['qty' => 40, 'from' => 46, 'to' => 55, 'gratis' => 4],
            ['qty' => 40, 'from' => 40, 'to' => 45, 'gratis' => 3],
            ['qty' => 35, 'from' => 35, 'to' => 39, 'gratis' => 3],
            ['qty' => 30, 'from' => 30, 'to' => 34, 'gratis' => 3],
            ['qty' => 25, 'from' => 25, 'to' => 29, 'gratis' => 2],
            ['qty' => 20, 'from' => 20, 'to' => 24, 'gratis' => 2],
        ];
    }

    public function offerValidityLabel(): string
    {
        return self::OFFER_VALIDITY_DAYS.' dni od daty przygotowania oferty';
    }

    /**
     * @return list<string>
     */
    public function notes(bool $isForeignTrip): array
    {
        $shared = [
            'program jest ramowy (kolejność zwiedzania może ulec zmianie)',
            'prosimy o zapoznanie się z Ogólnymi Warunkami Uczestnictwa w imprezach organizowanych przez Biuro Podróży RAFA, w szczególności z pkt 6, określającym warunki rezygnacji (dostępne na stronie www.bprafa.pl w zakładce „Dokumenty”)',
            'prosimy o zapoznanie się z Ogólnymi Warunkami Ubezpieczenia (dostępnymi na stronie www.bprafa.pl w zakładce „Dokumenty”)',
            'zachęcamy do wykupienia dodatkowego ubezpieczenia kosztów rezygnacji, które w przypadku np. nagłego zachorowania uniemożliwiającego udział w wyjeździe gwarantuje zwrot 100% ceny wycieczki. Koszt ubezpieczenia wynosi 3,2% ceny wycieczki',
            'zgłoszenia diet specjalnych (np. wegańskiej, wegetariańskiej, bezglutenowej, bezlaktozowej, eliminacyjnej itp.) przyjmowane są najpóźniej na 7 dni przed rozpoczęciem wycieczki. Dla uczestników wymagających diety specjalnej naliczana jest dopłata w wysokości 20 zł za osobę za każdy dzień trwania wycieczki',
        ];

        if ($isForeignTrip) {
            return array_merge(
                ['każdy uczestnik musi posiadać dowód osobisty lub paszport'],
                $shared
            );
        }

        return $shared;
    }

    /**
     * Części wpisu cennika (kwota boldem, reszta zwykłym tekstem w DOCX).
     *
     * @param  list<string>  $extraCurrencyLabels  np. ["+ 50 EUR"]
     * @return array{amount: string, detail: string}
     */
    public function formatPriceLineParts(
        int $pricePln,
        int $from,
        int $to,
        int $gratis,
        array $extraCurrencyLabels = [],
    ): array {
        $range = $from === $to
            ? (string) $from
            : $from.'–'.$to;

        $amount = number_format($pricePln, 0, ',', ' ').' PLN';

        foreach ($extraCurrencyLabels as $extra) {
            $extra = trim($extra);
            if ($extra !== '') {
                $amount .= ' '.$extra;
            }
        }

        return [
            // Trailing spacja — w DOCX wchodzi w bold razem z kwotą.
            'amount' => $amount.' ',
            'detail' => 'za osobę dla grupy '.$range.' uczestników'
                .' + '.$gratis.' '.$this->gratisWord($gratis).' gratis',
        ];
    }

    /**
     * Jednoliniowy wpis cennika, np.:
     * "375 PLN za osobę dla grupy 25–29 uczestników + 2 opiekunów gratis"
     * z walutą obcą: "2 640 PLN + 120 EUR za osobę dla grupy 46–55 uczestników + 4 opiekunów gratis"
     *
     * @param  list<string>  $extraCurrencyLabels  np. ["+ 50 EUR"]
     */
    public function formatPriceLine(
        int $pricePln,
        int $from,
        int $to,
        int $gratis,
        array $extraCurrencyLabels = [],
    ): string {
        $parts = $this->formatPriceLineParts($pricePln, $from, $to, $gratis, $extraCurrencyLabels);

        return $parts['amount'].$parts['detail'];
    }

    public function gratisWord(int $gratis): string
    {
        if ($gratis === 1) {
            return 'opiekuna';
        }

        if ($gratis >= 2 && $gratis <= 4) {
            return 'opiekunów';
        }

        return 'opiekunów';
    }

    /**
     * Domyślna liczba opiekunów gratis dla przedziału (gdy brak wariantu qty).
     */
    public function defaultGratisForRange(int $from, int $to): int
    {
        foreach ($this->eventOfferTiers() as $tier) {
            if ($tier['from'] === $from && $tier['to'] === $to) {
                return $tier['gratis'];
            }
        }

        // 1 opiekun / 15 uczestników (zaokrąglenie w górę od środka przedziału).
        $mid = (int) ceil(($from + $to) / 2);

        return max(1, (int) ceil($mid / 15));
    }

    /**
     * Rozbicie najwyższego progu 40–55 na 46–55 (+4) i 40–45 (+3) z tą samą ceną.
     *
     * @param  list<array{from: int, to: int, price: int, other?: list<string>, gratis?: int}>  $ranges  malejąco
     * @return list<array{from: int, to: int, price: int, other: list<string>, gratis: int}>
     */
    public function splitTopPriceTier(array $ranges): array
    {
        if ($ranges === []) {
            return [];
        }

        $out = [];
        foreach ($ranges as $index => $range) {
            $from = (int) $range['from'];
            $to = (int) $range['to'];
            $price = (int) $range['price'];
            $other = array_values($range['other'] ?? []);
            $gratis = (int) ($range['gratis'] ?? $this->defaultGratisForRange($from, $to));

            // Pierwszy (największy) wiersz w stylu 40–55 → dwa wiersze jak na WWW / QA.
            if ($index === 0 && $from === 40 && $to === 55) {
                $out[] = [
                    'from' => 46,
                    'to' => 55,
                    'price' => $price,
                    'other' => $other,
                    'gratis' => 4,
                ];
                $out[] = [
                    'from' => 40,
                    'to' => 45,
                    'price' => $price,
                    'other' => $other,
                    'gratis' => 3,
                ];

                continue;
            }

            $out[] = [
                'from' => $from,
                'to' => $to,
                'price' => $price,
                'other' => $other,
                'gratis' => $gratis,
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>  $hotelNames
     * @return list<string>  linie do wypunktowania
     */
    public function accommodationLines(array $hotelNames): array
    {
        $names = array_values(array_filter(array_map(
            static fn ($n) => trim((string) $n),
            $hotelNames
        ), static fn (string $n): bool => $n !== ''));

        if ($names === []) {
            return [self::DEFAULT_ACCOMMODATION];
        }

        $lines = [];
        foreach ($names as $name) {
            $lines[] = $name.' — '.self::ACCOMMODATION_ROOM_NOTE;
        }

        return $lines;
    }

    public function shouldShowAccommodation(int $coreDays): bool
    {
        return $coreDays > 1;
    }

    /**
     * Parsowanie HTML „Cena zawiera / nie zawiera” ze szablonu WWW.
     *
     * @return array{includes: list<string>, excludes: list<string>}
     */
    public function parsePriceDescriptionHtml(string $html): array
    {
        $working = $html;
        $working = preg_replace(
            '/<(strong|b)[^>]*>\s*(.*?)\s*<\/\1>/is',
            "\n@@HEAD@@$2@@/HEAD@@\n",
            $working
        ) ?? $working;
        $working = preg_replace('/<li[^>]*>/i', "\n- ", $working) ?? $working;
        $working = preg_replace('/<\/li>/i', "\n", $working) ?? $working;
        $working = preg_replace('/<p[^>]*>/i', "\n", $working) ?? $working;
        $working = str_ireplace(['</p>'], "\n", $working);
        $working = preg_replace('/<br\s*\/?/i', "\n", $working) ?? $working;
        $working = strip_tags($working);
        $working = html_entity_decode($working, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $working = str_replace("\u{00A0}", ' ', $working);

        $includes = [];
        $excludes = [];
        $bucket = null;

        foreach (preg_split('/\r\n|\r|\n/', $working) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || $this->isNoiseListMarker($trimmed)) {
                continue;
            }

            if (preg_match('/^@@HEAD@@(.*?)@@\/HEAD@@$/s', $trimmed, $m)) {
                $heading = $this->plainText($m[1]);
                if (preg_match('/cena\s+nie\s+zawiera/iu', $heading)) {
                    $bucket = 'excludes';
                } elseif (preg_match('/cena\s+zawiera/iu', $heading)) {
                    $bucket = 'includes';
                }

                continue;
            }

            if (preg_match('/^cena\s+nie\s+zawiera\s*:?\s*$/iu', $trimmed)) {
                $bucket = 'excludes';

                continue;
            }
            if (preg_match('/^cena\s+zawiera\s*:?\s*$/iu', $trimmed)) {
                $bucket = 'includes';

                continue;
            }

            if ($bucket === null) {
                continue;
            }

            $content = str_starts_with($trimmed, '-')
                ? $this->plainText(ltrim(substr($trimmed, 1)))
                : $this->plainText($trimmed);

            if ($content === '' || $this->isNoiseListMarker($content)) {
                continue;
            }

            if ($bucket === 'includes') {
                $includes[] = $content;
            } else {
                $excludes[] = $content;
            }
        }

        return [
            'includes' => $includes,
            'excludes' => $excludes,
        ];
    }

    public function plainText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);
        $text = strip_tags($text);
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return $text;
    }

    private function isNoiseListMarker(string $text): bool
    {
        return (bool) preg_match('/^[>\x{2022}\x{25CF}\x{00B7}\*\-–—]+$/u', $text);
    }
}
