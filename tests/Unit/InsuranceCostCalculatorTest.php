<?php

namespace Tests\Unit;

use App\Models\Insurance;
use App\Services\InsuranceCostCalculator;
use PHPUnit\Framework\TestCase;

class InsuranceCostCalculatorTest extends TestCase
{
    public function test_inactive_or_disabled_insurance_is_not_chargeable(): void
    {
        $disabled = new Insurance([
            'price_per_person' => 10,
            'active' => true,
            'insurance_enabled' => false,
        ]);
        $inactive = new Insurance([
            'price_per_person' => 10,
            'active' => false,
            'insurance_enabled' => true,
        ]);

        $this->assertFalse(InsuranceCostCalculator::isChargeable($disabled));
        $this->assertFalse(InsuranceCostCalculator::isChargeable($inactive));
        $this->assertSame(0.0, InsuranceCostCalculator::dayAssignmentCost($disabled, 20, 2));
    }

    public function test_day_assignment_cost_includes_gratis(): void
    {
        $insurance = new Insurance([
            'price_per_person' => 5.50,
            'active' => true,
            'insurance_enabled' => true,
        ]);

        // 20 płacących + 2 gratis = 22 × 5.50
        $this->assertSame(121.0, InsuranceCostCalculator::dayAssignmentCost($insurance, 20, 2));
    }

    public function test_total_for_multiple_days(): void
    {
        $insurance = new Insurance([
            'name' => 'NNW',
            'price_per_person' => 5.0,
            'active' => true,
            'insurance_enabled' => true,
        ]);

        $rows = collect([
            (object) ['day' => 1, 'insurance' => $insurance],
            (object) ['day' => 2, 'insurance' => $insurance],
        ]);

        // 2 dni × 5 × (10+1) = 110
        $this->assertSame(110.0, InsuranceCostCalculator::totalForDayAssignments($rows, 10, 1));
    }
}
