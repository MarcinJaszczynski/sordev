<?php

namespace Tests\Feature;

use App\Livewire\EventProgramPlanner;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventTemplate;
use App\Models\User;
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
            ->assertSet('showDeleteModal', false);

        $component = Livewire::test(EventProgramPlanner::class, ['eventId' => $event->id]);
        $events = $component->instance()->render()->getData()['plannerData']['events'];

        $this->assertNotEmpty($events);
        $this->assertStringContainsString('Własny punkt pilota', $events[0]['title'] ?? '');
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
            ->call('openDeleteModal', $point->id)
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
}
