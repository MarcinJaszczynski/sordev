<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\EventTemplate;
use App\Models\EventTemplatePricePerPerson;
use App\Models\EventTemplateQty;
use App\Models\EventTemplateStartingPlaceAvailability;
use App\Models\Place;
use App\Services\EventTemplateCalculationEngine;
use App\Services\UnifiedPriceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTemplatePriceRecalcWwwVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_recalculate_creates_prices_when_ppp_was_empty(): void
    {
        $pln = Currency::factory()->create([
            'name' => 'Polski złoty',
            'symbol' => 'PLN',
            'code' => 'PLN',
            'exchange_rate' => 1,
        ]);
        EventTemplateQty::create(['qty' => 40, 'gratis' => 3, 'staff' => 1, 'driver' => 1]);

        $start = Place::factory()->starting()->create(['name' => 'Warszawa']);
        $template = EventTemplate::factory()->create([
            'is_active' => true,
            'duration_days' => 2,
            'start_place_id' => $start->id,
            'end_place_id' => $start->id,
            'program_km' => 100,
        ]);

        EventTemplateStartingPlaceAvailability::query()->create([
            'event_template_id' => $template->id,
            'start_place_id' => $start->id,
            'end_place_id' => $start->id,
            'available' => true,
        ]);

        $this->assertSame(0, EventTemplatePricePerPerson::where('event_template_id', $template->id)->count());
        $this->assertTrue($template->qtyVariants()->get()->isEmpty());

        (new UnifiedPriceCalculator)->recalculateForTemplate($template);

        $prices = EventTemplatePricePerPerson::query()
            ->where('event_template_id', $template->id)
            ->where('start_place_id', $start->id)
            ->where('currency_id', $pln->id)
            ->where('price_per_person', '>', 0)
            ->get();

        $this->assertNotEmpty($prices, 'Po przeliczeniu z pustego PPP muszą powstać lokalne ceny PLN > 0 (WWW).');
    }

    public function test_force_delete_existing_does_not_wipe_when_calculation_returns_empty(): void
    {
        Currency::factory()->create([
            'name' => 'Polski złoty',
            'symbol' => 'PLN',
            'code' => 'PLN',
            'exchange_rate' => 1,
        ]);
        $qty = EventTemplateQty::create(['qty' => 40]);
        $pln = Currency::query()->where('symbol', 'PLN')->first();

        $start = Place::factory()->starting()->create(['name' => 'Poznań']);
        $template = EventTemplate::factory()->create([
            'is_active' => true,
            'duration_days' => 1,
            'start_place_id' => $start->id,
            'end_place_id' => $start->id,
        ]);

        EventTemplateStartingPlaceAvailability::query()->create([
            'event_template_id' => $template->id,
            'start_place_id' => $start->id,
            'end_place_id' => $start->id,
            'available' => true,
        ]);

        $existing = EventTemplatePricePerPerson::factory()->create([
            'event_template_id' => $template->id,
            'event_template_qty_id' => $qty->id,
            'currency_id' => $pln->id,
            'start_place_id' => $start->id,
            'price_per_person' => 420,
        ]);

        $emptyEngine = new class extends EventTemplateCalculationEngine
        {
            public function calculateDetailed(
                EventTemplate $template,
                ?int $startPlaceId = null,
                ?float $transportKm = null,
                bool $debug = false,
                ?iterable $qtyVariantsOverride = null,
                mixed $busOverride = null,
            ): array {
                return [];
            }
        };

        (new UnifiedPriceCalculator($emptyEngine))->calculateAndPersist($template, $start->id, true);

        $this->assertDatabaseHas('event_template_price_per_person', [
            'id' => $existing->id,
            'price_per_person' => 420,
        ]);
    }

    public function test_force_delete_existing_does_not_wipe_when_pln_is_zero(): void
    {
        Currency::factory()->create([
            'name' => 'Polski złoty',
            'symbol' => 'PLN',
            'code' => 'PLN',
            'exchange_rate' => 1,
        ]);
        $qty = EventTemplateQty::create(['qty' => 40]);
        $pln = Currency::query()->where('symbol', 'PLN')->first();

        $start = Place::factory()->starting()->create(['name' => 'Kraków']);
        $template = EventTemplate::factory()->create([
            'is_active' => true,
            'duration_days' => 1,
            'start_place_id' => $start->id,
            'end_place_id' => $start->id,
        ]);

        EventTemplateStartingPlaceAvailability::query()->create([
            'event_template_id' => $template->id,
            'start_place_id' => $start->id,
            'end_place_id' => $start->id,
            'available' => true,
        ]);

        $existing = EventTemplatePricePerPerson::factory()->create([
            'event_template_id' => $template->id,
            'event_template_qty_id' => $qty->id,
            'currency_id' => $pln->id,
            'start_place_id' => $start->id,
            'price_per_person' => 350,
        ]);

        $zeroEngine = new class extends EventTemplateCalculationEngine
        {
            public function calculateDetailed(
                EventTemplate $template,
                ?int $startPlaceId = null,
                ?float $transportKm = null,
                bool $debug = false,
                ?iterable $qtyVariantsOverride = null,
                mixed $busOverride = null,
            ): array {
                $qtyModel = EventTemplateQty::query()->where('qty', 40)->first();

                return [
                    40 => [
                        'event_template_qty_id' => $qtyModel?->id,
                        'qty' => 40,
                        'currencies' => [
                            'PLN' => [
                                'raw' => ['price_per_person' => 0],
                                'final' => ['price_per_person' => 0],
                            ],
                        ],
                    ],
                ];
            }
        };

        (new UnifiedPriceCalculator($zeroEngine))->calculateAndPersist($template, $start->id, true);

        $this->assertDatabaseHas('event_template_price_per_person', [
            'id' => $existing->id,
            'price_per_person' => 350,
        ]);
    }
}
