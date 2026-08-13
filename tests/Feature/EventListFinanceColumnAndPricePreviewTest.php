<?php

namespace Tests\Feature;

use App\Filament\Forms\EventPricePerPersonFields;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventQty;
use App\Models\User;
use App\Support\EventListFinanceColumn;
use App\Support\MoneyFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventListFinanceColumnAndPricePreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        EventListFinanceColumn::resetWarmCache();
    }

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

    public function test_html_uses_client_price_not_base_total_cost(): void
    {
        $user = User::factory()->create();
        $eur = Currency::query()->create([
            'name' => 'Euro',
            'symbol' => 'EUR',
            'code' => 'EUR',
            'exchange_rate' => 4.0,
        ]);

        $event = Event::factory()->create([
            'assigned_to' => $user->id,
            'participant_count' => 10,
            'total_cost' => 1000, // baza — nie wolno jej dzielić jako ceny klienta
        ]);
        EventQty::create([
            'event_id' => $event->id,
            'qty' => 10,
            'gratis' => 0,
            'staff' => 1,
            'driver' => 1,
        ]);
        EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Bilet',
            'unit_price' => 25,
            'quantity' => 1,
            'group_size' => 1,
            'currency_id' => $eur->id,
            'convert_to_pln' => false,
            'include_in_calculation' => true,
            'include_in_program' => true,
            'active' => true,
        ]);

        $event->forceFill(['agreements_amount_paid_total' => 0]);

        $html = EventListFinanceColumn::html($event->fresh(), 'PLN');

        $this->assertStringContainsString('Wpłaty klienta:', $html);
        $this->assertStringContainsString('Cena za os.:', $html);
        $this->assertStringContainsString('Koszt bazowy:', $html);
        $this->assertStringContainsString('EUR', $html);
        // Nie pokazuj ceny z bazy (1000/10 = 100 PLN) jako ceny za osobę
        $this->assertStringNotContainsString(MoneyFormatter::format(100, 'PLN'), $html);
        $this->assertStringNotContainsString('Klient:', $html);
        $this->assertStringNotContainsString('Ubezpieczenie:', $html);
    }

    public function test_html_marks_underpaid_against_group_total_not_base(): void
    {
        $user = User::factory()->create();
        $pln = Currency::query()->create([
            'name' => 'Złoty',
            'symbol' => 'PLN',
            'code' => 'PLN',
            'exchange_rate' => 1,
        ]);

        $event = Event::factory()->create([
            'assigned_to' => $user->id,
            'participant_count' => 10,
            'total_cost' => 500, // baza niska
        ]);
        EventQty::create([
            'event_id' => $event->id,
            'qty' => 10,
            'gratis' => 0,
            'staff' => 1,
            'driver' => 1,
        ]);
        EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Wstęp',
            'unit_price' => 200,
            'quantity' => 1,
            'group_size' => 1,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
            'include_in_calculation' => true,
            'include_in_program' => true,
            'active' => true,
        ]);

        // Mała wpłata względem sumy grupy (nie względem bazy 500)
        $event->forceFill(['agreements_amount_paid_total' => 100]);

        $html = EventListFinanceColumn::html($event->fresh(), 'PLN');

        $this->assertStringContainsString('#2563eb', $html);
    }

    public function test_resolve_gratis_ignores_form_zero_when_variant_has_caregivers(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'assigned_to' => $user->id,
            'participant_count' => 33,
        ]);
        EventQty::create([
            'event_id' => $event->id,
            'qty' => 33,
            'gratis' => 4,
            'staff' => 1,
            'driver' => 1,
        ]);

        $get = fn (string $key) => ['gratis_count' => 0, 'participant_count' => 33][$key] ?? null;

        $this->assertSame(4, EventPricePerPersonFields::resolveGratisFromForm($get, $event->fresh(), 33));
    }

    public function test_resolve_gratis_accepts_explicit_form_value_when_changed(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'assigned_to' => $user->id,
            'participant_count' => 33,
        ]);
        EventQty::create([
            'event_id' => $event->id,
            'qty' => 33,
            'gratis' => 4,
            'staff' => 1,
            'driver' => 1,
        ]);

        $get = fn (string $key) => ['gratis_count' => 2, 'participant_count' => 33][$key] ?? null;

        $this->assertSame(2, EventPricePerPersonFields::resolveGratisFromForm($get, $event->fresh(), 33));
    }

    public function test_format_summary_hides_breakdown_for_non_admin(): void
    {
        Role::firstOrCreate(['name' => 'biuro']);
        $user = User::factory()->create();
        $user->assignRole('biuro');
        $this->actingAs($user);

        $html = EventPricePerPersonFields::formatSummaryContent([
            'ready' => true,
            'price_per_person_label' => '2 860,00 PLN + 128,47 EUR',
            'total_pln' => 94227.17,
            'base_pln' => 77203.75,
            'markup_pln' => 11580.56,
            'tax_pln' => 5442.86,
            'paying' => 33,
            'gratis' => 4,
            'nearest' => [],
        ]);

        $this->assertStringContainsString('Za osobę: 2 860,00 PLN + 128,47 EUR', $html);
        $this->assertStringContainsString('Suma grupy:', $html);
        $this->assertStringContainsString('Płacących: 33', $html);
        $this->assertStringNotContainsString('Baza / marża / podatki', $html);
    }

    public function test_format_summary_shows_breakdown_for_admin(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $html = EventPricePerPersonFields::formatSummaryContent([
            'ready' => true,
            'price_per_person_label' => '2 860,00 PLN',
            'total_pln' => 94227.17,
            'base_pln' => 77203.75,
            'markup_pln' => 11580.56,
            'tax_pln' => 5442.86,
            'paying' => 33,
            'gratis' => 4,
            'nearest' => [],
        ]);

        $this->assertStringContainsString('Baza / marża / podatki', $html);
        $this->assertStringContainsString('77 203,75 PLN', $html);
    }

    public function test_resolved_price_matches_cost_calculator_not_stale_price_table(): void
    {
        $user = User::factory()->create();
        $pln = Currency::query()->create([
            'name' => 'Złoty',
            'symbol' => 'PLN',
            'code' => 'PLN',
            'exchange_rate' => 1,
        ]);

        $event = Event::factory()->create([
            'assigned_to' => $user->id,
            'participant_count' => 10,
            'total_cost' => 500,
        ]);
        EventQty::create([
            'event_id' => $event->id,
            'qty' => 10,
            'gratis' => 0,
            'staff' => 1,
            'driver' => 1,
        ]);
        EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Wstęp',
            'unit_price' => 200,
            'quantity' => 1,
            'group_size' => 1,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
            'include_in_calculation' => true,
            'include_in_program' => true,
            'active' => true,
        ]);

        // Stary wiersz cennika (qty=12) — NIE wolno go brać zamiast live kalkulacji.
        \App\Models\EventPricePerPerson::create([
            'event_id' => $event->id,
            'price_per_person' => 999.99,
            'price_with_tax' => 9999.99,
            'is_manual' => false,
            'currency_id' => $pln->id,
        ]);

        $event = $event->fresh();
        $calc = \App\Services\EventCostCalculator::for($event)->calculate(10, 0);
        $expected = (float) ($calc['price_per_person_rounded'] ?? $calc['price_per_person']);

        $this->assertSame($expected, $event->resolvedPricePerPerson(10));
        $this->assertSame(round((float) $calc['base_pln'], 2), $event->resolvedBaseTotalCost(10));
        $this->assertSame(round((float) $calc['total_pln'], 2), $event->resolvedFullTotalCost(10));
        $this->assertNotEquals(999.99, $event->resolvedPricePerPerson(10));
    }
}
