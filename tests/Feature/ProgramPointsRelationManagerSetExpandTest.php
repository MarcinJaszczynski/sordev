<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\EditEventProgram;
use App\Filament\Resources\EventResource\RelationManagers\ProgramPointsRelationManager;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProgramPointsRelationManagerSetExpandTest extends TestCase
{
    use RefreshDatabase;

    public function test_sets_are_expanded_by_default_in_table_records(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['duration_days' => 3]);

        $parent = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Set rodzic',
            'include_in_program' => true,
            'active' => true,
        ]);

        $child = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'parent_id' => $parent->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Podpunkt A',
            'include_in_program' => true,
            'active' => true,
        ]);

        $component = Livewire::actingAs($user)
            ->test(ProgramPointsRelationManager::class, [
                'ownerRecord' => $event,
                'pageClass' => EditEventProgram::class,
                'ownerProgramView' => 'days',
                'ownerProgramDay' => 1,
                'ownerProgramFilter' => 'all',
            ]);

        $expandedIds = $component->instance()->getTableRecords()->pluck('id')->all();

        $this->assertContains($parent->id, $expandedIds);
        $this->assertContains($child->id, $expandedIds);
    }

    public function test_toggle_set_expanded_collapses_children_in_table_records(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['duration_days' => 3]);

        $parent = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Set rodzic',
            'include_in_program' => true,
            'active' => true,
        ]);

        EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'parent_id' => $parent->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Podpunkt A',
            'include_in_program' => true,
            'active' => true,
        ]);

        $component = Livewire::actingAs($user)
            ->test(ProgramPointsRelationManager::class, [
                'ownerRecord' => $event,
                'pageClass' => EditEventProgram::class,
                'ownerProgramView' => 'days',
                'ownerProgramDay' => 1,
                'ownerProgramFilter' => 'all',
            ]);

        $component->call('toggleSetExpanded', $parent->id);

        $this->assertSame(
            [$parent->id],
            $component->instance()->getTableRecords()->pluck('id')->all(),
        );
    }

    public function test_default_scope_shows_all_active_points_except_inactive(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['duration_days' => 1]);

        $inProgram = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'W programie',
            'include_in_program' => true,
            'active' => true,
        ]);

        $outsideProgram = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 2,
            'name' => 'Poza programem',
            'include_in_program' => false,
            'active' => true,
        ]);

        EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 3,
            'name' => 'Nieaktywny',
            'include_in_program' => true,
            'active' => false,
        ]);

        $ids = Livewire::actingAs($user)
            ->test(ProgramPointsRelationManager::class, [
                'ownerRecord' => $event,
                'pageClass' => EditEventProgram::class,
                'ownerProgramView' => 'days',
                'ownerProgramDay' => 1,
                'ownerProgramFilter' => 'all',
            ])
            ->instance()
            ->getTableRecords()
            ->pluck('id')
            ->all();

        $this->assertEqualsCanonicalizing([$inProgram->id, $outsideProgram->id], $ids);
    }
}
