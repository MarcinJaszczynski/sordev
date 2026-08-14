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

class ProgramPointsRelationManagerListGroupingTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_view_groups_points_by_day_with_section_headers(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'duration_days' => 2,
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-11',
        ]);

        EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Punkt dnia 1',
            'include_in_program' => true,
            'active' => true,
        ]);

        EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 2,
            'order' => 1,
            'name' => 'Punkt dnia 2',
            'include_in_program' => true,
            'active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(ProgramPointsRelationManager::class, [
                'ownerRecord' => $event,
                'pageClass' => EditEventProgram::class,
                'ownerProgramView' => 'list',
                'ownerProgramDay' => 1,
                'ownerProgramFilter' => 'all',
            ])
            ->assertSuccessful()
            ->assertSeeHtml('fi-ta-group-header')
            ->assertSee('Dzień 1')
            ->assertSee('Dzień 2')
            ->assertSee('Punkt dnia 1')
            ->assertSee('Punkt dnia 2')
            ->assertDontSeeHtml('epp-day-banner');
    }

    public function test_days_tab_does_not_group_by_day(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['duration_days' => 2]);

        EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Tylko dzien 1',
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
            ->assertSuccessful()
            ->assertSee('Tylko dzien 1')
            ->assertDontSeeHtml('fi-ta-group-header');
    }
}
