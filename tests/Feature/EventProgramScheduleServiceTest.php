<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventTemplate;
use App\Models\EventTemplateProgramPoint;
use App\Models\Place;
use App\Models\User;
use App\Services\EventProgramScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EventProgramScheduleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_chains_root_points_from_duration_starting_at_eight(): void
    {
        $event = Event::factory()->create(['duration_days' => 1]);

        $first = $this->makeRootPoint($event, [
            'name' => 'Zbiórka',
            'order' => 1,
            'duration_hours' => 1,
            'duration_minutes' => 0,
        ]);

        $second = $this->makeRootPoint($event, [
            'name' => 'Zwiedzanie',
            'order' => 2,
            'duration_hours' => 2,
            'duration_minutes' => 0,
        ]);

        $updated = app(EventProgramScheduleService::class)->bootstrapFromTemplate($event);

        $this->assertGreaterThan(0, $updated);

        $first->refresh();
        $second->refresh();

        $this->assertSame('08:00:00', substr((string) $first->start_time, 0, 8));
        $this->assertSame('09:00:00', substr((string) $first->end_time, 0, 8));
        $this->assertSame('09:00:00', substr((string) $second->start_time, 0, 8));
        $this->assertSame('11:00:00', substr((string) $second->end_time, 0, 8));
        $this->assertFalse((bool) $first->times_manually_locked);
    }

    public function test_bootstrap_preserves_template_pivot_start_and_end_times(): void
    {
        $place = Place::create(['name' => 'Warszawa']);
        $template = EventTemplate::factory()->create([
            'duration_days' => 1,
            'start_place_id' => $place->id,
        ]);

        $templatePoint = EventTemplateProgramPoint::factory()->create([
            'name' => 'Koncert',
            'duration_hours' => 1,
            'duration_minutes' => 0,
        ]);

        $template->programPoints()->attach($templatePoint->id, [
            'day' => 1,
            'order' => 1,
            'start_time' => '10:30:00',
            'end_time' => '12:00:00',
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $event = Event::createFromTemplate($template, [
            'name' => 'Impreza testowa',
            'client_name' => 'Klient',
            'start_date' => now()->format('Y-m-d'),
            'participant_count' => 20,
        ]);

        $point = $event->programPoints()->whereNull('parent_id')->first();

        $this->assertNotNull($point);
        $this->assertSame('10:30:00', substr((string) $point->start_time, 0, 8));
        $this->assertSame('12:00:00', substr((string) $point->end_time, 0, 8));
    }

    public function test_manual_edit_of_middle_point_shifts_following_unlocked_points(): void
    {
        $event = Event::factory()->create(['duration_days' => 1]);
        $service = app(EventProgramScheduleService::class);

        $first = $this->makeRootPoint($event, [
            'order' => 1,
            'duration_hours' => 1,
            'duration_minutes' => 0,
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
        ]);

        $second = $this->makeRootPoint($event, [
            'order' => 2,
            'duration_hours' => 1,
            'duration_minutes' => 0,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ]);

        $third = $this->makeRootPoint($event, [
            'order' => 3,
            'duration_hours' => 1,
            'duration_minutes' => 0,
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
        ]);

        $service->applyManualTimeChange($second, '09:00', '10:30');

        $first->refresh();
        $second->refresh();
        $third->refresh();

        $this->assertSame('08:00:00', substr((string) $first->start_time, 0, 8));
        $this->assertSame('09:00:00', substr((string) $first->end_time, 0, 8));
        $this->assertSame('09:00:00', substr((string) $second->start_time, 0, 8));
        $this->assertSame('10:30:00', substr((string) $second->end_time, 0, 8));
        $this->assertTrue((bool) $second->times_manually_locked);
        $this->assertSame('10:30:00', substr((string) $third->start_time, 0, 8));
        $this->assertSame('11:30:00', substr((string) $third->end_time, 0, 8));
    }

    public function test_manual_edit_skips_locked_following_point_and_continues_after_it(): void
    {
        $event = Event::factory()->create(['duration_days' => 1]);
        $service = app(EventProgramScheduleService::class);

        $first = $this->makeRootPoint($event, [
            'order' => 1,
            'duration_hours' => 1,
            'duration_minutes' => 0,
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
        ]);

        $second = $this->makeRootPoint($event, [
            'order' => 2,
            'duration_hours' => 1,
            'duration_minutes' => 0,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ]);

        $locked = $this->makeRootPoint($event, [
            'order' => 3,
            'duration_hours' => 1,
            'duration_minutes' => 0,
            'start_time' => '11:00:00',
            'end_time' => '12:00:00',
            'times_manually_locked' => true,
        ]);

        $fourth = $this->makeRootPoint($event, [
            'order' => 4,
            'duration_hours' => 1,
            'duration_minutes' => 0,
            'start_time' => '12:00:00',
            'end_time' => '13:00:00',
        ]);

        $service->applyManualTimeChange($first, '08:00', '09:30');

        $second->refresh();
        $locked->refresh();
        $fourth->refresh();

        $this->assertSame('09:30:00', substr((string) $second->start_time, 0, 8));
        $this->assertSame('10:30:00', substr((string) $second->end_time, 0, 8));
        $this->assertSame('11:00:00', substr((string) $locked->start_time, 0, 8));
        $this->assertSame('12:00:00', substr((string) $locked->end_time, 0, 8));
        $this->assertSame('12:00:00', substr((string) $fourth->start_time, 0, 8));
        $this->assertSame('13:00:00', substr((string) $fourth->end_time, 0, 8));
    }

    public function test_manual_edit_of_set_parent_propagates_times_to_children(): void
    {
        $event = Event::factory()->create(['duration_days' => 1]);
        $service = app(EventProgramScheduleService::class);

        $parent = $this->makeRootPoint($event, [
            'name' => 'Set',
            'order' => 1,
            'duration_hours' => 3,
            'duration_minutes' => 0,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
        ]);

        $childA = EventProgramPoint::create([
            'event_id' => $event->id,
            'parent_id' => $parent->id,
            'name' => 'Część A',
            'day' => 1,
            'order' => 1,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $childB = EventProgramPoint::create([
            'event_id' => $event->id,
            'parent_id' => $parent->id,
            'name' => 'Część B',
            'day' => 1,
            'order' => 2,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $service->applyManualTimeChange($parent, '10:00', '13:00');

        $childA->refresh();
        $childB->refresh();

        $this->assertSame('10:00', substr((string) $childA->start_time, 0, 5));
        $this->assertSame('11:30', substr((string) $childA->end_time, 0, 5));
        $this->assertSame('11:30', substr((string) $childB->start_time, 0, 5));
        $this->assertSame('13:00', substr((string) $childB->end_time, 0, 5));
    }

    public function test_relayout_only_unlocked_respects_manual_anchors(): void
    {
        $event = Event::factory()->create(['duration_days' => 1]);
        $service = app(EventProgramScheduleService::class);

        $this->makeRootPoint($event, [
            'order' => 1,
            'duration_hours' => 1,
            'duration_minutes' => 0,
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'times_manually_locked' => true,
        ]);

        $second = $this->makeRootPoint($event, [
            'order' => 2,
            'duration_hours' => 2,
            'duration_minutes' => 0,
            'start_time' => '12:00:00',
            'end_time' => '14:00:00',
        ]);

        $service->bootstrapFromTemplate($event, onlyUnlocked: true);

        $second->refresh();

        $this->assertSame('09:00:00', substr((string) $second->start_time, 0, 8));
        $this->assertSame('11:00:00', substr((string) $second->end_time, 0, 8));
    }

    public function test_bootstrap_uses_per_day_program_start_times(): void
    {
        if (! Schema::hasColumn('events', 'program_day_start_times')) {
            $this->markTestSkipped('Kolumna program_day_start_times nie istnieje.');
        }

        $event = Event::factory()->create([
            'duration_days' => 2,
            'program_day_start_times' => [
                '1' => '08:30',
                '2' => '07:30',
            ],
        ]);

        $dayOne = $this->makeRootPoint($event, [
            'name' => 'Śniadanie',
            'day' => 1,
            'order' => 1,
            'duration_hours' => 1,
            'duration_minutes' => 0,
        ]);

        $dayTwo = $this->makeRootPoint($event, [
            'name' => 'Śniadanie',
            'day' => 2,
            'order' => 1,
            'duration_hours' => 1,
            'duration_minutes' => 0,
        ]);

        app(EventProgramScheduleService::class)->bootstrapFromTemplate($event->fresh());

        $dayOne->refresh();
        $dayTwo->refresh();

        $this->assertSame('08:30:00', substr((string) $dayOne->start_time, 0, 8));
        $this->assertSame('07:30:00', substr((string) $dayTwo->start_time, 0, 8));
    }

    public function test_relayout_day_applies_updated_day_start(): void
    {
        if (! Schema::hasColumn('events', 'program_day_start_times')) {
            $this->markTestSkipped('Kolumna program_day_start_times nie istnieje.');
        }

        $event = Event::factory()->create(['duration_days' => 1]);

        $point = $this->makeRootPoint($event, [
            'duration_hours' => 1,
            'duration_minutes' => 0,
        ]);

        app(EventProgramScheduleService::class)->bootstrapFromTemplate($event->fresh());
        $point->refresh();
        $this->assertSame('08:00:00', substr((string) $point->start_time, 0, 8));

        $event->setProgramDayStartTime(1, '09:15');
        $event->save();

        app(EventProgramScheduleService::class)->relayoutDay($event->fresh(), 1, dayStart: '09:15');

        $point->refresh();
        $this->assertSame('09:15:00', substr((string) $point->start_time, 0, 8));
    }

    public function test_relayout_day_includes_timed_points_outside_program(): void
    {
        if (! Schema::hasColumn('events', 'program_day_start_times')) {
            $this->markTestSkipped('Kolumna program_day_start_times nie istnieje.');
        }

        $event = Event::factory()->create(['duration_days' => 1]);

        $this->makeRootPoint($event, [
            'name' => 'Zbiórka',
            'order' => 1,
            'duration_hours' => 1,
            'duration_minutes' => 0,
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
        ]);

        $this->makeRootPoint($event, [
            'name' => 'Przejazd',
            'order' => 2,
            'duration_hours' => 1,
            'duration_minutes' => 0,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ]);

        $calcPoint = $this->makeRootPoint($event, [
            'name' => 'Zwiedzanie kalkulacja',
            'order' => 3,
            'duration_hours' => 1,
            'duration_minutes' => 0,
            'include_in_program' => false,
            'start_time' => '10:20:00',
            'end_time' => '11:20:00',
        ]);

        $this->makeRootPoint($event, [
            'name' => 'Zwiedzanie',
            'order' => 4,
            'duration_hours' => 1,
            'duration_minutes' => 0,
            'start_time' => '11:00:00',
            'end_time' => '12:00:00',
        ]);

        app(EventProgramScheduleService::class)->relayoutDay(
            $event->fresh(),
            1,
            dayStart: '09:00',
            includeTimedNonProgramPoints: true,
        );

        $calcPoint->refresh();

        $this->assertSame('11:00:00', substr((string) $calcPoint->start_time, 0, 8));
        $this->assertSame('12:00:00', substr((string) $calcPoint->end_time, 0, 8));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeRootPoint(Event $event, array $overrides = []): EventProgramPoint
    {
        if (! Schema::hasColumn('event_program_points', 'times_manually_locked')) {
            unset($overrides['times_manually_locked']);
        }

        return EventProgramPoint::create(array_merge([
            'event_id' => $event->id,
            'name' => 'Punkt',
            'day' => 1,
            'order' => 1,
            'duration_hours' => 1,
            'duration_minutes' => 0,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
            'convert_to_pln' => false,
        ], $overrides));
    }
}
