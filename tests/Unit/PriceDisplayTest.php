<?php

use App\Models\Currency;
use App\Models\EventTemplate;
use App\Models\EventTemplatePricePerPerson;
use App\Models\EventTemplateQty;
use App\Models\EventType;
use App\Support\PriceDisplay;
use Illuminate\Support\Collection;
use Tests\TestCase;

uses(TestCase::class);

function makePriceRow(float $price, string $currencySymbol, int $qtyId = 1, ?int $startPlaceId = null, float $exchangeRate = 1.0): EventTemplatePricePerPerson
{
    $currency = new Currency([
        'symbol' => $currencySymbol,
        'exchange_rate' => $exchangeRate,
        'code' => $currencySymbol,
        'name' => $currencySymbol,
    ]);

    $qty = new EventTemplateQty([
        'qty' => 30,
    ]);

    $row = new EventTemplatePricePerPerson([
        'event_template_qty_id' => $qtyId,
        'price_per_person' => $price,
        'start_place_id' => $startPlaceId,
    ]);

    $row->setRelation('currency', $currency);
    $row->setRelation('eventTemplateQty', $qty);

    return $row;
}

function makeTemplate(array $eventTypeNames): EventTemplate
{
    $template = new EventTemplate();

    $types = array_map(function (string $name) {
        return new EventType(['name' => $name]);
    }, $eventTypeNames);

    $template->setRelation('eventTypes', collect($types));

    return $template;
}

test('price display hides foreign supplements for domestic templates', function () {
    $template = makeTemplate(['Krajowe']);

    $plnRow = makePriceRow(500, 'PLN');
    $eurRow = makePriceRow(120, 'EUR', 1, null, 4.5);

    /** @var Collection<int, EventTemplatePricePerPerson> $rows */
    $rows = collect([$plnRow, $eurRow]);
    $template->setRelation('pricesPerPerson', $rows);

    $result = PriceDisplay::collectVariants($template);

    expect($result['variants'])->toHaveCount(1);
    $variant = $result['variants']->first();
    expect($variant['extras'])->toBeInstanceOf(Collection::class);
    expect($variant['extras'])->toHaveCount(0);
    expect($variant['display'])->toContain('PLN');
    expect($variant['display'])->not->toContain('EUR');
});

test('price display keeps foreign supplements for foreign templates', function () {
    $template = makeTemplate(['Zagraniczne']);

    $plnRow = makePriceRow(500, 'PLN');
    $eurRow = makePriceRow(120, 'EUR', 1, null, 4.5);

    $rows = collect([$plnRow, $eurRow]);
    $template->setRelation('pricesPerPerson', $rows);

    $result = PriceDisplay::collectVariants($template);

    expect($result['variants'])->toHaveCount(1);
    $variant = $result['variants']->first();
    expect($variant['extras'])->toBeInstanceOf(Collection::class);
    expect($variant['extras'])->toHaveCount(1);
    expect($variant['display'])->toContain('EUR');
});
