<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventQty;
use App\Models\EventTemplate;
use App\Models\EventTemplatePricePerPerson;
use App\Models\EventTemplateQty;
use App\Models\Place;
use App\Models\User;
use App\Services\EventCostCalculator;
use App\Services\EventPriceSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventPriceSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_calculator_uses_form_gratis_and_includes_converted_currency(): void
    {
        $user = User::factory()->create();
        $pln = Currency::query()->create([
            'name' => 'Złoty',
            'symbol' => 'PLN',
            'code' => 'PLN',
            'exchange_rate' => 1,
        ]);
        $eur = Currency::query()->create([
            'name' => 'Euro',
            'symbol' => 'EUR',
            'code' => 'EUR',
            'exchange_rate' => 4.0,
        ]);

        $event = Event::factory()->create([
            'assigned_to' => $user->id,
            'participant_count' => 40,
        ]);
        EventQty::create([
            'event_id' => $event->id,
            'qty' => 40,
            'gratis' => 0,
            'staff' => 1,
            'driver' => 1,
        ]);

        EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Bilet',
            'unit_price' => 10,
            'quantity' => 1,
            'group_size' => 1,
            'currency_id' => $eur->id,
            'convert_to_pln' => true,
            'include_in_calculation' => true,
            'include_in_program' => true,
            'active' => true,
        ]);

        // Bez gratis: 40 × 10 EUR × 4 = 1600 PLN
        $withoutGratis = EventCostCalculator::for($event->fresh())->calculate(40, 0);
        $this->assertSame(1600.0, (float) collect($withoutGratis['lines'])->sum('cost_pln'));

        // Z 5 gratisami: 45 × 10 × 4 = 1800 PLN
        $withGratis = EventCostCalculator::for($event->fresh())->calculate(40, 5);
        $this->assertSame(1800.0, (float) collect($withGratis['lines'])->sum('cost_pln'));
        $this->assertSame(5, (int) $withGratis['gratis']);
        $this->assertSame(40, (int) $withGratis['paying']);
        $this->assertGreaterThan(0, (float) $withGratis['price_per_person']);
    }

    public function test_event_calculator_keeps_foreign_bucket_when_not_converted(): void
    {
        $user = User::factory()->create();
        $eur = Currency::query()->create([
            'name' => 'Euro',
            'symbol' => 'EUR',
            'code' => 'EUR',
            'exchange_rate' => 4.35,
        ]);

        $template = EventTemplate::factory()->create();
        $type = \App\Models\EventType::query()->firstOrCreate(['name' => 'Zagraniczne']);
        $template->eventTypes()->sync([$type->id]);

        $event = Event::factory()->create([
            'assigned_to' => $user->id,
            'participant_count' => 10,
            'event_template_id' => $template->id,
        ]);

        EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Muzeum EUR',
            'unit_price' => 20,
            'quantity' => 1,
            'group_size' => 1,
            'currency_id' => $eur->id,
            'convert_to_pln' => false,
            'include_in_calculation' => true,
            'include_in_program' => true,
            'active' => true,
        ]);

        $calc = EventCostCalculator::for($event->fresh(['eventTemplate.eventTypes']))->calculate(10, 0);

        $this->assertArrayHasKey('EUR', $calc['foreign']);
        $this->assertSame(200.0, (float) $calc['foreign']['EUR']['base']);
        $this->assertGreaterThan(0, (float) $calc['foreign']['EUR']['price_per_person']);
    }

    public function test_nearest_template_prices_returns_lower_and_upper_qty(): void
    {
        $place = Place::factory()->starting()->create();
        $template = EventTemplate::factory()->create(['start_place_id' => $place->id]);
        $pln = Currency::query()->create([
            'name' => 'Złoty',
            'symbol' => 'PLN',
            'code' => 'PLN',
            'exchange_rate' => 1,
        ]);

        $q20 = EventTemplateQty::create([
            'qty' => 20,
            'gratis' => 2,
            'staff' => 1,
            'driver' => 1,
        ]);
        $q30 = EventTemplateQty::create([
            'qty' => 30,
            'gratis' => 3,
            'staff' => 1,
            'driver' => 1,
        ]);
        $q40 = EventTemplateQty::create([
            'qty' => 40,
            'gratis' => 4,
            'staff' => 1,
            'driver' => 1,
        ]);

        foreach ([[$q20, 2100], [$q30, 2000], [$q40, 1900]] as [$qty, $price]) {
            EventTemplatePricePerPerson::create([
                'event_template_id' => $template->id,
                'event_template_qty_id' => $qty->id,
                'start_place_id' => $place->id,
                'currency_id' => $pln->id,
                'price_per_person' => $price,
                'price_with_tax' => $price * $qty->qty,
            ]);
        }

        $nearest = app(EventPriceSummaryService::class)->nearestTemplatePrices(
            $template->fresh(),
            $place->id,
            32,
            3,
        );

        $this->assertCount(2, $nearest);
        $qtys = collect($nearest)->pluck('qty')->all();
        $this->assertSame([30, 40], $qtys);
        $this->assertTrue(collect($nearest)->every(fn ($row) => $row['price_per_person'] > 0));
    }

    public function test_nearest_template_prices_prefers_bracket_not_two_closest_below(): void
    {
        $place = Place::factory()->starting()->create();
        $template = EventTemplate::factory()->create(['start_place_id' => $place->id]);
        $pln = Currency::query()->create([
            'name' => 'Złoty',
            'symbol' => 'PLN',
            'code' => 'PLN',
            'exchange_rate' => 1,
        ]);

        $variants = [
            [35, 3, 285],
            [40, 3, 260],
            [45, 3, 245],
        ];
        foreach ($variants as [$qty, $gratis, $price]) {
            $qtyModel = EventTemplateQty::create([
                'qty' => $qty,
                'gratis' => $gratis,
                'staff' => 1,
                'driver' => 1,
            ]);
            EventTemplatePricePerPerson::create([
                'event_template_id' => $template->id,
                'event_template_qty_id' => $qtyModel->id,
                'start_place_id' => $place->id,
                'currency_id' => $pln->id,
                'price_per_person' => $price,
                'price_with_tax' => $price * $qty,
            ]);
        }

        // 42: abs-distance wybrałoby 40 i 35; bracketing → 40 (↓) i 45 (↑).
        $nearest = app(EventPriceSummaryService::class)->nearestTemplatePrices(
            $template->fresh(),
            $place->id,
            42,
            3,
        );

        $this->assertSame([40, 45], collect($nearest)->pluck('qty')->all());
    }

    public function test_payable_total_uses_rounded_price_times_paying(): void
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
            'participant_count' => 42,
            'use_manual_transport_cost' => true,
            'manual_transport_cost' => 0,
        ]);
        EventQty::create([
            'event_id' => $event->id,
            'qty' => 42,
            'gratis' => 3,
            'staff' => 1,
            'driver' => 1,
        ]);

        // 42 × 100 = 4200 baza → przy 0% marży/podatków PPP = 100 (już wielokrotność 5).
        EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Bilet',
            'unit_price' => 100,
            'quantity' => 1,
            'group_size' => 1,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
            'include_in_calculation' => true,
            'include_in_program' => true,
            'active' => true,
        ]);

        // Koszt z gratisami: (42+3)×100 = 4500; PPP raw = 4500/42 ≈ 107.14 → ceil 5 = 110.
        $summary = app(EventPriceSummaryService::class)->forEvent($event->fresh(), 42, 3, includeNearest: false);

        $this->assertTrue($summary['ready']);
        $this->assertSame(110.0, (float) $summary['price_per_person_rounded']);
        $this->assertSame(4620.0, (float) $summary['payable_total_pln']); // 110 × 42
        $this->assertNotSame(
            (float) $summary['payable_total_pln'],
            (float) $summary['total_pln'],
            'Suma do zapłaty (zaokr.) różni się od surowej kalkulacji'
        );
    }

    public function test_cost_calculator_recovers_from_partial_bus_select(): void
    {
        $user = User::factory()->create();
        $bus = Bus::factory()->create([
            'capacity' => 49,
            'package_price_per_day' => 2000,
            'package_km_per_day' => 10000,
            'extra_km_price' => 0,
            'currency' => 'PLN',
        ]);

        $event = Event::factory()->create([
            'assigned_to' => $user->id,
            'bus_id' => $bus->id,
            'transfer_km' => 100,
            'program_km' => 50,
            'duration_days' => 2,
            'participant_count' => 20,
            'use_manual_transport_cost' => false,
        ]);
        EventQty::create([
            'event_id' => $event->id,
            'qty' => 20,
            'gratis' => 0,
            'staff' => 1,
            'driver' => 1,
        ]);

        EventCostCalculator::clearRequestCache();
        $fresh = $event->fresh();
        $fresh->load('bus');
        $withFullBus = EventCostCalculator::for($fresh)->calculate(20, 0);

        EventCostCalculator::clearRequestCache();
        $partialEvent = $event->fresh();
        $partialBus = Bus::query()->select(['id', 'name'])->findOrFail($bus->id);
        $partialEvent->setRelation('bus', $partialBus);
        $withPartial = EventCostCalculator::for($partialEvent)->calculate(20, 0);

        $this->assertSame(
            round((float) $withFullBus['price_per_person'], 2),
            round((float) $withPartial['price_per_person'], 2),
        );
        $this->assertSame(
            round((float) $withFullBus['total_pln'], 2),
            round((float) $withPartial['total_pln'], 2),
        );

        $transportLine = collect($withPartial['lines'] ?? [])
            ->first(fn (array $line): bool => ($line['category'] ?? '') === 'transport');
        $this->assertNotNull($transportLine);
        $this->assertGreaterThan(0, (float) ($transportLine['cost_pln'] ?? 0));
    }

    public function test_cost_calculator_recovers_from_stale_null_bus_relation(): void
    {
        $user = User::factory()->create();
        $bus = Bus::factory()->create([
            'capacity' => 49,
            'package_price_per_day' => 2000,
            'package_km_per_day' => 10000,
            'extra_km_price' => 0,
            'currency' => 'PLN',
        ]);

        $event = Event::factory()->create([
            'assigned_to' => $user->id,
            'bus_id' => $bus->id,
            'transfer_km' => 100,
            'program_km' => 50,
            'duration_days' => 2,
            'participant_count' => 20,
            'use_manual_transport_cost' => false,
        ]);
        EventQty::create([
            'event_id' => $event->id,
            'qty' => 20,
            'gratis' => 0,
            'staff' => 1,
            'driver' => 1,
        ]);

        EventCostCalculator::clearRequestCache();
        $fresh = $event->fresh();
        $fresh->load('bus');
        $withBus = EventCostCalculator::for($fresh)->calculate(20, 0);

        EventCostCalculator::clearRequestCache();
        $stale = $event->fresh();
        $stale->setRelation('bus', null);
        $withStaleNull = EventCostCalculator::for($stale)->calculate(20, 0);

        $this->assertSame(
            round((float) $withBus['price_per_person'], 2),
            round((float) $withStaleNull['price_per_person'], 2),
        );
        $this->assertSame(
            round((float) $withBus['total_pln'], 2),
            round((float) $withStaleNull['total_pln'], 2),
        );
        $this->assertGreaterThan(0, (float) $withStaleNull['total_pln']);

        $transportLine = collect($withStaleNull['lines'] ?? [])
            ->first(fn (array $line): bool => ($line['category'] ?? '') === 'transport');
        $this->assertNotNull($transportLine);
        $this->assertGreaterThan(0, (float) ($transportLine['cost_pln'] ?? 0));
    }
}
