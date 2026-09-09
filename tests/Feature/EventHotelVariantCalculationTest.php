<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventHotelStay;
use App\Models\EventQty;
use App\Models\EventTemplate;
use App\Models\EventTemplateHotelDay;
use App\Models\HotelRoom;
use App\Models\Place;
use App\Models\User;
use App\Services\EventCalculationSnapshotBuilder;
use App\Services\EventCostCalculator;
use App\Services\EventHotelPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventHotelVariantCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_variants_get_different_hotel_costs_for_different_qty(): void
    {
        $user = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $place = Place::factory()->starting()->create();
        $template = EventTemplate::factory()->create([
            'duration_days' => 2,
            'start_place_id' => $place->id,
        ]);

        $room = HotelRoom::create([
            'name' => 'Twin',
            'people_count' => 2,
            'price' => 200,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);

        EventTemplateHotelDay::create([
            'event_template_id' => $template->id,
            'day' => 1,
            'hotel_room_ids_qty' => [$room->id],
            'hotel_room_ids_gratis' => [],
            'hotel_room_ids_staff' => [],
            'hotel_room_ids_driver' => [],
        ]);

        $this->assertTrue(Auth::check());

        $event = Event::createFromTemplate($template, [
            'name' => 'Warianty hotel',
            'client_name' => 'Klient',
            'start_date' => now()->format('Y-m-d'),
            'participant_count' => 50,
            'use_manual_transport_cost' => true,
            'manual_transport_cost' => 0,
            'created_by' => $user->id,
        ]);

        EventQty::query()->where('event_id', $event->id)->delete();
        EventQty::create(['event_id' => $event->id, 'qty' => 50, 'gratis' => 0, 'staff' => 0, 'driver' => 0]);
        EventQty::create(['event_id' => $event->id, 'qty' => 30, 'gratis' => 0, 'staff' => 0, 'driver' => 0]);

        $calc50 = EventCostCalculator::for($event->fresh())->calculate(50, 0, 0, 0);
        $calc30 = EventCostCalculator::for($event->fresh())->calculate(30, 0, 0, 0);

        $hotel50 = collect($calc50['lines'] ?? [])
            ->firstWhere('category', 'accommodation');
        $hotel30 = collect($calc30['lines'] ?? [])
            ->firstWhere('category', 'accommodation');

        $this->assertNotNull($hotel50);
        $this->assertNotNull($hotel30);
        $this->assertGreaterThan((float) $hotel30['cost_pln'], (float) $hotel50['cost_pln']);
        $this->assertEqualsWithDelta(5000.0, (float) $hotel50['cost_pln'], 0.01);
        $this->assertEqualsWithDelta(3000.0, (float) $hotel30['cost_pln'], 0.01);
    }

    public function test_flat_stay_per_person_appears_in_detailed_calculation_points(): void
    {
        $event = Event::factory()->create([
            'duration_days' => 2,
            'participant_count' => 50,
            'hotel_pricing_mode' => 'flat_stay_per_person',
            'hotel_flat_stay_amount' => 100,
            'hotel_flat_stay_convert_to_pln' => true,
            'use_manual_transport_cost' => true,
            'manual_transport_cost' => 0,
        ]);

        EventQty::create(['event_id' => $event->id, 'qty' => 50, 'gratis' => 0, 'staff' => 0, 'driver' => 0]);
        EventQty::create(['event_id' => $event->id, 'qty' => 30, 'gratis' => 0, 'staff' => 0, 'driver' => 0]);

        EventHotelStay::create(['event_id' => $event->id, 'day' => 1]);
        EventHotelStay::create(['event_id' => $event->id, 'day' => 2]);

        $service = app(EventHotelPlanService::class);
        $this->assertEqualsWithDelta(5000.0, $service->totalsByCurrencyForVariant($event->fresh(), [
            'qty' => 50, 'gratis' => 0, 'staff' => 0, 'driver' => 0,
        ])['PLN'] ?? 0, 0.01);
        $this->assertEqualsWithDelta(3000.0, $service->totalsByCurrencyForVariant($event->fresh(), [
            'qty' => 30, 'gratis' => 0, 'staff' => 0, 'driver' => 0,
        ])['PLN'] ?? 0, 0.01);

        $payload = app(EventCalculationSnapshotBuilder::class)
            ->buildDetailedForVariant($event->fresh(), [
                'qty' => 30,
                'gratis' => 0,
                'staff' => 0,
                'driver' => 0,
            ]);

        $detailed = $payload['detailed_calculations'][30]
            ?? $payload['detailed_calculations']['30']
            ?? [];
        $points = collect($detailed['PLN']['points'] ?? []);
        $hotelPoints = $points->filter(function (array $point): bool {
            $name = (string) ($point['name'] ?? '');

            return str_starts_with($name, 'Hotel -')
                || str_contains($name, 'Nocleg');
        });

        $this->assertFalse($hotelPoints->isEmpty(), 'Koszt noclegu flat powinien być w punktach kalkulacji');
        $this->assertEqualsWithDelta(
            3000.0,
            (float) $hotelPoints->sum(fn (array $p) => (float) ($p['cost'] ?? 0)),
            0.01,
        );
        $this->assertGreaterThanOrEqual(
            3000.0,
            (float) ($detailed['PLN']['total'] ?? 0),
        );
    }

    /**
     * Snapshot przy małym pax zamraża tylko wybrane typy (np. same Trpl).
     * Warianty qty muszą realokować z pełnej półki szablonu (Trpl+Twin),
     * inaczej kalkulacja imprezy rozjeżdża się ze szablonem.
     */
    public function test_event_variant_hotel_cost_matches_template_shelf_after_narrow_snapshot(): void
    {
        $user = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $place = Place::factory()->starting()->create();
        $template = EventTemplate::factory()->create([
            'duration_days' => 2,
            'start_place_id' => $place->id,
        ]);

        $triple = HotelRoom::create([
            'name' => 'Trpl',
            'people_count' => 3,
            'price' => 210,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);
        $twin = HotelRoom::create([
            'name' => 'Twin',
            'people_count' => 2,
            'price' => 140,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);
        $single = HotelRoom::create([
            'name' => 'Sgl',
            'people_count' => 1,
            'price' => 110,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);

        EventTemplateHotelDay::create([
            'event_template_id' => $template->id,
            'day' => 1,
            'hotel_room_ids_qty' => [$triple->id, $twin->id],
            'hotel_room_ids_gratis' => [$twin->id, $single->id],
            'hotel_room_ids_staff' => [$single->id],
            'hotel_room_ids_driver' => [],
        ]);

        $event = Event::createFromTemplate($template, [
            'name' => 'Narrow shelf',
            'client_name' => 'Klient',
            'start_date' => now()->format('Y-m-d'),
            'participant_count' => 15,
            'use_manual_transport_cost' => true,
            'manual_transport_cost' => 0,
            'created_by' => $user->id,
        ]);

        EventQty::query()->where('event_id', $event->id)->delete();
        EventQty::create(['event_id' => $event->id, 'qty' => 15, 'gratis' => 1, 'staff' => 1, 'driver' => 0]);
        EventQty::create(['event_id' => $event->id, 'qty' => 30, 'gratis' => 3, 'staff' => 1, 'driver' => 1]);

        $event = $event->fresh(['hotelStays.roomLines']);
        $qtyRoomIds = $event->hotelStays->flatMap->roomLines
            ->where('role', 'qty')
            ->pluck('hotel_room_id')
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->all();
        $this->assertSame([$triple->id], $qtyRoomIds, 'Snapshot przy 15 osobach powinien zamrozić tylko Trpl');

        $service = app(EventHotelPlanService::class);
        $group30 = ['qty' => 30, 'gratis' => 3, 'staff' => 1, 'driver' => 1];

        $templateHotelPln = 0.0;
        foreach ($template->hotelDays()->get() as $hotelDay) {
            foreach ($group30 as $role => $people) {
                if ($people <= 0) {
                    continue;
                }
                $ids = $hotelDay->{"hotel_room_ids_{$role}"} ?? [];
                foreach ($service->allocateRoomLines($people, is_array($ids) ? $ids : [], $role) as $line) {
                    $templateHotelPln += (float) $line['unit_price'] * (int) $line['quantity'];
                }
            }
        }

        $eventHotelPln = (float) ($service->totalsByCurrencyForVariant($event, $group30)['PLN'] ?? 0);

        $this->assertEqualsWithDelta($templateHotelPln, $eventHotelPln, 0.01);
        // 10×Trpl + (1×Twin+1×Sgl) + 1×Sgl + driver 0 = 2100+250+110 = 2460 (1 noc)
        $this->assertEqualsWithDelta(2460.0, $eventHotelPln, 0.01);

        $structure = $service->buildHotelStructureForCalculationVariant($event, $group30);
        $dayRooms = collect($structure->first()['rooms'] ?? []);
        $qtyTriple = $dayRooms->first(fn (array $room): bool => ($room['group_type'] ?? '') === 'qty'
            && (int) ($room['room']->people_count ?? 0) === 3);
        $this->assertNotNull($qtyTriple);
        $this->assertSame(10, (int) $qtyTriple['room_count']);
        $gratisTwin = $dayRooms->first(fn (array $room): bool => ($room['group_type'] ?? '') === 'gratis'
            && (int) ($room['room']->people_count ?? 0) === 2);
        $this->assertNotNull($gratisTwin, 'Gratis powinien dostać Twin ze półki szablonu');
    }
}
