<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventTemplate;
use App\Models\EventTemplateProgramPoint;
use App\Models\User;
use App\Services\EventProgramPointOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventProgramPointOrderTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(array $overrides = []): Event
    {
        $template = EventTemplate::factory()->create();

        return Event::factory()->create(array_merge([
            'event_template_id' => $template->id,
        ], $overrides));
    }

    private function apiAs(User $user, string $method, string $uri, array $data = [])
    {
        return $this->actingAs($user, 'sanctum')
            ->json($method, '/api/v1'.$uri, $data, ['Accept' => 'application/json']);
    }

    public function test_sorted_for_display_orders_parents_then_children(): void
    {
        $event = $this->makeEvent();

        $parent = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 2,
            'name' => 'Muzeum',
        ]);

        $parentFirst = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Zbiórka',
        ]);

        $childB = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 2,
            'parent_id' => $parent->id,
            'name' => 'Sala B',
        ]);

        $childA = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'parent_id' => $parent->id,
            'name' => 'Sala A',
        ]);

        $dayTwo = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 2,
            'order' => 1,
            'name' => 'Powrót',
        ]);

        $sorted = app(EventProgramPointOrderService::class)->sortedForDisplay($event);

        $this->assertSame(
            [$parentFirst->id, $parent->id, $childA->id, $childB->id, $dayTwo->id],
            $sorted->pluck('id')->all()
        );
    }

    public function test_sorted_for_display_excludes_children_not_in_visible_subset(): void
    {
        $event = $this->makeEvent();
        $service = app(EventProgramPointOrderService::class);

        $parent = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'include_in_program' => true,
            'active' => true,
        ]);

        $visibleChild = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'parent_id' => $parent->id,
            'include_in_program' => true,
            'active' => true,
        ]);

        $hiddenChild = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 2,
            'parent_id' => $parent->id,
            'include_in_program' => false,
            'active' => true,
        ]);

        $subset = $service->loadPoints($event)->whereIn('id', [$parent->id, $visibleChild->id]);
        $sorted = $service->sortedForDisplay($event, $subset);

        $this->assertSame(
            [$parent->id, $visibleChild->id],
            $sorted->pluck('id')->all()
        );
        $this->assertFalse($sorted->pluck('id')->contains($hiddenChild->id));
    }

    public function test_sorted_for_display_orders_by_start_time_within_day(): void
    {
        $event = $this->makeEvent();
        $service = app(EventProgramPointOrderService::class);

        $morning = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 3,
            'start_time' => '10:20:00',
        ]);

        $afternoon = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'start_time' => '13:00:00',
        ]);

        $evening = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 2,
            'start_time' => '19:15:00',
        ]);

        $sorted = $service->sortedForDisplay($event);

        $this->assertSame(
            [$morning->id, $afternoon->id, $evening->id],
            $sorted->pluck('id')->all()
        );
    }

    public function test_repair_order_by_start_times_renumbers_points(): void
    {
        $event = $this->makeEvent();
        $service = app(EventProgramPointOrderService::class);

        $evening = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'start_time' => '19:00:00',
        ]);

        $noon = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 2,
            'start_time' => '12:00:00',
        ]);

        $service->repairOrderByStartTimes($event);

        $this->assertDatabaseHas('event_program_points', ['id' => $noon->id, 'order' => 1]);
        $this->assertDatabaseHas('event_program_points', ['id' => $evening->id, 'order' => 2]);
    }

    public function test_reorder_day_updates_parent_order_without_changing_child_day(): void
    {
        $event = $this->makeEvent();
        $service = app(EventProgramPointOrderService::class);

        $p1 = EventProgramPoint::factory()->create(['event_id' => $event->id, 'day' => 1, 'order' => 1, 'name' => 'A']);
        $p2 = EventProgramPoint::factory()->create(['event_id' => $event->id, 'day' => 1, 'order' => 2, 'name' => 'B']);
        $child = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'parent_id' => $p2->id,
            'name' => 'B-child',
        ]);

        $service->reorderDay($event, 1, [$p2->id, $p1->id, $child->id]);

        $this->assertDatabaseHas('event_program_points', ['id' => $p2->id, 'order' => 1, 'day' => 1]);
        $this->assertDatabaseHas('event_program_points', ['id' => $p1->id, 'order' => 2, 'day' => 1]);
        $this->assertDatabaseHas('event_program_points', ['id' => $child->id, 'day' => 1, 'parent_id' => $p2->id]);
    }

    public function test_repair_event_renumbers_parents_per_day(): void
    {
        $event = $this->makeEvent();

        $a = EventProgramPoint::factory()->create(['event_id' => $event->id, 'day' => 1, 'order' => 9]);
        $b = EventProgramPoint::factory()->create(['event_id' => $event->id, 'day' => 1, 'order' => 3]);
        $c = EventProgramPoint::factory()->create(['event_id' => $event->id, 'day' => 2, 'order' => 8]);

        app(EventProgramPointOrderService::class)->repairEvent($event);

        $this->assertDatabaseHas('event_program_points', ['id' => $b->id, 'order' => 1]);
        $this->assertDatabaseHas('event_program_points', ['id' => $a->id, 'order' => 2]);
        $this->assertDatabaseHas('event_program_points', ['id' => $c->id, 'order' => 1]);
    }

    public function test_copy_program_points_from_template_creates_parent_child_sets(): void
    {
        $template = EventTemplate::factory()->create(['duration_days' => 1]);

        $parentTemplate = EventTemplateProgramPoint::factory()->create(['name' => 'Zwiedzanie']);
        $childTemplate = EventTemplateProgramPoint::factory()->create(['name' => 'Sala 1']);

        $template->programPoints()->attach($parentTemplate->id, [
            'day' => 1,
            'order' => 1,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $parentTemplate->children()->attach($childTemplate->id, ['order' => 1]);

        $event = Event::factory()->create([
            'event_template_id' => $template->id,
            'duration_days' => 1,
        ]);

        $event->copyProgramPointsFromTemplate();

        $parent = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->whereNull('parent_id')
            ->first();

        $this->assertNotNull($parent);

        $child = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->where('parent_id', $parent->id)
            ->first();

        $this->assertNotNull($child);
        $this->assertSame(1, (int) $child->order);
    }

    public function test_api_reorder_uses_order_service(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $event = $this->makeEvent();

        $p1 = EventProgramPoint::factory()->create(['event_id' => $event->id, 'day' => 1, 'order' => 1]);
        $p2 = EventProgramPoint::factory()->create(['event_id' => $event->id, 'day' => 1, 'order' => 2]);
        $p3 = EventProgramPoint::factory()->create(['event_id' => $event->id, 'day' => 1, 'order' => 3]);

        $response = $this->apiAs($user, 'POST', '/events/'.$event->id.'/program-points/reorder', [
            'day' => 1,
            'point_ids' => [$p3->id, $p1->id, $p2->id],
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('event_program_points', ['id' => $p3->id, 'order' => 1]);
        $this->assertDatabaseHas('event_program_points', ['id' => $p1->id, 'order' => 2]);
        $this->assertDatabaseHas('event_program_points', ['id' => $p2->id, 'order' => 3]);
    }

    public function test_pilot_program_points_exclude_facultative_day(): void
    {
        $template = EventTemplate::factory()->create(['duration_days' => 5]);
        $event = Event::factory()->create([
            'event_template_id' => $template->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-05',
            'duration_days' => 5,
        ]);
        $service = app(EventProgramPointOrderService::class);

        EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 5,
            'order' => 1,
            'name' => 'Powrót',
            'include_in_program' => true,
            'active' => true,
        ]);

        EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 6,
            'order' => 1,
            'name' => 'Opcja fakultatywna',
            'include_in_program' => true,
            'active' => true,
        ]);

        $pilotPoints = $service->pilotProgramPoints($event);

        $this->assertCount(1, $pilotPoints);
        $this->assertSame('Powrót', $pilotPoints->first()->name);
    }

    public function test_pilot_program_points_use_template_duration_when_event_duration_is_stale(): void
    {
        $template = EventTemplate::factory()->create(['duration_days' => 4]);
        $event = Event::factory()->create([
            'event_template_id' => $template->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-01',
            'duration_days' => 1,
        ]);
        $service = app(EventProgramPointOrderService::class);

        EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 3,
            'order' => 1,
            'name' => 'Dzień 3',
            'include_in_program' => true,
            'active' => true,
        ]);

        EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 5,
            'order' => 1,
            'name' => 'Fakultatywny',
            'include_in_program' => true,
            'active' => true,
        ]);

        $pilotPoints = $service->pilotProgramPoints($event);

        $this->assertCount(1, $pilotPoints);
        $this->assertSame('Dzień 3', $pilotPoints->first()->name);
    }
}
