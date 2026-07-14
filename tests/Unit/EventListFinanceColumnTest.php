<?php

namespace Tests\Unit;

use App\Support\EventListFinanceColumn;
use PHPUnit\Framework\TestCase;

class EventListFinanceColumnTest extends TestCase
{
    public function test_resolve_currency_code_defaults_to_pln(): void
    {
        $this->assertSame('PLN', EventListFinanceColumn::resolveCurrencyCode(null));
        $this->assertSame('PLN', EventListFinanceColumn::resolveCurrencyCode(new \stdClass));
    }

    public function test_resolve_currency_code_reads_table_filter(): void
    {
        $livewire = new class
        {
            public array $tableFilters = [
                'finance_display_currency' => ['code' => 'eur'],
            ];
        };

        $this->assertSame('EUR', EventListFinanceColumn::resolveCurrencyCode($livewire));
    }
}
