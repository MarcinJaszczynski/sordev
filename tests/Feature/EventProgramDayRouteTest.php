<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\EditEventProgram;
use App\Filament\Resources\EventResource\Pages\ManageEventTransport;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventProgramDayRouteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
    }

    public function test_event_stores_and_reads_program_day_routes(): void
    {
        if (! Schema::hasColumn('events', 'program_day_routes')) {
            $this->markTestSkipped('Kolumna program_day_routes nie istnieje.');
        }

        $event = Event::factory()->create(['duration_days' => 3]);

        $event->setProgramDayRoute(1, 'Warszawa – Poznań – Kalisz');
        $event->setProgramDayRoute(2, 'Kalisz – Wrocław');
        $event->save();

        $event->refresh();

        $this->assertSame('Warszawa – Poznań – Kalisz', $event->programDayRoute(1));
        $this->assertSame('Kalisz – Wrocław', $event->programDayRoute(2));
        $this->assertNull($event->programDayRoute(3));
        $this->assertSame([
            '1' => 'Warszawa – Poznań – Kalisz',
            '2' => 'Kalisz – Wrocław',
        ], $event->programDayRoutes());
    }

    public function test_program_page_saves_day_route(): void
    {
        if (! Schema::hasColumn('events', 'program_day_routes')) {
            $this->markTestSkipped('Kolumna program_day_routes nie istnieje.');
        }

        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $event = Event::factory()->create(['duration_days' => 2]);

        $this->actingAs($admin);

        Livewire::test(EditEventProgram::class, ['record' => $event->getKey()])
            ->set('programDay', 1)
            ->set('programDayRoute', 'Warszawa – Łódź – Kraków')
            ->call('updateProgramDayRoute')
            ->assertHasNoErrors();

        $event->refresh();

        $this->assertSame('Warszawa – Łódź – Kraków', $event->programDayRoute(1));
    }

    public function test_transport_page_saves_program_day_routes(): void
    {
        if (! Schema::hasColumn('events', 'program_day_routes')) {
            $this->markTestSkipped('Kolumna program_day_routes nie istnieje.');
        }

        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $event = Event::factory()->create(['duration_days' => 2]);

        $this->actingAs($admin);

        Livewire::test(ManageEventTransport::class, ['record' => $event->getKey()])
            ->fillForm([
                'program_day_routes' => [
                    '1' => 'Warszawa – Poznań',
                    '2' => 'Poznań – Gdańsk',
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $event->refresh();

        $this->assertSame('Warszawa – Poznań', $event->programDayRoute(1));
        $this->assertSame('Poznań – Gdańsk', $event->programDayRoute(2));
    }
}
