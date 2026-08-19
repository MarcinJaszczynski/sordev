<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\EditEventProgram;
use App\Filament\Resources\EventResource\RelationManagers\ProgramPointsRelationManager;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventTemplateProgramPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProgramPointsRelationManagerOrderPrefillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_edit_form_prefills_current_order_including_zero(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $event = Event::factory()->create(['duration_days' => 3]);
        $template = EventTemplateProgramPoint::factory()->create();
        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'event_template_program_point_id' => $template->id,
            'day' => 1,
            'order' => 0,
            'name' => 'Punkt z zerową kolejnością',
        ]);

        Livewire::actingAs($user)
            ->test(ProgramPointsRelationManager::class, [
                'ownerRecord' => $event,
                'pageClass' => EditEventProgram::class,
                'ownerProgramView' => 'days',
                'ownerProgramDay' => 1,
            ])
            ->mountTableAction('edit', $point->getKey())
            ->assertTableActionDataSet([
                'order' => 0,
                'day' => 1,
            ]);
    }

    public function test_edit_form_prefills_existing_order(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $event = Event::factory()->create(['duration_days' => 3]);
        $template = EventTemplateProgramPoint::factory()->create();
        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'event_template_program_point_id' => $template->id,
            'day' => 2,
            'order' => 7,
            'name' => 'Punkt siódmy',
        ]);

        Livewire::actingAs($user)
            ->test(ProgramPointsRelationManager::class, [
                'ownerRecord' => $event,
                'pageClass' => EditEventProgram::class,
                'ownerProgramView' => 'days',
                'ownerProgramDay' => 2,
            ])
            ->mountTableAction('edit', $point->getKey())
            ->assertTableActionDataSet([
                'order' => 7,
                'day' => 2,
            ]);
    }

    public function test_add_form_prefills_next_order_for_active_day(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $event = Event::factory()->create(['duration_days' => 3]);

        EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'parent_id' => null,
        ]);
        EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 2,
            'parent_id' => null,
        ]);
        EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 2,
            'order' => 9,
            'parent_id' => null,
        ]);

        Livewire::actingAs($user)
            ->test(ProgramPointsRelationManager::class, [
                'ownerRecord' => $event,
                'pageClass' => EditEventProgram::class,
                'ownerProgramView' => 'days',
                'ownerProgramDay' => 1,
            ])
            ->mountTableAction('add_program_point')
            ->assertTableActionDataSet([
                'day' => 1,
                'order' => 3,
                'include_in_program' => true,
                'include_in_calculation' => true,
                'active' => true,
            ]);
    }

    public function test_add_form_updates_order_when_day_changes(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $event = Event::factory()->create(['duration_days' => 3]);

        EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 2,
            'parent_id' => null,
        ]);
        EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 2,
            'order' => 5,
            'parent_id' => null,
        ]);

        Livewire::actingAs($user)
            ->test(ProgramPointsRelationManager::class, [
                'ownerRecord' => $event,
                'pageClass' => EditEventProgram::class,
                'ownerProgramView' => 'days',
                'ownerProgramDay' => 1,
            ])
            ->mountTableAction('add_program_point')
            ->assertTableActionDataSet([
                'day' => 1,
                'order' => 3,
            ])
            ->setTableActionData([
                'day' => 2,
            ])
            ->assertTableActionDataSet([
                'day' => 2,
                'order' => 6,
            ]);
    }
}
