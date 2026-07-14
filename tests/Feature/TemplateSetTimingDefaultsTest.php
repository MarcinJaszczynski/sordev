<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\EventProgramPoint;
use App\Services\ProgramPointSetTimePropagator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateSetTimingDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_defaults_build_wilno_style_three_by_forty_five_minute_window(): void
    {
        $template = EventTemplate::factory()->create([
            'set_default_child_count' => 3,
            'set_default_slot_minutes' => 45,
        ]);

        $event = Event::factory()->create([
            'event_template_id' => $template->id,
        ]);

        $parent = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Wilno',
            'day' => 1,
            'order' => 1,
            'start_time' => '09:00',
            'end_time' => null,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        foreach (['Zamek', 'Stare miasto', 'Katedra'] as $index => $name) {
            EventProgramPoint::create([
                'event_id' => $event->id,
                'parent_id' => $parent->id,
                'name' => $name,
                'day' => 1,
                'order' => $index + 1,
                'include_in_program' => true,
                'include_in_calculation' => true,
                'active' => true,
            ]);
        }

        app(ProgramPointSetTimePropagator::class)->propagateFromParent($parent->fresh(['children', 'event.eventTemplate']));

        $parent->refresh();
        $this->assertSame('11:15', substr((string) $parent->end_time, 0, 5));

        $times = $parent->children()->orderBy('order')->get()->map(fn (EventProgramPoint $child): string => substr((string) $child->start_time, 0, 5).'–'.substr((string) $child->end_time, 0, 5))->all();

        $this->assertSame(['09:00–09:45', '09:45–10:30', '10:30–11:15'], $times);
    }
}
