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

    public function test_transport_page_shows_only_core_route_days_when_facultative_points_exist(): void
    {
        if (! Schema::hasColumn('events', 'program_day_routes')) {
            $this->markTestSkipped('Kolumna program_day_routes nie istnieje.');
        }

        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $start = now()->startOfDay();
        $event = Event::factory()->create([
            'duration_days' => 2,
            'start_date' => $start,
            'end_date' => $start->copy()->addDay(),
        ]);

        \App\Models\EventProgramPoint::query()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Dzień 1',
            'active' => true,
        ]);
        \App\Models\EventProgramPoint::query()->create([
            'event_id' => $event->id,
            'day' => 2,
            'order' => 1,
            'name' => 'Dzień 2',
            'active' => true,
        ]);
        // Slot fakultatywny (core+1) — tylko pod stronę/szablon, nie pod trasy.
        \App\Models\EventProgramPoint::query()->create([
            'event_id' => $event->id,
            'day' => 3,
            'order' => 1,
            'name' => 'Opcja fakultatywna',
            'active' => true,
        ]);

        $this->assertSame(2, $event->resolveCoreProgramDaysCount());
        $this->assertSame(3, $event->resolveProgramDaysCount());

        $this->actingAs($admin);

        Livewire::test(ManageEventTransport::class, ['record' => $event->getKey()])
            ->assertFormFieldExists('program_day_routes.1')
            ->assertFormFieldExists('program_day_routes.2')
            ->assertFormFieldDoesNotExist('program_day_routes.3')
            ->assertSee('Wysłano do kierowcy')
            ->assertSee('Wyślij do kierowcy');
    }

    public function test_program_day_routes_ignore_facultative_day_entries(): void
    {
        if (! Schema::hasColumn('events', 'program_day_routes')) {
            $this->markTestSkipped('Kolumna program_day_routes nie istnieje.');
        }

        $start = now()->startOfDay();
        $event = Event::factory()->create([
            'duration_days' => 2,
            'start_date' => $start,
            'end_date' => $start->copy()->addDay(),
            'program_day_routes' => [
                '1' => 'Warszawa – Kraków',
                '2' => 'Kraków – Zakopane',
                '3' => 'Nie powinno się pokazać',
            ],
        ]);

        $this->assertSame([
            '1' => 'Warszawa – Kraków',
            '2' => 'Kraków – Zakopane',
        ], $event->programDayRoutes());
        $this->assertNull($event->programDayRoute(3));

        $event->setProgramDayRoute(3, 'Próba zapisu fakultatywu');
        $event->save();
        $event->refresh();

        $this->assertArrayNotHasKey('3', $event->program_day_routes ?? []);
        $this->assertNull($event->programDayRoute(3));
    }

    public function test_transport_page_can_mark_driver_pickup_info_as_sent(): void
    {
        if (! Schema::hasColumn('events', 'driver_pickup_info_sent_at')) {
            $this->markTestSkipped('Kolumna driver_pickup_info_sent_at nie istnieje.');
        }

        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $event = Event::factory()->create([
            'bus_id' => null,
            'driver_pickup_info_sent_at' => null,
            'driver_pickup_info_sent_by' => null,
        ]);

        $this->actingAs($admin);

        Livewire::test(ManageEventTransport::class, ['record' => $event->getKey()])
            ->fillForm([
                'driver_pickup_info_sent' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $event->refresh();

        $this->assertTrue($event->isDriverPickupInfoSent());
        $this->assertSame($admin->id, $event->driver_pickup_info_sent_by);
    }
}
