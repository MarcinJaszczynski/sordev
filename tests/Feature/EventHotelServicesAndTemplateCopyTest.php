<?php

namespace Tests\Feature;

use App\Filament\Forms\EventProgramPointPricingFields;
use App\Filament\Resources\EventResource\Pages\EventHotelPlanning;
use App\Filament\Resources\EventResource\RelationManagers\EventHotelServicesRelationManager;
use App\Models\Contractor;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventHotelStay;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventTemplate;
use App\Models\EventTemplateProgramPoint;
use App\Models\Place;
use App\Models\User;
use App\Services\EventHotelServiceDuplicator;
use App\Services\ProgramPointSetTimePropagator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class EventHotelServicesAndTemplateCopyTest extends TestCase
{
    use RefreshDatabase;

    public function test_hotel_service_duplicator_creates_records_for_other_days(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $event = Event::factory()->create(['duration_days' => 3, 'participant_count' => 10]);

        $source = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Obiadokolacja',
            'day' => 1,
            'order' => 1,
            'unit_price' => 25,
            'quantity' => 10,
            'total_price' => 250,
            'is_hotel_service' => true,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
            'convert_to_pln' => false,
        ]);

        $created = app(EventHotelServiceDuplicator::class)->duplicateToAllDays($source, $event);

        $this->assertCount(2, $created);
        $this->assertDatabaseCount('event_program_points', 3);
        $this->assertEquals([2, 3], collect($created)->pluck('day')->sort()->values()->all());
    }

    public function test_hotel_service_per_piece_price_flows_to_settlement_cost(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $event = Event::factory()->create(['duration_days' => 2, 'participant_count' => 45]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Alkohol — butelki',
            'day' => 1,
            'order' => 1,
            'unit_price' => 80,
            'group_size' => 0,
            'quantity' => 12,
            'is_hotel_service' => true,
            'include_in_program' => false,
            'include_in_calculation' => true,
            'active' => true,
            'convert_to_pln' => true,
        ]);

        $point->refresh();

        $this->assertSame(960.0, (float) $point->total_price);
        $this->assertSame(12, (int) $point->quantity);

        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $cost = $settlement->upsertCostFromProgramPoint($point->fresh(['currency', 'event']));

        $this->assertSame(960.0, (float) $cost->planned_amount);
        $this->assertSame('Alkohol — butelki', $cost->name);
    }

    public function test_template_copy_includes_nested_set_children(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $place = Place::create(['name' => 'Wilno']);
        $template = EventTemplate::factory()->create([
            'duration_days' => 2,
            'start_place_id' => $place->id,
        ]);

        $parentTemplate = EventTemplateProgramPoint::factory()->create(['name' => 'Wilno — zwiedzanie']);
        $childTemplate = EventTemplateProgramPoint::factory()->create(['name' => 'Stare Miasto']);
        $grandchildTemplate = EventTemplateProgramPoint::factory()->create(['name' => 'Katedra']);

        $template->programPoints()->attach($parentTemplate->id, [
            'day' => 1,
            'order' => 1,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        DB::table('event_template_program_point_parent')->insert([
            ['parent_id' => $parentTemplate->id, 'child_id' => $childTemplate->id, 'order' => 1],
            ['parent_id' => $childTemplate->id, 'child_id' => $grandchildTemplate->id, 'order' => 1],
        ]);

        $event = Event::createFromTemplate($template, [
            'name' => 'Wycieczka Wilno',
            'client_name' => 'Klient',
            'start_date' => now()->format('Y-m-d'),
            'participant_count' => 20,
        ]);

        $this->assertDatabaseCount('event_program_points', 3);

        $parent = $event->programPoints()->whereNull('parent_id')->first();
        $this->assertNotNull($parent);
        $this->assertSame('Wilno — zwiedzanie', $parent->name);

        $child = $event->programPoints()->where('parent_id', $parent->id)->first();
        $this->assertNotNull($child);
        $this->assertSame('Stare Miasto', $child->name);

        $grandchild = $event->programPoints()->where('parent_id', $child->id)->first();
        $this->assertNotNull($grandchild);
        $this->assertSame('Katedra', $grandchild->name);
    }

    public function test_set_time_propagator_fills_empty_child_hours(): void
    {
        $event = Event::factory()->create();

        $parent = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Set',
            'day' => 1,
            'order' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $child = EventProgramPoint::create([
            'event_id' => $event->id,
            'parent_id' => $parent->id,
            'name' => 'Podpunkt',
            'day' => 1,
            'order' => 1,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $updated = app(ProgramPointSetTimePropagator::class)->propagateFromParent($parent->fresh('children'));

        $this->assertSame(1, $updated);
        $child->refresh();
        $this->assertSame('09:00', $child->start_time);
        $this->assertSame('12:00', $child->end_time);
    }

    public function test_set_time_propagator_splits_window_evenly_among_children(): void
    {
        $event = Event::factory()->create();

        $parent = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Wilno',
            'day' => 1,
            'order' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $childA = EventProgramPoint::create([
            'event_id' => $event->id,
            'parent_id' => $parent->id,
            'name' => 'Zamek',
            'day' => 1,
            'order' => 1,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $childB = EventProgramPoint::create([
            'event_id' => $event->id,
            'parent_id' => $parent->id,
            'name' => 'Stare miasto',
            'day' => 1,
            'order' => 2,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $updated = app(ProgramPointSetTimePropagator::class)->propagateFromParent($parent->fresh('children'));

        $this->assertSame(2, $updated);
        $childA->refresh();
        $childB->refresh();
        $this->assertSame('09:00', $childA->start_time);
        $this->assertSame('10:30', $childA->end_time);
        $this->assertSame('10:30', $childB->start_time);
        $this->assertSame('12:00', $childB->end_time);
    }

    public function test_assigned_hotel_contractor_ids_collects_stay_hotels(): void
    {
        $event = Event::factory()->create(['duration_days' => 2]);

        $hotelDay1 = Contractor::create(['name' => 'Hotel dzień 1', 'status' => 'active']);
        $hotelDay2 = Contractor::create(['name' => 'Hotel dzień 2', 'status' => 'active']);
        Contractor::create(['name' => 'Inny hotel', 'status' => 'active']);

        EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 1,
            'contractor_id' => $hotelDay1->id,
        ]);

        EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 2,
            'contractor_id' => $hotelDay2->id,
        ]);

        $ids = $event->fresh()->assignedHotelContractorIds()->sort()->values()->all();

        $this->assertSame([$hotelDay1->id, $hotelDay2->id], $ids);
    }

    public function test_merge_pricing_payload_preserves_per_piece_group_size(): void
    {
        $payload = EventProgramPointPricingFields::mergePricingIntoPayload(
            [
                'group_size' => 0,
                'quantity' => 8,
                'planned_price' => 640,
                'paid_price' => 0,
                'convert_to_pln' => true,
            ],
            80.0,
            45,
        );

        $this->assertSame(0, $payload['group_size']);
        $this->assertSame(8, $payload['quantity']);
        $this->assertSame(640.0, $payload['total_price']);
    }

    public function test_hotel_service_can_be_created_from_relation_manager(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $pln = Currency::create([
            'name' => 'Polski złoty',
            'symbol' => 'PLN',
            'code' => 'PLN',
            'exchange_rate' => 1,
        ]);

        $event = Event::factory()->create(['duration_days' => 2, 'participant_count' => 20]);
        $hotel = Contractor::create(['name' => 'Hotel planu', 'status' => 'active']);

        EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 1,
            'contractor_id' => $hotel->id,
        ]);

        Livewire::test(EventHotelServicesRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => EventHotelPlanning::class,
        ])
            ->callTableAction('create', data: [
                'name' => 'Śniadanie',
                'day' => 1,
                'contractor_id' => $hotel->id,
                'unit_price' => 25,
                'group_size' => 1,
                'quantity' => 20,
                'currency_id' => $pln->id,
                'convert_to_pln' => false,
                'include_in_program' => true,
                'planned_price' => 500,
                'paid_price' => 0,
                'calculated_price' => 500,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('event_program_points', [
            'event_id' => $event->id,
            'name' => 'Śniadanie',
            'contractor_id' => $hotel->id,
            'is_hotel_service' => true,
            'group_size' => 1,
        ]);
    }
}
