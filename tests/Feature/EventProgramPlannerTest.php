<?php

namespace Tests\Feature;

use App\Livewire\EventProgramPlanner;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventTemplate;
use App\Models\User;
use App\Services\EventProgramPointOrderService;
use App\Services\EventProgramScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventProgramPlannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_planner_excludes_points_not_included_in_program(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $event = Event::factory()->create([
            'duration_days' => 1,
            'start_date' => now()->format('Y-m-d'),
        ]);

        EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'W programie',
            'day' => 1,
            'order' => 1,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'include_in_program' => true,
            'include_in_calculation' => false,
            'active' => true,
            'event_template_program_point_id' => null,
        ]);

        EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Poza programem',
            'day' => 1,
            'order' => 2,
            'start_time' => '12:00',
            'end_time' => '13:00',
            'include_in_program' => false,
            'include_in_calculation' => true,
            'active' => true,
            'event_template_program_point_id' => null,
        ]);

        $component = Livewire::test(EventProgramPlanner::class, ['eventId' => $event->id]);
        $events = $component->instance()->render()->getData()['plannerData']['events'];
        $titles = collect($events)->pluck('title')->implode(' ');

        $this->assertStringContainsString('W programie', $titles);
        $this->assertStringNotContainsString('Poza programem', $titles);
    }

    public function test_planner_builds_events_for_custom_point_without_template(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $event = Event::factory()->create([
            'duration_days' => 2,
            'start_date' => now()->format('Y-m-d'),
        ]);

        EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Własny punkt pilota',
            'day' => 1,
            'order' => 1,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'include_in_program' => true,
            'include_in_calculation' => false,
            'active' => true,
            'event_template_program_point_id' => null,
        ]);

        Livewire::test(EventProgramPlanner::class, ['eventId' => $event->id])
            ->assertOk()
            ->assertSet('showEditModal', false)
            ->assertSet('showDeleteModal', false);

        $component = Livewire::test(EventProgramPlanner::class, ['eventId' => $event->id]);
        $events = $component->instance()->render()->getData()['plannerData']['events'];

        $this->assertNotEmpty($events);
        $this->assertStringContainsString('Własny punkt pilota', $events[0]['title'] ?? '');
    }

    public function test_planner_can_edit_point_times_manually(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $event = Event::factory()->create([
            'duration_days' => 1,
            'start_date' => now()->format('Y-m-d'),
        ]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Do edycji godzin',
            'day' => 1,
            'order' => 1,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'include_in_program' => true,
            'include_in_calculation' => false,
            'active' => true,
            'event_template_program_point_id' => null,
        ]);

        Livewire::test(EventProgramPlanner::class, ['eventId' => $event->id])
            ->call('openEditModal', $point->id)
            ->assertSet('showEditModal', true)
            ->assertSet('editingPointId', $point->id)
            ->assertSet('editingData.start_time', '10:00')
            ->assertSet('editingData.end_time', '11:00')
            ->set('editingData.start_time', '14:30')
            ->set('editingData.end_time', '16:15')
            ->call('saveEditingPoint')
            ->assertSet('showEditModal', false)
            ->assertHasNoErrors();

        $point->refresh();

        $this->assertSame('14:30', substr((string) $point->start_time, 0, 5));
        $this->assertSame('16:15', substr((string) $point->end_time, 0, 5));
    }

    public function test_planner_can_remove_point_from_program(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $event = Event::factory()->create([
            'duration_days' => 1,
            'start_date' => now()->format('Y-m-d'),
        ]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Do usunięcia',
            'day' => 1,
            'order' => 1,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'include_in_program' => true,
            'include_in_calculation' => false,
            'active' => true,
            'event_template_program_point_id' => null,
        ]);

        Livewire::test(EventProgramPlanner::class, ['eventId' => $event->id])
            ->call('openEditModal', $point->id)
            ->assertSet('showEditModal', true)
            ->call('requestRemoveEditingPoint')
            ->assertSet('showEditModal', false)
            ->assertSet('showDeleteModal', true)
            ->assertSet('deletingPointId', $point->id)
            ->call('confirmRemovePoint')
            ->assertSet('showDeleteModal', false);

        $this->assertFalse((bool) $point->fresh()->include_in_program);
    }

    public function test_planner_uses_template_duration_when_event_duration_is_stale(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $template = EventTemplate::factory()->create(['duration_days' => 5]);
        $event = Event::factory()->create([
            'event_template_id' => $template->id,
            'duration_days' => 1,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-10',
        ]);

        $component = Livewire::test(EventProgramPlanner::class, ['eventId' => $event->id]);
        $plannerData = $component->instance()->render()->getData()['plannerData'];

        $this->assertSame(5, $plannerData['durationDays']);
    }

    public function test_planner_time_change_is_visible_in_program_list_order_and_hours(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $event = Event::factory()->create([
            'duration_days' => 1,
            'start_date' => '2026-09-10',
        ]);

        $breakfast = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Śniadanie',
            'day' => 1,
            'order' => 1,
            'start_time' => '08:00',
            'end_time' => '09:00',
            'include_in_program' => true,
            'include_in_calculation' => false,
            'active' => true,
            'event_template_program_point_id' => null,
        ]);

        $museum = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Muzeum',
            'day' => 1,
            'order' => 2,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'include_in_program' => true,
            'include_in_calculation' => false,
            'active' => true,
            'event_template_program_point_id' => null,
        ]);

        Livewire::test(EventProgramPlanner::class, ['eventId' => $event->id])
            ->call('updatePointSchedule', $museum->id, '2026-09-10T07:00:00', '2026-09-10T07:45:00')
            ->assertHasNoErrors();

        $breakfast->refresh();
        $museum->refresh();

        $this->assertSame('07:00', substr((string) $museum->start_time, 0, 5));
        $this->assertSame('07:45', substr((string) $museum->end_time, 0, 5));
        $this->assertSame(1, (int) $museum->day);

        $programList = app(EventProgramPointOrderService::class)
            ->sortedForDisplay($event)
            ->whereNull('parent_id')
            ->values();

        $this->assertSame(
            [$museum->id, $breakfast->id],
            $programList->pluck('id')->all(),
            'Zakładka Dzień/Lista sortuje po godzinie startu — po przesunięciu w planerze Muzeum ma być pierwsze.'
        );
        $this->assertSame('07:00', substr((string) $programList[0]->start_time, 0, 5));
        $this->assertLessThan((int) $breakfast->order, (int) $museum->order);
    }

    public function test_planner_day_move_is_visible_on_the_target_program_day(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $event = Event::factory()->create([
            'duration_days' => 2,
            'start_date' => '2026-09-10',
        ]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Przejazd',
            'day' => 1,
            'order' => 1,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'include_in_program' => true,
            'include_in_calculation' => false,
            'active' => true,
            'event_template_program_point_id' => null,
        ]);

        Livewire::test(EventProgramPlanner::class, ['eventId' => $event->id])
            ->call('updatePointSchedule', $point->id, '2026-09-11T15:00:00', '2026-09-11T16:30:00')
            ->assertHasNoErrors();

        $point->refresh();

        $this->assertSame(2, (int) $point->day);
        $this->assertSame('15:00', substr((string) $point->start_time, 0, 5));
        $this->assertSame('16:30', substr((string) $point->end_time, 0, 5));
    }

    public function test_program_list_time_change_is_visible_in_planner(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $event = Event::factory()->create([
            'duration_days' => 1,
            'start_date' => '2026-09-10',
        ]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Obiad',
            'day' => 1,
            'order' => 1,
            'start_time' => '12:00',
            'end_time' => '13:00',
            'include_in_program' => true,
            'include_in_calculation' => false,
            'active' => true,
            'event_template_program_point_id' => null,
        ]);

        app(EventProgramScheduleService::class)->applyManualTimeChange($point, '18:00', '19:30');

        $component = Livewire::test(EventProgramPlanner::class, ['eventId' => $event->id]);
        $events = $component->instance()->render()->getData()['plannerData']['events'];

        $this->assertNotEmpty($events);
        $this->assertStringContainsString('Obiad', $events[0]['title'] ?? '');
        $this->assertStringContainsString('T18:00', $events[0]['start'] ?? '');
        $this->assertStringContainsString('T19:30', $events[0]['end'] ?? '');
    }
}
