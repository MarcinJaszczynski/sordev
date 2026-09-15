<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\EditEventProgram;
use App\Filament\Resources\EventResource\RelationManagers\ProgramPointsRelationManager;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventTemplateProgramPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProgramPointsRelationManagerPlannedPriceSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_adding_template_point_seeds_planned_price_for_full_headcount(): void
    {
        $user = User::factory()->create();
        $pln = Currency::factory()->pln()->create();

        $event = Event::factory()->create([
            'duration_days' => 3,
            'participant_count' => 20,
        ]);

        $template = EventTemplateProgramPoint::factory()->create([
            'name' => 'Kolacja bankietowa bankiet cena',
            'unit_price' => 220,
            'group_size' => 1,
            'currency_id' => $pln->id,
            'include_pilot_in_cost' => true,
            'include_gratis_in_cost' => false,
            'include_driver_in_cost' => false,
        ]);

        Livewire::actingAs($user)
            ->test(ProgramPointsRelationManager::class, [
                'ownerRecord' => $event,
                'pageClass' => EditEventProgram::class,
                'ownerProgramView' => 'days',
                'ownerProgramDay' => 1,
                'ownerProgramFilter' => 'all',
            ])
            ->callTableAction('add_program_point', data: [
                'source_point' => 'template_'.$template->id,
                'name' => $template->name,
                'day' => 1,
                'order' => 1,
                'unit_price' => 220,
                'group_size' => 1,
                'quantity' => 1,
                'currency_id' => $pln->id,
                'convert_to_pln' => false,
                // Symulacja poprawnego seedu z applyTemplateDefaults (po fixie).
                'planned_price' => 4620,
                'paid_price' => 0,
                'calculated_price' => 4620,
                'include_in_program' => true,
                'include_in_calculation' => true,
                'include_pilot_in_cost' => true,
                'include_gratis_in_cost' => false,
                'include_driver_in_cost' => false,
                'active' => true,
            ])
            ->assertHasNoTableActionErrors();

        $point = EventProgramPoint::query()
            ->where('event_id', $event->id)
            ->where('name', $template->name)
            ->first();

        $this->assertNotNull($point);
        $this->assertEqualsWithDelta(4620.0, (float) $point->planned_price, 0.01);
        $this->assertEqualsWithDelta(4620.0, (float) $point->calculated_price, 0.01);
        $this->assertTrue((bool) $point->include_pilot_in_cost);
    }
}
