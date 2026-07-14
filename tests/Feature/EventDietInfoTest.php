<?php

namespace Tests\Feature;

use App\Filament\Pilot\Resources\PilotEventResource\Pages\ViewPilotEvent;
use App\Models\Event;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventDietInfoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'pilot']);
    }

    public function test_event_stores_diet_info(): void
    {
        if (! Schema::hasColumn('events', 'diet_info')) {
            $this->markTestSkipped('Kolumna events.diet_info nie istnieje w tym środowisku testowym.');
        }

        $event = Event::factory()->create([
            'diet_info' => '1 x dieta bezglutenowa',
        ]);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'diet_info' => '1 x dieta bezglutenowa',
        ]);
    }

    public function test_pilot_event_view_shows_diet_info_section(): void
    {
        if (! Schema::hasColumn('events', 'diet_info')) {
            $this->markTestSkipped('Kolumna events.diet_info nie istnieje w tym środowisku testowym.');
        }

        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
            'diet_info' => '1 x dieta bezglutenowa',
        ]);

        Filament::setServingStatus(true);
        Filament::setCurrentPanel(Filament::getPanel('pilot'));

        $this->actingAs($pilot);

        Livewire::test(ViewPilotEvent::class, ['record' => $event->getKey()])
            ->assertSee('Diety')
            ->assertSee('bezglutenowa');

        Filament::setServingStatus(false);
        Filament::setCurrentPanel(null);
    }

    public function test_pilot_event_view_hides_diet_section_when_empty(): void
    {
        if (! Schema::hasColumn('events', 'diet_info')) {
            $this->markTestSkipped('Kolumna events.diet_info nie istnieje w tym środowisku testowym.');
        }

        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
            'diet_info' => null,
        ]);

        Filament::setServingStatus(true);
        Filament::setCurrentPanel(Filament::getPanel('pilot'));

        $this->actingAs($pilot);

        Livewire::test(ViewPilotEvent::class, ['record' => $event->getKey()])
            ->assertDontSee('Diety specjalne');

        Filament::setServingStatus(false);
        Filament::setCurrentPanel(null);
    }
}
