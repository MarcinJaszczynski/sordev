<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\EventProgramTreeEditor;
use App\Models\EventTemplate;
use App\Models\EventTemplateProgramPoint;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Usuwanie pivota programu szablonu bez PRAGMA foreign_keys (MySQL-safe).
 */
class EventProgramTreeEditorDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_point_removes_pivot_and_reorders_without_disabling_fk(): void
    {
        $user = User::factory()->create();
        $place = Place::factory()->starting()->create();
        $template = EventTemplate::factory()->create([
            'start_place_id' => $place->id,
            'duration_days' => 2,
            'is_active' => true,
        ]);

        $a = EventTemplateProgramPoint::factory()->create(['name' => 'Punkt A']);
        $b = EventTemplateProgramPoint::factory()->create(['name' => 'Punkt B']);
        $c = EventTemplateProgramPoint::factory()->create(['name' => 'Punkt C']);

        $template->programPoints()->attach([
            $a->id => [
                'day' => 1,
                'order' => 0,
                'notes' => null,
                'include_in_program' => true,
                'include_in_calculation' => true,
                'active' => true,
            ],
            $b->id => [
                'day' => 1,
                'order' => 1,
                'notes' => null,
                'include_in_program' => true,
                'include_in_calculation' => true,
                'active' => true,
            ],
            $c->id => [
                'day' => 1,
                'order' => 2,
                'notes' => null,
                'include_in_program' => true,
                'include_in_calculation' => true,
                'active' => true,
            ],
        ]);

        $middlePivotId = (int) DB::table('event_template_event_template_program_point')
            ->where('event_template_id', $template->id)
            ->where('event_template_program_point_id', $b->id)
            ->value('id');

        $this->assertGreaterThan(0, $middlePivotId);

        // FK muszą zostać włączone — deletePoint nie może ich wyłączać.
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared('PRAGMA foreign_keys = ON');
        }

        Livewire::actingAs($user)
            ->test(EventProgramTreeEditor::class, ['eventTemplate' => $template])
            ->call('deletePoint', $middlePivotId);

        $remaining = DB::table('event_template_event_template_program_point')
            ->where('event_template_id', $template->id)
            ->orderBy('order')
            ->get();

        $this->assertCount(2, $remaining);
        $this->assertSame([$a->id, $c->id], $remaining->pluck('event_template_program_point_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([0, 1], $remaining->pluck('order')->map(fn ($o) => (int) $o)->all());

        // Katalogowe punkty programu nie są kasowane — tylko pivot.
        $this->assertDatabaseHas('event_template_program_points', ['id' => $b->id]);
    }

    public function test_delete_point_missing_pivot_does_not_throw(): void
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->create(['duration_days' => 1]);

        Livewire::actingAs($user)
            ->test(EventProgramTreeEditor::class, ['eventTemplate' => $template])
            ->call('deletePoint', 999999)
            ->assertDispatched('notify');
    }
}
