<?php

namespace Tests\Unit;

use App\Models\Event;
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

    public function test_html_shows_only_client_payments_and_price_per_person(): void
    {
        $event = new Event([
            'participant_count' => 2,
            'total_cost' => 3290,
        ]);
        $event->forceFill(['agreements_amount_paid_total' => 3290]);

        $html = EventListFinanceColumn::html($event, 'PLN');

        $this->assertStringContainsString('Wpłaty klienta:', $html);
        $this->assertStringContainsString('Cena za os.:', $html);
        $this->assertStringContainsString('#047857', $html);
        $this->assertStringNotContainsString('Klient:', $html);
        $this->assertStringNotContainsString('Ubezpieczenie:', $html);
        $this->assertStringNotContainsString('Koszty wykonawców:', $html);
    }

    public function test_html_marks_underpaid_payments_blue_when_not_overdue(): void
    {
        $event = new Event([
            'participant_count' => 2,
            'total_cost' => 5000,
        ]);
        $event->forceFill(['agreements_amount_paid_total' => 1000]);

        $html = EventListFinanceColumn::html($event, 'PLN');

        $this->assertStringContainsString('#2563eb', $html);
    }
}
