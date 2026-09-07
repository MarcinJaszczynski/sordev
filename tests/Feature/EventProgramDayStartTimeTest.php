<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\EditEventProgram;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventProgramDayStartTimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
    }

    public function test_changing_day_start_time_relayouts_program_points_in_sequence(): void
    {
        if (! Schema::hasColumn('events', 'program_day_start_times')) {
            $this->markTestSkipped('Kolumna program_day_start_times nie istnieje.');
        }

        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $event = Event::factory()->create(['duration_days' => 1]);

        $first = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Śniadanie',
            'day' => 1,
            'order' => 1,
            'duration_hours' => 1,
            'duration_minutes' => 0,
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
            'convert_to_pln' => false,
        ]);

        $second = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Zwiedzanie',
            'day' => 1,
            'order' => 2,
            'duration_hours' => 2,
            'duration_minutes' => 0,
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
            'convert_to_pln' => false,
        ]);

        $this->actingAs($admin);

        // Symuluje wire:change="updateProgramDayStartTime($event.target.value)" —
        // wartość musi dojść jako argument, nie tylko przez deferred wire:model.
        Livewire::test(EditEventProgram::class, ['record' => $event->getKey()])
            ->set('programDay', 1)
            ->call('updateProgramDayStartTime', '10:00')
            ->assertHasNoErrors()
            ->assertSet('programDayStartTime', '10:00');

        $event->refresh();
        $first->refresh();
        $second->refresh();

        $this->assertSame('10:00', $event->programDayStartTimeLabel(1));
        $this->assertSame('10:00:00', substr((string) $first->start_time, 0, 8));
        $this->assertSame('11:00:00', substr((string) $first->end_time, 0, 8));
        $this->assertSame('11:00:00', substr((string) $second->start_time, 0, 8));
        $this->assertSame('13:00:00', substr((string) $second->end_time, 0, 8));
    }

    public function test_day_start_time_accepts_hms_and_normalizes(): void
    {
        if (! Schema::hasColumn('events', 'program_day_start_times')) {
            $this->markTestSkipped('Kolumna program_day_start_times nie istnieje.');
        }

        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $event = Event::factory()->create(['duration_days' => 1]);

        EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Zbiórka',
            'day' => 1,
            'order' => 1,
            'duration_hours' => 1,
            'duration_minutes' => 0,
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
            'convert_to_pln' => false,
        ]);

        $this->actingAs($admin);

        Livewire::test(EditEventProgram::class, ['record' => $event->getKey()])
            ->call('updateProgramDayStartTime', '07:30:00')
            ->assertHasNoErrors()
            ->assertSet('programDayStartTime', '07:30');

        $event->refresh();

        $this->assertSame('07:30', $event->programDayStartTimeLabel(1));
    }
}
