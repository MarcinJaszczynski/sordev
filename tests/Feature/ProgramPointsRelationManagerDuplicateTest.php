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

class ProgramPointsRelationManagerDuplicateTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_action_clones_set_with_children(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['duration_days' => 3]);

        $set = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Przejazd autokarem',
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'parent_id' => $set->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Kierowca',
            'include_in_program' => true,
            'active' => true,
        ]);

        EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'parent_id' => $set->id,
            'day' => 1,
            'order' => 2,
            'name' => 'Paliwo',
            'include_in_program' => true,
            'active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(ProgramPointsRelationManager::class, [
                'ownerRecord' => $event,
                'pageClass' => EditEventProgram::class,
                'ownerProgramView' => 'days',
                'ownerProgramDay' => 1,
                'ownerProgramFilter' => 'all',
            ])
            ->callTableAction('duplicate', $set);

        $clone = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->whereNull('parent_id')
            ->where('name', 'Przejazd autokarem (kopia)')
            ->first();

        $this->assertNotNull($clone);
        $this->assertSame(2, (int) $clone->order);
        $this->assertTrue((bool) $clone->include_in_program);
        $this->assertTrue((bool) $clone->include_in_calculation);

        $childNames = EventProgramPoint::query()
            ->where('parent_id', $clone->id)
            ->orderBy('order')
            ->pluck('name')
            ->all();

        $this->assertSame(['Kierowca', 'Paliwo'], $childNames);
    }

    public function test_duplicate_action_clones_single_point_without_children_flag_for_child(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['duration_days' => 2]);

        $set = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Set',
            'include_in_program' => true,
            'active' => true,
        ]);

        $child = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'parent_id' => $set->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Podpunkt',
            'include_in_program' => true,
            'active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(ProgramPointsRelationManager::class, [
                'ownerRecord' => $event,
                'pageClass' => EditEventProgram::class,
                'ownerProgramView' => 'days',
                'ownerProgramDay' => 1,
                'ownerProgramFilter' => 'all',
            ])
            ->callTableAction('duplicate', $child);

        $clone = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->where('parent_id', $set->id)
            ->where('name', 'Podpunkt (kopia)')
            ->first();

        $this->assertNotNull($clone);
        $this->assertSame(2, (int) $clone->order);
        $this->assertSame(2, EventProgramPoint::query()->where('parent_id', $set->id)->count());
    }
}
