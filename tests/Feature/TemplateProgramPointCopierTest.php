<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventTemplate;
use App\Models\EventTemplateProgramPoint;
use App\Services\TemplateProgramPointCopier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TemplateProgramPointCopierTest extends TestCase
{
    use RefreshDatabase;

    public function test_copy_creates_separate_points_for_same_template_id_on_multiple_days(): void
    {
        $template = EventTemplate::factory()->create(['duration_days' => 3]);
        $transport = EventTemplateProgramPoint::factory()->create(['name' => 'Przejazd autokarem']);

        foreach ([1, 2, 3] as $day) {
            $template->programPoints()->attach($transport->id, [
                'day' => $day,
                'order' => 1,
                'include_in_program' => true,
                'include_in_calculation' => true,
                'active' => true,
            ]);
        }

        $event = Event::factory()->create([
            'event_template_id' => $template->id,
            'duration_days' => 3,
            'participant_count' => 20,
        ]);

        app(TemplateProgramPointCopier::class)->copyToEvent($event);

        $copies = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->where('event_template_program_point_id', $transport->id)
            ->whereNull('parent_id')
            ->orderBy('day')
            ->get();

        $this->assertCount(3, $copies);
        $this->assertSame([1, 2, 3], $copies->pluck('day')->all());
    }

    public function test_copy_creates_multiple_slots_for_same_template_id_on_one_day(): void
    {
        $template = EventTemplate::factory()->create(['duration_days' => 1]);
        $transport = EventTemplateProgramPoint::factory()->create(['name' => 'Przejazd autokarem']);

        foreach ([3, 6, 9] as $order) {
            $template->programPoints()->attach($transport->id, [
                'day' => 1,
                'order' => $order,
                'include_in_program' => true,
                'include_in_calculation' => true,
                'active' => true,
            ]);
        }

        $event = Event::factory()->create([
            'event_template_id' => $template->id,
            'duration_days' => 1,
        ]);

        app(TemplateProgramPointCopier::class)->copyToEvent($event);

        $copies = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->where('event_template_program_point_id', $transport->id)
            ->whereNull('parent_id')
            ->orderBy('order')
            ->get();

        $this->assertCount(3, $copies);
        $this->assertSame([1, 2, 3], $copies->pluck('order')->all());
    }

    public function test_copy_still_includes_nested_set_children(): void
    {
        $template = EventTemplate::factory()->create(['duration_days' => 1]);
        $parentTemplate = EventTemplateProgramPoint::factory()->create(['name' => 'Muzeum']);
        $childTemplate = EventTemplateProgramPoint::factory()->create(['name' => 'Bilety']);

        $template->programPoints()->attach($parentTemplate->id, [
            'day' => 1,
            'order' => 1,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        DB::table('event_template_program_point_parent')->insert([
            'parent_id' => $parentTemplate->id,
            'child_id' => $childTemplate->id,
            'order' => 1,
        ]);

        $event = Event::factory()->create(['event_template_id' => $template->id]);

        app(TemplateProgramPointCopier::class)->copyToEvent($event);

        $this->assertDatabaseCount('event_program_points', 2);

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
        $this->assertSame('Bilety', $child->name);
    }

    public function test_fill_missing_adds_only_missing_slots_without_duplicating_existing(): void
    {
        $template = EventTemplate::factory()->create(['duration_days' => 3]);
        $pilot = EventTemplateProgramPoint::factory()->create(['name' => 'Pilot krajowe']);

        foreach ([1, 2, 3] as $day) {
            $template->programPoints()->attach($pilot->id, [
                'day' => $day,
                'order' => 0,
                'include_in_program' => true,
                'include_in_calculation' => true,
                'active' => true,
            ]);
        }

        $event = Event::factory()->create([
            'event_template_id' => $template->id,
            'duration_days' => 3,
        ]);

        EventProgramPoint::create([
            'event_id' => $event->id,
            'event_template_program_point_id' => $pilot->id,
            'name' => 'Pilot krajowe',
            'day' => 1,
            'order' => 1,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $filled = app(TemplateProgramPointCopier::class)->fillMissingFromTemplate($event);

        $this->assertSame(2, $filled);

        $copies = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->where('event_template_program_point_id', $pilot->id)
            ->whereNull('parent_id')
            ->orderBy('day')
            ->get();

        $this->assertCount(3, $copies);
        $this->assertSame([1, 2, 3], $copies->pluck('day')->all());
    }

    public function test_bulk_copy_does_not_log_history_for_each_point(): void
    {
        $template = EventTemplate::factory()->create(['duration_days' => 2]);
        $transport = EventTemplateProgramPoint::factory()->create(['name' => 'Przejazd autokarem']);

        foreach ([1, 2] as $day) {
            $template->programPoints()->attach($transport->id, [
                'day' => $day,
                'order' => 4,
                'include_in_program' => true,
                'include_in_calculation' => true,
                'active' => true,
            ]);
        }

        $event = Event::factory()->create([
            'event_template_id' => $template->id,
            'duration_days' => 2,
        ]);

        app(TemplateProgramPointCopier::class)->copyToEvent($event);

        $this->assertSame(0, $event->history()->where('action', 'program_added')->count());
        $this->assertCount(2, EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->where('event_template_program_point_id', $transport->id)
            ->whereNull('parent_id')
            ->get());
    }

    public function test_copy_program_points_from_template_syncs_once_after_bulk_copy(): void
    {
        $template = EventTemplate::factory()->create(['duration_days' => 1]);
        $point = EventTemplateProgramPoint::factory()->create(['name' => 'Zwiedzanie']);

        $template->programPoints()->attach($point->id, [
            'day' => 1,
            'order' => 1,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $event = Event::factory()->create([
            'event_template_id' => $template->id,
            'duration_days' => 1,
        ]);

        $event->copyProgramPointsFromTemplate();

        $this->assertSame(1, $event->history()->where('action', 'program_copied')->count());
        $this->assertSame(0, $event->history()->where('action', 'program_added')->count());
        $this->assertDatabaseHas('event_program_points', [
            'event_id' => $event->id,
            'event_template_program_point_id' => $point->id,
        ]);
    }
}
