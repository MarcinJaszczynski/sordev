<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Documents\WordOfferContent;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WordOfferContentTest extends TestCase
{
    private WordOfferContent $content;

    protected function setUp(): void
    {
        parent::setUp();
        $this->content = new WordOfferContent;
    }

    #[Test]
    public function formats_price_line_with_range_and_gratis(): void
    {
        $line = $this->content->formatPriceLine(375, 25, 29, 2);

        $this->assertSame(
            '375 PLN za osobę dla grupy 25–29 uczestników + 2 opiekunów gratis',
            $line
        );
    }

    #[Test]
    public function formats_price_line_with_euro_extra(): void
    {
        $line = $this->content->formatPriceLine(2640, 46, 55, 4, ['+ 120 EUR']);

        $this->assertSame(
            '2 640 PLN + 120 EUR za osobę dla grupy 46–55 uczestników + 4 opiekunów gratis',
            $line
        );
    }

    #[Test]
    public function splits_price_line_into_bold_amount_and_plain_detail(): void
    {
        $parts = $this->content->formatPriceLineParts(2640, 46, 55, 4, ['+ 120 EUR']);

        $this->assertSame('2 640 PLN + 120 EUR ', $parts['amount']);
        $this->assertSame(
            'za osobę dla grupy 46–55 uczestników + 4 opiekunów gratis',
            $parts['detail']
        );
    }

    #[Test]
    public function splits_top_40_55_tier_into_two_rows(): void
    {
        $split = $this->content->splitTopPriceTier([
            ['from' => 40, 'to' => 55, 'price' => 375, 'other' => ['+ 40 EUR'], 'gratis' => 4],
            ['from' => 35, 'to' => 39, 'price' => 400, 'other' => [], 'gratis' => 3],
        ]);

        $this->assertCount(3, $split);
        $this->assertSame([46, 55, 375, 4], [$split[0]['from'], $split[0]['to'], $split[0]['price'], $split[0]['gratis']]);
        $this->assertSame([40, 45, 375, 3], [$split[1]['from'], $split[1]['to'], $split[1]['price'], $split[1]['gratis']]);
        $this->assertSame(['+ 40 EUR'], $split[0]['other']);
        $this->assertSame(['+ 40 EUR'], $split[1]['other']);
    }

    #[Test]
    public function notes_for_foreign_trip_start_with_passport_requirement(): void
    {
        $foreign = $this->content->notes(true);
        $domestic = $this->content->notes(false);

        $this->assertSame('każdy uczestnik musi posiadać dowód osobisty lub paszport', $foreign[0]);
        $this->assertNotSame($foreign[0], $domestic[0]);
        $this->assertCount(count($domestic) + 1, $foreign);
    }

    #[Test]
    public function accommodation_uses_default_without_hotels(): void
    {
        $lines = $this->content->accommodationLines([]);

        $this->assertSame([WordOfferContent::DEFAULT_ACCOMMODATION], $lines);
        $this->assertTrue($this->content->shouldShowAccommodation(2));
        $this->assertFalse($this->content->shouldShowAccommodation(1));
    }

    #[Test]
    public function accommodation_lists_assigned_hotels(): void
    {
        $lines = $this->content->accommodationLines(['Hotel Alfa', 'Hotel Beta']);

        $this->assertCount(2, $lines);
        $this->assertStringContainsString('Hotel Alfa', $lines[0]);
        $this->assertStringContainsString(WordOfferContent::ACCOMMODATION_ROOM_NOTE, $lines[0]);
    }

    #[Test]
    public function parse_price_description_drops_noise_markers(): void
    {
        $html = '<p><strong>Cena zawiera:</strong></p><ul><li>przejazd</li><li>&gt;</li></ul>'
            .'<p><strong>Cena nie zawiera:</strong></p><ul><li>wydatków</li></ul>';

        $parsed = $this->content->parsePriceDescriptionHtml($html);

        $this->assertSame(['przejazd'], $parsed['includes']);
        $this->assertSame(['wydatków'], $parsed['excludes']);
    }

    #[Test]
    public function offer_validity_is_ten_days(): void
    {
        $this->assertSame(10, WordOfferContent::OFFER_VALIDITY_DAYS);
        $this->assertStringContainsString('10 dni', $this->content->offerValidityLabel());
    }
}
