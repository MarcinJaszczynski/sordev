<?php

use App\Services\ProgramPointPricingCalculator;

test('billable units per person when group size is 1', function (): void {
    expect(ProgramPointPricingCalculator::billableUnits(45, 1))->toBe(45);
});

test('billable units per group uses ceiling division', function (): void {
    expect(ProgramPointPricingCalculator::billableUnits(45, 20))->toBe(3);
    expect(ProgramPointPricingCalculator::billableUnits(40, 20))->toBe(2);
});

test('total price matches template engine formula', function (): void {
    expect(ProgramPointPricingCalculator::totalPrice(300, 45, 20))->toBe(900.0);
    expect(ProgramPointPricingCalculator::totalPrice(50, 45, 1))->toBe(2250.0);
});

test('fixed quantity mode ignores participant count', function (): void {
    expect(ProgramPointPricingCalculator::billableUnits(45, 0, 2))->toBe(2);
    expect(ProgramPointPricingCalculator::totalPrice(100, 45, 0, 2))->toBe(200.0);
});

test('unit price label reflects pricing mode', function (): void {
    expect(ProgramPointPricingCalculator::unitPriceLabel(1))->toBe('Cena za osobę');
    expect(ProgramPointPricingCalculator::unitPriceLabel(20))->toBe('Cena za grupę');
    expect(ProgramPointPricingCalculator::unitPriceLabel(0))->toBe('Cena za sztukę');
});

test('pricing basis maps group size modes', function (): void {
    expect(ProgramPointPricingCalculator::pricingBasisFromGroupSize(1))->toBe(ProgramPointPricingCalculator::BASIS_PER_PERSON);
    expect(ProgramPointPricingCalculator::pricingBasisFromGroupSize(20))->toBe(ProgramPointPricingCalculator::BASIS_PER_GROUP);
    expect(ProgramPointPricingCalculator::pricingBasisFromGroupSize(0))->toBe(ProgramPointPricingCalculator::BASIS_PER_PIECE);
    expect(ProgramPointPricingCalculator::pricingBasisFromGroupSize(null))->toBe(ProgramPointPricingCalculator::BASIS_PER_PERSON);
});
