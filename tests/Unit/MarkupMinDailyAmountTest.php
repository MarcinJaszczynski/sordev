<?php

namespace Tests\Unit;

use App\Models\Markup;
use PHPUnit\Framework\TestCase;

class MarkupMinDailyAmountTest extends TestCase
{
    public function test_uses_percent_when_above_daily_minimum(): void
    {
        $result = Markup::calculateAmount(10_000, 20, 750, 1);

        $this->assertSame(2000.0, $result['amount']);
        $this->assertFalse($result['min_daily_applied']);
        $this->assertSame(750.0, $result['min_daily_floor']);
    }

    public function test_uses_daily_minimum_when_percent_is_lower(): void
    {
        // 20% z 1500 = 300 < 750 → floor 750
        $result = Markup::calculateAmount(1500, 20, 750, 1);

        $this->assertSame(750.0, $result['amount']);
        $this->assertTrue($result['min_daily_applied']);
        $this->assertSame(750.0, $result['min_daily_floor']);
    }

    public function test_daily_minimum_scales_with_duration_days(): void
    {
        // 20% z 1500 = 300 < 750×3 = 2250
        $result = Markup::calculateAmount(1500, 20, 750, 3);

        $this->assertSame(2250.0, $result['amount']);
        $this->assertTrue($result['min_daily_applied']);
        $this->assertSame(2250.0, $result['min_daily_floor']);
    }

    public function test_zero_min_daily_keeps_percent_only(): void
    {
        $result = Markup::calculateAmount(1500, 20, 0, 5);

        $this->assertSame(300.0, $result['amount']);
        $this->assertFalse($result['min_daily_applied']);
    }

    public function test_days_below_one_treated_as_one(): void
    {
        $result = Markup::calculateAmount(100, 10, 750, 0);

        $this->assertSame(750.0, $result['amount']);
        $this->assertTrue($result['min_daily_applied']);
    }

    public function test_instance_amount_for_base_reads_model_fields(): void
    {
        $markup = new Markup([
            'percent' => 20,
            'min_daily_amount_pln' => 750,
        ]);

        $result = $markup->amountForBase(1000, 2);

        $this->assertSame(1500.0, $result['amount']);
        $this->assertTrue($result['min_daily_applied']);
        $this->assertSame(20.0, $result['percent_applied']);
    }
}
