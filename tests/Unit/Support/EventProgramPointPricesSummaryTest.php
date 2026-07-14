<?php

namespace Tests\Unit\Support;

use App\Support\EventProgramPointPricesSummary;
use Tests\TestCase;

class EventProgramPointPricesSummaryTest extends TestCase
{
    public function test_resolve_paid_status(): void
    {
        $this->assertSame(
            EventProgramPointPricesSummary::STATUS_FULL,
            EventProgramPointPricesSummary::resolvePaidStatus(1000, 1000),
        );

        $this->assertSame(
            EventProgramPointPricesSummary::STATUS_FULL,
            EventProgramPointPricesSummary::resolvePaidStatus(1000.005, 1000),
        );

        $this->assertSame(
            EventProgramPointPricesSummary::STATUS_PARTIAL,
            EventProgramPointPricesSummary::resolvePaidStatus(400, 1000),
        );

        $this->assertSame(
            EventProgramPointPricesSummary::STATUS_NONE,
            EventProgramPointPricesSummary::resolvePaidStatus(0, 1000),
        );
    }
}
