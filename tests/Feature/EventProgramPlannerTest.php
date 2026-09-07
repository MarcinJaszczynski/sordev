<?php

namespace Tests\Feature;

use App\Livewire\EventProgramPlanner;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
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

        $this->assertTrue($point->fresh()->trashed());
        $this->assertNull(
            EventProgramPoint::query()->whereKey($point->id)->first(),
            'Soft-deleted punkt nie powinien być widoczny w domyślnym query (jak na Liście).'
        );
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
        $this->assertSame('08:00', substr((string) $breakfast->start_time, 0, 5));
        $this->assertSame('09:00', substr((string) $breakfast->end_time, 0, 5));
    }

    public function test_planner_cascades_following_points_like_list(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $event = Event::factory()->create([
            'duration_days' => 1,
            'start_date' => '2026-09-10',
        ]);

        $first = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Zbiórka',
            'day' => 1,
            'order' => 1,
            'start_time' => '08:00',
            'end_time' => '09:00',
            'include_in_program' => true,
            'include_in_calculation' => false,
            'active' => true,
            'event_template_program_point_id' => null,
        ]);

        $second = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Zwiedzanie',
            'day' => 1,
            'order' => 2,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'include_in_program' => true,
            'include_in_calculation' => false,
            'active' => true,
            'event_template_program_point_id' => null,
        ]);

        Livewire::test(EventProgramPlanner::class, ['eventId' => $event->id])
            ->call('updatePointSchedule', $first->id, '2026-09-10T08:00:00', '2026-09-10T10:00:00')
            ->assertHasNoErrors();

        $first->refresh();
        $second->refresh();

        $this->assertSame('08:00', substr((string) $first->start_time, 0, 5));
        $this->assertSame('10:00', substr((string) $first->end_time, 0, 5));
        $this->assertSame('10:00', substr((string) $second->start_time, 0, 5), 'Planer jak Lista — kolejne punkty dnia przesuwają się za kotwicą.');
        $this->assertSame('12:00', substr((string) $second->end_time, 0, 5));

        $programList = app(EventProgramPointOrderService::class)
            ->sortedForDisplay($event)
            ->whereNull('parent_id')
            ->values();

        $this->assertSame('10:00', substr((string) $programList[1]->start_time, 0, 5));
        $this->assertSame('12:00', substr((string) $programList[1]->end_time, 0, 5));
    }

    public function test_list_cascade_is_visible_in_planner(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $event = Event::factory()->create([
            'duration_days' => 1,
            'start_date' => '2026-09-10',
        ]);

        $first = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Zbiórka',
            'day' => 1,
            'order' => 1,
            'start_time' => '08:00',
            'end_time' => '09:00',
            'include_in_program' => true,
            'include_in_calculation' => false,
            'active' => true,
            'event_template_program_point_id' => null,
        ]);

        $second = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Zwiedzanie',
            'day' => 1,
            'order' => 2,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'include_in_program' => true,
            'include_in_calculation' => false,
            'active' => true,
            'event_template_program_point_id' => null,
        ]);

        app(EventProgramScheduleService::class)->applyManualTimeChange($first, '08:00', '10:00');

        $component = Livewire::test(EventProgramPlanner::class, ['eventId' => $event->id]);
        $events = collect($component->instance()->render()->getData()['plannerData']['events']);

        $secondEvent = $events->first(
            fn (array $row): bool => (string) ($row['id'] ?? '') === (string) $second->id
        );

        $this->assertNotNull($secondEvent);
        $this->assertStringContainsString('T10:00', $secondEvent['start'] ?? '');
        $this->assertStringContainsString('T12:00', $secondEvent['end'] ?? '');
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

    public function test_planner_calendar_includes_unpaid_advance_due_date_before_trip(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $pln = Currency::factory()->pln()->create();
        $event = Event::factory()->create([
            'duration_days' => 2,
            'start_date' => '2026-09-01',
        ]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Bilety Bałtów',
            'day' => 1,
            'order' => 1,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
            'planned_price' => 2400,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
            'event_template_program_point_id' => null,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $settlement->upsertCostFromProgramPoint($point->fresh(['templatePoint', 'currency', 'event']));
        $plan = $settlement->costs()->where('source_type', 'program_point')->firstOrFail();
        $plan->forceFill([
            'advance_amount' => 2400,
            'advance_due_date' => '2026-08-24',
            'payment_status' => 'planned',
            'paid_by' => 'office',
        ])->saveQuietly();

        $component = Livewire::test(EventProgramPlanner::class, ['eventId' => $event->id]);
        $plannerData = $component->instance()->render()->getData()['plannerData'];
        $events = $plannerData['events'];

        $this->assertSame('2026-08-24', $plannerData['rangeStart']);
        $this->assertTrue(collect($events)->contains(
            fn (array $row): bool => ($row['id'] ?? null) === 'payment-due-cost-'.$plan->id
        ));
        $this->assertTrue(collect($events)->contains(
            fn (array $row): bool => str_contains((string) ($row['title'] ?? ''), 'Bilety Bałtów')
                && str_starts_with((string) ($row['start'] ?? ''), '2026-08-24')
        ));
    }

    public function test_planner_refresh_event_rebuilds_calendar_after_finance_change(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $event = Event::factory()->create([
            'duration_days' => 1,
            'start_date' => '2026-09-01',
        ]);

        EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Muzeum',
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
            ->call('refreshCalendarFromFinanceChange')
            ->assertOk();
    }

    public function test_planner_can_collapse_set_children(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $event = Event::factory()->create([
            'duration_days' => 1,
            'start_date' => now()->format('Y-m-d'),
        ]);

        $parent = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Set szkolny',
            'day' => 1,
            'order' => 1,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'include_in_program' => true,
            'include_in_calculation' => false,
            'active' => true,
        ]);

        $child = EventProgramPoint::create([
            'event_id' => $event->id,
            'parent_id' => $parent->id,
            'name' => 'Bilety',
            'day' => 1,
            'order' => 2,
            'start_time' => '10:15',
            'end_time' => '11:00',
            'include_in_program' => true,
            'include_in_calculation' => false,
            'active' => true,
        ]);

        $component = Livewire::test(EventProgramPlanner::class, ['eventId' => $event->id]);
        $events = collect($component->viewData('plannerData')['events'] ?? []);

        $this->assertTrue($events->contains(fn (array $event): bool => (string) ($event['id'] ?? '') === (string) $child->id));

        $component->call('toggleSetChildrenCollapsed');
        $eventsCollapsed = collect($component->viewData('plannerData')['events'] ?? []);

        $this->assertTrue($component->get('setChildrenCollapsed'));
        $this->assertFalse($eventsCollapsed->contains(fn (array $event): bool => (string) ($event['id'] ?? '') === (string) $child->id));
        $this->assertTrue($eventsCollapsed->contains(fn (array $event): bool => (string) ($event['id'] ?? '') === (string) $parent->id));
    }
}
