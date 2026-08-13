<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\CreateEvent;
use App\Filament\Resources\EventResource\Pages\ManageEventTransport;
use App\Models\Contact;
use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\Place;
use App\Models\PlaceDistance;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventProgramStartPlaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_clean_event_persists_program_start_place_and_shows_on_transport(): void
    {
        if (! Schema::hasColumn('events', 'program_start_place_id')) {
            $this->markTestSkipped('Brak kolumny program_start_place_id.');
        }

        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $pickup = Place::factory()->starting()->create(['name' => 'Warszawa Podstawienie']);
        $programStart = Place::factory()->create(['name' => 'Kraków Program']);

        PlaceDistance::query()->create([
            'from_place_id' => $pickup->id,
            'to_place_id' => $programStart->id,
            'distance_km' => 150,
        ]);

        $contractor = Contractor::create(['name' => 'Szkoła Test', 'status' => 'active']);
        $contact = Contact::create([
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'email' => 'jan@example.com',
            'phone' => '500600700',
        ]);
        $contractor->contacts()->attach($contact->id);

        $this->actingAs($admin);
        Filament::setServingStatus(true);

        Livewire::test(CreateEvent::class)
            ->call('applyClientLookup', [
                [
                    'contact_id' => (string) $contact->id,
                    'contractor_id' => (string) $contractor->id,
                    'department_label' => null,
                ],
            ], 'Jan Kowalski · Szkoła Test', 'jan@example.com', '500600700')
            ->fillForm([
                'event_template_id' => null,
                'name' => 'Czysta z początkiem programu',
                'start_date' => '2026-09-01',
                'duration_days' => 2,
                'participant_count' => 20,
                'start_place_id' => $pickup->id,
                'program_start_place_id' => $programStart->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $event = Event::query()->where('name', 'Czysta z początkiem programu')->first();
        $this->assertNotNull($event);
        $this->assertSame($pickup->id, (int) $event->start_place_id);
        $this->assertSame($programStart->id, (int) $event->program_start_place_id);
        $this->assertEquals(300.0, (float) $event->transfer_km);

        Livewire::test(ManageEventTransport::class, ['record' => $event->getKey()])
            ->assertFormSet([
                'start_place_id' => $pickup->id,
                'program_start_place_id' => $programStart->id,
                'transfer_km' => 300,
            ]);
    }

    public function test_create_from_template_copies_program_start_place(): void
    {
        if (! Schema::hasColumn('events', 'program_start_place_id')) {
            $this->markTestSkipped('Brak kolumny program_start_place_id.');
        }

        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $pickup = Place::factory()->starting()->create(['name' => 'Łódź']);
        $programStart = Place::factory()->create(['name' => 'Gdańsk']);
        $template = EventTemplate::factory()->create([
            'name' => 'Szablon Gdańsk',
            'start_place_id' => $programStart->id,
            'duration_days' => 2,
            'transfer_km' => 0,
            'program_km' => 40,
        ]);

        $contractor = Contractor::create(['name' => 'Firma', 'status' => 'active']);
        $contact = Contact::create([
            'first_name' => 'Ala',
            'last_name' => 'Nowak',
            'phone' => '501501501',
        ]);
        $contractor->contacts()->attach($contact->id);

        $this->actingAs($admin);

        Livewire::test(CreateEvent::class)
            ->call('applyClientLookup', [
                [
                    'contact_id' => (string) $contact->id,
                    'contractor_id' => (string) $contractor->id,
                    'department_label' => null,
                ],
            ], 'Ala Nowak · Firma', null, '501501501')
            ->fillForm([
                'event_template_id' => $template->id,
                'name' => 'Ze szablonu Gdańsk',
                'start_date' => '2026-10-01',
                'duration_days' => 2,
                'participant_count' => 15,
                'start_place_id' => $pickup->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $event = Event::query()->where('name', 'Ze szablonu Gdańsk')->first();
        $this->assertNotNull($event);
        $this->assertSame($pickup->id, (int) $event->start_place_id);
        $this->assertSame($programStart->id, (int) $event->program_start_place_id);
    }
}
