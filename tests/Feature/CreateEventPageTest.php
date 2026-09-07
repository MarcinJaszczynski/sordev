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

        foreach (['admin', 'super_admin', 'biuro'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
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

    public function test_create_event_defaults_staff_and_driver_count_to_one(): void
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
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
            'email' => 'anna@example.com',
            'phone' => '500600701',
        ]);
        $contractor->contacts()->attach($contact->id);

        $this->actingAs($admin);

        $component = Livewire::test(CreateEvent::class);

        $this->assertSame(1, (int) $component->get('data.staff_count'));
        $this->assertSame(1, (int) $component->get('data.driver_count'));

        $component
            ->call('applyClientLookup', [
                [
                    'contact_id' => (string) $contact->id,
                    'contractor_id' => (string) $contractor->id,
                    'department_label' => null,
                ],
            ], 'Anna Nowak · Szkoła Podstawowa', 'anna@example.com', '500600701')
            ->fillForm([
                'event_template_id' => $template->id,
                'name' => 'Wycieczka z domyślną obsługą',
                'start_date' => '2026-08-01',
                'duration_days' => 2,
                'participant_count' => 20,
                'gratis_count' => 2,
                'start_place_id' => $place->id,
                // celowo bez staff_count / driver_count
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $event = Event::query()->where('name', 'Wycieczka z domyślną obsługą')->first();
        $this->assertNotNull($event);

        $variant = $event->qtyVariants()->where('qty', 20)->first();
        $this->assertNotNull($variant);
        $this->assertSame(1, (int) $variant->staff);
        $this->assertSame(1, (int) $variant->driver);
        $this->assertSame(1, $event->resolveStaffCountForParticipantCount(20));
        $this->assertSame(1, $event->resolveDriverCountForParticipantCount(20));
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
            ->assertHasFormErrors(['ordering_parties']);

        $errors = app(\App\Services\EventOrderingPartyService::class)->validateForEventCreation(
            null,
            null,
            null,
            null,
        );
        $this->assertStringContainsString('Dodaj nowego klienta', $errors['ordering_parties'] ?? '');
        $this->assertStringContainsString('Zapisz klienta i wybierz go', $errors['client_name'] ?? '');

        $this->assertSame(0, Event::query()->where('name', 'Bez klienta')->count());
    }

    public function test_create_event_after_lookup_quick_create_flow(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $place = Place::factory()->starting()->create();
        $template = EventTemplate::factory()->create([
            'start_place_id' => $place->id,
            'duration_days' => 2,
        ]);

        $this->actingAs($admin);

        $lookup = Livewire::test(\App\Livewire\EventClientLookup::class)
            ->call('openQuickCreate')
            ->assertSee('Zapisz klienta i wybierz go')
            ->set('companyName', 'Szkoła Quick Create')
            ->set('firstName', 'Ola')
            ->set('lastName', 'Nowa')
            ->set('phone', '601602603')
            ->call('quickCreate')
            ->assertSee('Wybrany zamawiający')
            ->assertSee('Ola Nowa')
            ->assertDispatched('client-lookup-applied');

        $selected = $lookup->get('selected');
        $this->assertIsArray($selected);
        $this->assertNotEmpty($selected['contractor_id'] ?? null);

        $orderingParties = [[
            'contact_id' => filled($selected['contact_id'] ?? null) ? (string) $selected['contact_id'] : null,
            'contractor_id' => (string) $selected['contractor_id'],
            'department_label' => null,
            'notes' => null,
        ]];
        $attrs = app(\App\Services\EventOrderingPartyService::class)->primaryClientAttributes($orderingParties);

        Livewire::test(CreateEvent::class)
            ->call(
                'applyClientLookup',
                $orderingParties,
                (string) ($attrs['client_name'] ?? ''),
                $attrs['client_email'] ?? null,
                $attrs['client_phone'] ?? null,
            )
            ->assertSet('data.client_phone', '601602603')
            ->fillForm([
                'event_template_id' => $template->id,
                'name' => 'Impreza po quick create',
                'start_date' => '2026-08-01',
                'duration_days' => 2,
                'participant_count' => 12,
                'start_place_id' => $place->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('events', [
            'name' => 'Impreza po quick create',
            'client_phone' => '601602603',
        ]);
        $this->assertDatabaseHas('contractors', [
            'name' => 'Szkoła Quick Create',
            'phone' => '601602603',
        ]);
        $this->assertDatabaseHas('contacts', [
            'first_name' => 'Ola',
            'last_name' => 'Nowa',
            'phone' => '601602603',
        ]);
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

    public function test_create_event_persists_transport_times_from_identity_section(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $place = Place::factory()->starting()->create();
        $template = EventTemplate::factory()->create([
            'start_place_id' => $place->id,
            'duration_days' => 2,
        ]);

        $contractor = Contractor::create([
            'name' => 'Szkoła',
            'status' => 'active',
        ]);
        $contact = Contact::create([
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
            'email' => 'anna@example.com',
            'phone' => '501502503',
        ]);
        $contractor->contacts()->attach($contact->id);

        $this->actingAs($admin);

        $form = [
            'event_template_id' => $template->id,
            'name' => 'Impreza z godzinami',
            'start_date' => '2026-08-01',
            'duration_days' => 2,
            'participant_count' => 20,
            'start_place_id' => $place->id,
            'departure_time' => '08:30',
            'return_time' => '18:00',
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('events', 'substitution_time')) {
            $form['substitution_time'] = '07:45';
        }

        Livewire::test(CreateEvent::class)
            ->call('applyClientLookup', [
                [
                    'contact_id' => (string) $contact->id,
                    'contractor_id' => (string) $contractor->id,
                    'department_label' => null,
                ],
            ], 'Anna Nowak · Szkoła', 'anna@example.com', '501502503')
            ->fillForm($form)
            ->set('data.departure_time', '08:30')
            ->set('data.return_time', '18:00')
            ->tap(function ($component) {
                if (\Illuminate\Support\Facades\Schema::hasColumn('events', 'substitution_time')) {
                    $component->set('data.substitution_time', '07:45');
                }
            })
            ->call('create')
            ->assertHasNoFormErrors();

        $event = Event::query()->where('name', 'Impreza z godzinami')->first();
        $this->assertNotNull($event);
        $this->assertStringContainsString('08:30', (string) $event->departure_time);
        $this->assertStringContainsString('18:00', (string) $event->return_time);

        if (\Illuminate\Support\Facades\Schema::hasColumn('events', 'substitution_time')) {
            $this->assertStringContainsString('07:45', (string) $event->substitution_time);
        }
    }

    public function test_biuro_cannot_create_event_without_template(): void
    {
        \Spatie\Permission\Models\Permission::findOrCreate('create event', 'web');

        $biuro = User::factory()->create(['status' => 'active']);
        $biuro->assignRole('biuro');
        $biuro->givePermissionTo('create event');

        $place = Place::factory()->starting()->create();
        $payload = app(\App\Services\ClientLookupService::class)->quickCreate([
            'first_name' => 'Ewa',
            'last_name' => 'Test',
            'phone' => '500100200',
            'email' => 'ewa@example.com',
        ]);

        $this->actingAs($biuro);

        Livewire::test(CreateEvent::class)
            ->call(
                'applyClientLookup',
                $payload['ordering_parties'],
                $payload['client_name'],
                $payload['client_email'],
                $payload['client_phone'],
            )
            ->fillForm([
                'event_template_id' => null,
                'name' => 'Próba bez szablonu',
                'start_date' => '2026-08-01',
                'duration_days' => 1,
                'participant_count' => 10,
                'start_place_id' => $place->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['event_template_id']);

        $this->assertDatabaseMissing('events', ['name' => 'Próba bez szablonu']);
    }

    public function test_admin_can_create_event_without_template(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $place = Place::factory()->starting()->create();
        $contractor = Contractor::create(['name' => 'Firma Admin', 'status' => 'active']);
        $contact = Contact::create([
            'first_name' => 'Admin',
            'last_name' => 'Klient',
            'email' => 'admin.klient@example.com',
            'phone' => '511511511',
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
            ], 'Admin Klient · Firma Admin', 'admin.klient@example.com', '511511511')
            ->fillForm([
                'event_template_id' => null,
                'name' => 'Czysta impreza admin',
                'start_date' => '2026-08-15',
                'duration_days' => 1,
                'participant_count' => 8,
                'start_place_id' => $place->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('events', [
            'name' => 'Czysta impreza admin',
            'event_template_id' => null,
        ]);
    }
}
