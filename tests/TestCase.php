<?php

namespace Tests;

use App\Models\Currency;
use App\Support\Tasks\TaskQueryFilters;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase truncate'uje tabele bez eventów Eloquent —
        // statyczne cache ID muszą być czyszczone między testami.
        Currency::clearPlnIdsCache();
        TaskQueryFilters::clearStatusIdCaches();
    }
}
