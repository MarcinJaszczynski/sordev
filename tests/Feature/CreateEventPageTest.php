<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\CreateEvent;
use App\Models\Contact;
use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CreateEventPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
    }

    public function test_create_event_page_saves_event_after_client_lookup_applied(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $place = Place::factory()->starting()->create();
        $template = EventTemplate::factory()->create([
            'start_place_id' => $place->id,
            'duration_days' => 2,
        ]);

        $contractor = Contractor::create([
            'name' => 'Szkoła Podstawowa',
            'status' => 'active',
        ]);
        $contact = Contact::create([
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'email' => 'jan@example.com',
            'phone' => '500600700',
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
            ], 'Jan Kowalski · Szkoła Podstawowa', 'jan@example.com', '500600700')
            ->fillForm([
                'event_template_id' => $template->id,
                'name' => 'Wycieczka testowa',
                'start_date' => '2026-08-01',
                'duration_days' => 2,
                'participant_count' => 20,
                'gratis_count' => 2,
                'start_place_id' => $place->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('events', [
            'name' => 'Wycieczka testowa',
            'client_name' => 'Jan Kowalski · Szkoła Podstawowa',
            'event_template_id' => $template->id,
        ]);
    }

    public function test_create_event_without_client_shows_validation_error(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $place = Place::factory()->starting()->create();
        $template = EventTemplate::factory()->create([
            'start_place_id' => $place->id,
        ]);

        $this->actingAs($admin);

        Livewire::test(CreateEvent::class)
            ->fillForm([
                'event_template_id' => $template->id,
                'name' => 'Bez klienta',
                'start_date' => '2026-08-01',
                'participant_count' => 10,
                'start_place_id' => $place->id,
            ])
            ->call('create')
            ->assertHasFormErrors();

        $this->assertSame(0, Event::query()->where('name', 'Bez klienta')->count());
    }

    public function test_create_event_after_quick_create_with_phone_only(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $place = Place::factory()->starting()->create();
        $template = EventTemplate::factory()->create([
            'start_place_id' => $place->id,
            'duration_days' => 2,
        ]);

        $payload = app(\App\Services\ClientLookupService::class)->quickCreate([
            'first_name' => 'Maria',
            'last_name' => 'Test',
            'phone' => '600111222',
        ]);

        $this->actingAs($admin);

        Livewire::test(CreateEvent::class)
            ->call(
                'applyClientLookup',
                $payload['ordering_parties'],
                $payload['client_name'],
                $payload['client_email'],
                $payload['client_phone'],
            )
            ->fillForm([
                'event_template_id' => $template->id,
                'name' => 'Impreza z ręcznym zamawiającym',
                'start_date' => '2026-08-01',
                'duration_days' => 2,
                'participant_count' => 15,
                'start_place_id' => $place->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('events', [
            'name' => 'Impreza z ręcznym zamawiającym',
            'client_phone' => '600111222',
        ]);
    }

    public function test_create_event_rejects_ordering_party_without_phone_or_email(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $place = Place::factory()->starting()->create();
        $template = EventTemplate::factory()->create([
            'start_place_id' => $place->id,
        ]);

        $contractor = Contractor::create([
            'name' => 'Firma bez kontaktu',
            'status' => 'active',
        ]);

        $this->actingAs($admin);

        Livewire::test(CreateEvent::class)
            ->call('applyClientLookup', [
                [
                    'contact_id' => null,
                    'contractor_id' => (string) $contractor->id,
                    'department_label' => null,
                ],
            ], 'Firma bez kontaktu', null, null)
            ->fillForm([
                'event_template_id' => $template->id,
                'name' => 'Bez telefonu',
                'start_date' => '2026-08-01',
                'participant_count' => 10,
                'start_place_id' => $place->id,
            ])
            ->call('create')
            ->assertHasFormErrors();

        $this->assertSame(0, Event::query()->where('name', 'Bez telefonu')->count());
    }
}
