<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Models\Contractor;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EditEventScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
    }

    public function test_edit_event_saves_updated_schedule(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $contractor = Contractor::create([
            'name' => 'Klient testowy',
            'email' => 'klient@example.com',
            'status' => 'active',
        ]);

        $event = Event::factory()->create([
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-07',
            'duration_days' => 7,
            'client_name' => 'Klient testowy',
            'contractor_id' => $contractor->id,
            'status' => Event::STATUS_CONFIRMED,
        ]);

        $this->actingAs($admin);

        Livewire::test(EditEvent::class, ['record' => $event->getKey()])
            ->fillForm([
                'start_date' => '2026-06-10',
                'end_date' => '2026-06-12',
                'duration_days' => 3,
                'ordering_parties' => [
                    [
                        'contact_id' => null,
                        'contractor_id' => (string) $contractor->id,
                        'department_label' => null,
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $event->refresh();

        $this->assertSame('2026-06-10', $event->start_date?->toDateString());
        $this->assertSame('2026-06-12', $event->end_date?->toDateString());
        $this->assertSame(3, (int) $event->duration_days);
    }
}
