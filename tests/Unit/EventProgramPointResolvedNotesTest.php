<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventTemplateProgramPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventProgramPointResolvedNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolved_notes_fall_back_to_template(): void
    {
        $event = Event::factory()->create();

        $template = EventTemplateProgramPoint::factory()->create([
            'office_notes' => '<p>Uwaga biura z szablonu</p>',
            'pilot_notes' => '<p>Uwaga pilota z szablonu</p>',
        ]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'event_template_program_point_id' => $template->id,
            'name' => 'Punkt testowy',
            'day' => 1,
            'order' => 1,
            'office_notes' => null,
            'pilot_notes' => null,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $point->load('templatePoint');

        $this->assertTrue($point->hasResolvedOfficeNotes());
        $this->assertTrue($point->hasResolvedPilotNotes());
        $this->assertStringContainsString('Uwaga biura', (string) $point->resolvedOfficeNotes());
        $this->assertStringContainsString('Uwaga pilota', (string) $point->resolvedPilotNotes());
    }

    public function test_event_override_takes_precedence_over_template_notes(): void
    {
        $event = Event::factory()->create();

        $template = EventTemplateProgramPoint::factory()->create([
            'pilot_notes' => '<p>Szablon</p>',
        ]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'event_template_program_point_id' => $template->id,
            'name' => 'Punkt testowy',
            'day' => 1,
            'order' => 1,
            'pilot_notes' => '<p>Impreza</p>',
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $point->load('templatePoint');

        $this->assertStringContainsString('Impreza', (string) $point->resolvedPilotNotes());
    }

    public function test_cleared_description_does_not_fall_back_to_template(): void
    {
        $event = Event::factory()->create();

        $template = EventTemplateProgramPoint::factory()->create([
            'description' => '<p>Opis z szablonu</p>',
        ]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'event_template_program_point_id' => $template->id,
            'name' => 'Punkt setu',
            'description' => '',
            'day' => 1,
            'order' => 1,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $point->load('templatePoint');

        $this->assertNull($point->resolvedDescription());
    }

    public function test_null_description_falls_back_to_template(): void
    {
        $event = Event::factory()->create();

        $template = EventTemplateProgramPoint::factory()->create([
            'description' => '<p>Opis z szablonu</p>',
        ]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'event_template_program_point_id' => $template->id,
            'name' => 'Punkt setu',
            'description' => null,
            'day' => 1,
            'order' => 1,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $point->load('templatePoint');

        $this->assertStringContainsString('Opis z szablonu', (string) $point->resolvedDescription());
    }

    public function test_cleared_notes_do_not_fall_back_to_template(): void
    {
        $event = Event::factory()->create();

        $template = EventTemplateProgramPoint::factory()->create([
            'office_notes' => '<p>Biuro szablon</p>',
            'pilot_notes' => '<p>Pilot szablon</p>',
        ]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'event_template_program_point_id' => $template->id,
            'name' => 'Punkt setu',
            'day' => 1,
            'order' => 1,
            'office_notes' => '',
            'pilot_notes' => '<p></p>',
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $point->load('templatePoint');

        $this->assertNull($point->resolvedOfficeNotes());
        $this->assertNull($point->resolvedPilotNotes());
        $this->assertFalse($point->hasResolvedOfficeNotes());
        $this->assertFalse($point->hasResolvedPilotNotes());
    }
}
