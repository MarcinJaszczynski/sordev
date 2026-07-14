<?php

namespace Tests\Unit;

use App\Models\Bus;
use App\Models\Currency;
use App\Models\Event;
use App\Services\EventTransportCostCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTransportCostCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_transport_km_from_transfer_and_program(): void
    {
        $event = Event::factory()->create([
            'transfer_km' => 500,
            'program_km' => 370,
            'duration_days' => 4,
        ]);

        $calculator = new EventTransportCostCalculator($event);

        $this->assertSame(1370.0, $calculator->resolveTransportKm());
    }

    public function test_cost_within_included_km_package(): void
    {
        $bus = Bus::factory()->create([
            'capacity' => 49,
            'package_price_per_day' => 1000,
            'package_km_per_day' => 5000,
            'extra_km_price' => 10,
            'currency' => 'PLN',
        ]);

        $event = Event::factory()->create([
            'bus_id' => $bus->id,
            'transfer_km' => 10,
            'program_km' => 20,
            'duration_days' => 2,
            'participant_count' => 30,
        ]);
        $event->setRelation('bus', $bus);

        $calculator = new EventTransportCostCalculator($event);
        // km = 40, included = 2 * 5000 = 10000
        $this->assertSame(2000.0, $calculator->costForVariant(['qty' => 30, 'gratis' => 0, 'staff' => 1, 'driver' => 1]));
    }

    public function test_cost_with_extra_km_and_bus_multiplier(): void
    {
        $bus = Bus::factory()->create([
            'capacity' => 49,
            'package_price_per_day' => 2430,
            'package_km_per_day' => 300,
            'extra_km_price' => 8.1,
            'currency' => 'PLN',
        ]);

        $event = Event::factory()->create([
            'bus_id' => $bus->id,
            'transfer_km' => 500.17,
            'program_km' => 370,
            'duration_days' => 4,
            'participant_count' => 30,
        ]);
        $event->setRelation('bus', $bus);

        $calculator = new EventTransportCostCalculator($event);
        $cost = $calculator->costForVariant(['qty' => 30, 'gratis' => 0, 'staff' => 1, 'driver' => 1]);

        $this->assertSame(11099.75, $cost);
    }

    public function test_sync_transport_inserts_line_into_detailed_calculations(): void
    {
        $bus = Bus::factory()->create();
        $event = Event::factory()->create([
            'bus_id' => $bus->id,
            'transfer_km' => 100,
            'program_km' => 50,
            'duration_days' => 3,
        ]);
        $event->setRelation('bus', $bus);

        $detailed = [
            10 => [
                'PLN' => [
                    'points' => [
                        ['name' => 'Hotel', 'cost' => 1000, 'currency_symbol' => 'PLN'],
                    ],
                    'total' => 1000,
                    'total_before_markup' => 1000,
                    'total_before_tax' => 1000,
                ],
                'markup' => ['percent_applied' => 0, 'amount' => 0],
                'taxes' => ['total_amount' => 0, 'breakdown' => []],
            ],
        ];

        $calculator = new EventTransportCostCalculator($event);
        $calculator->syncTransportInDetailedCalculations(
            $detailed,
            [10 => ['qty' => 10, 'gratis' => 0, 'staff' => 1, 'driver' => 1]],
            ['qty' => 10, 'gratis' => 0, 'staff' => 1, 'driver' => 1],
            fn () => null,
        );

        $names = collect($detailed[10]['PLN']['points'])->pluck('name');
        $this->assertTrue($names->contains(EventTransportCostCalculator::TRANSPORT_POINT_NAME));
    }

    public function test_manual_transport_cost_overrides_bus_calculation(): void
    {
        $bus = Bus::factory()->create([
            'capacity' => 49,
            'package_price_per_day' => 5000,
            'package_km_per_day' => 100,
            'extra_km_price' => 10,
            'currency' => 'PLN',
        ]);

        $event = Event::factory()->create([
            'bus_id' => $bus->id,
            'transfer_km' => 500,
            'program_km' => 200,
            'duration_days' => 4,
            'use_manual_transport_cost' => true,
            'manual_transport_cost' => 4200.50,
        ]);
        $event->setRelation('bus', $bus);

        $calculator = new EventTransportCostCalculator($event);

        $this->assertTrue($calculator->usesManualTransportCost());
        $this->assertSame(4200.5, $calculator->effectiveTransportCost());
    }

    public function test_manual_transport_syncs_into_detailed_calculations_without_bus(): void
    {
        $event = Event::factory()->create([
            'bus_id' => null,
            'use_manual_transport_cost' => true,
            'manual_transport_cost' => 3500,
        ]);

        $detailed = [
            20 => [
                'PLN' => [
                    'points' => [],
                    'total' => 0,
                    'total_before_markup' => 0,
                    'total_before_tax' => 0,
                ],
                'markup' => ['percent_applied' => 0, 'amount' => 0],
                'taxes' => ['total_amount' => 0, 'breakdown' => []],
            ],
        ];

        $calculator = new EventTransportCostCalculator($event);
        $calculator->syncTransportInDetailedCalculations(
            $detailed,
            [20 => ['qty' => 20, 'gratis' => 0, 'staff' => 1, 'driver' => 1]],
            ['qty' => 20, 'gratis' => 0, 'staff' => 1, 'driver' => 1],
            fn () => null,
        );

        $names = collect($detailed[20]['PLN']['points'])->pluck('name');
        $this->assertTrue($names->contains(EventTransportCostCalculator::MANUAL_TRANSPORT_POINT_NAME));
    }

    public function test_converts_foreign_bus_currency_to_pln(): void
    {
        Currency::factory()->eur()->create();

        $bus = Bus::factory()->create([
            'capacity' => 50,
            'package_price_per_day' => 100,
            'package_km_per_day' => 10000,
            'extra_km_price' => 0,
            'currency' => 'EUR',
        ]);

        $event = Event::factory()->create([
            'bus_id' => $bus->id,
            'transfer_km' => 0,
            'program_km' => 0,
            'duration_days' => 1,
        ]);
        $event->setRelation('bus', $bus);

        $calculator = new EventTransportCostCalculator($event);
        $this->assertSame(450.0, $calculator->costForVariant(['qty' => 10, 'gratis' => 0, 'staff' => 0, 'driver' => 0]));
    }
}
