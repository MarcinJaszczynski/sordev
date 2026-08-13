<?php

namespace Tests;

use App\Models\ContractorType;
use App\Models\Currency;
use App\Models\Event;
use App\Services\EventCostCalculator;
use App\Support\EventListFinanceColumn;
use App\Support\Tasks\TaskQueryFilters;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Pełny Feature suite przekracza domyślne 600s (SQLite Schema::hasColumn).
        set_time_limit(0);
        @ini_set('max_execution_time', '0');

        // Statyczne cache'e nie mogą przeciekać między testami
        // (RefreshDatabase resetuje ID → kolizje / „fałszywe” wyniki).
        EventCostCalculator::clearRequestCache();
        Currency::clearPlnIdsCache();
        Event::clearContactsByIdsCache();
        TaskQueryFilters::clearStatusIdCaches();
        ContractorType::clearIdsForNamesCache();
        EventListFinanceColumn::resetWarmCache();
    }
}
