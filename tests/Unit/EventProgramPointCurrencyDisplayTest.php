<?php

use App\Models\Currency;
use App\Models\EventProgramPoint;

test('program point formatAmount respects convert_to_pln flag', function (): void {
    $point = new EventProgramPoint([
        'convert_to_pln' => false,
    ]);
    $point->setRelation('currency', new Currency([
        'symbol' => 'EUR',
        'exchange_rate' => 4.30,
    ]));

    expect($point->formatAmount(200))->toBe('200,00 EUR');
    expect($point->formatAmount(200, 0))->toBe('200 EUR');
});

test('program point formatAmount appends pln when converted', function (): void {
    $point = new EventProgramPoint([
        'convert_to_pln' => true,
    ]);
    $point->setRelation('currency', new Currency([
        'symbol' => 'EUR',
        'exchange_rate' => 4.30,
    ]));

    expect($point->formatAmount(200))->toBe('200,00 EUR (≈ 860,00 PLN)');
});
