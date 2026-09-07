<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Livewire\EventAdditionalOrderingPartiesLookup;
use App\Livewire\EventClientLookup;
use App\Models\Contact;
use App\Models\Contractor;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EditEventClientLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_edit_event_summary_shows_selected_client_card(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $contractor = Contractor::create([
            'name' => 'Szkoła Testowa',
            'email' => 'szkola@test.pl',
            'phone' => '501502503',
            'status' => 'active',
        ]);
        $contact = Contact::create([
            'first_name' => 'Anna',
            'last_name' => 'Kowalska',
            'email' => 'anna@test.pl',
            'phone' => '601602603',
        ]);
        $contractor->contacts()->attach($contact->id);

        $event = Event::factory()->create([
            'client_name' => 'Anna Kowalska · Szkoła Testowa',
            'client_email' => 'anna@test.pl',
            'client_phone' => '601602603',
            'contractor_id' => $contractor->id,
            'status' => Event::STATUS_CONFIRMED,
        ]);
        $event->syncOrderingParties([[
            'contact_id' => $contact->id,
            'contractor_id' => $contractor->id,
            'department_label' => null,
        ]]);

        $this->actingAs($admin);

        Livewire::test(EditEvent::class, ['record' => $event->getKey()])
            ->assertSee('Wybrany zamawiający')
            ->assertSee('Anna Kowalska')
            ->assertSee('Szkoła Testowa')
            ->assertSee('601602603')
            ->assertDontSee('Główny zamawiający (nazwa)');
    }

    public function test_edit_event_applies_client_lookup_and_saves(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $oldContractor = Contractor::create([
            'name' => 'Stary klient',
            'status' => 'active',
        ]);
        $newContractor = Contractor::create([
            'name' => 'Nowy klient',
            'email' => 'nowy@example.com',
            'phone' => '700800900',
            'status' => 'active',
        ]);
        $contact = Contact::create([
            'first_name' => 'Marek',
            'last_name' => 'Nowak',
            'email' => 'marek@example.com',
            'phone' => '700800900',
        ]);
        $newContractor->contacts()->attach($contact->id);

        $event = Event::factory()->create([
            'client_name' => 'Stary klient',
            'contractor_id' => $oldContractor->id,
            'status' => Event::STATUS_CONFIRMED,
        ]);
        $event->syncOrderingParties([[
            'contact_id' => null,
            'contractor_id' => $oldContractor->id,
            'department_label' => null,
        ]]);

        $this->actingAs($admin);

        Livewire::test(EditEvent::class, ['record' => $event->getKey()])
            ->call('applyClientLookup', [
                [
                    'contact_id' => (string) $contact->id,
                    'contractor_id' => (string) $newContractor->id,
                    'department_label' => null,
                ],
            ], 'Marek Nowak · Nowy klient', 'marek@example.com', '700800900')
            ->call('save')
            ->assertHasNoFormErrors();

        $event->refresh();

        $this->assertSame('Marek Nowak · Nowy klient', $event->client_name);
        $this->assertSame('marek@example.com', $event->client_email);
        $this->assertSame('700800900', $event->client_phone);
        $this->assertTrue(
            $event->orderingContractors()->whereKey($newContractor->id)->exists()
        );
    }

    public function test_event_client_lookup_mounts_with_initial_selection(): void
    {
        Livewire::test(EventClientLookup::class, [
            'selected' => [
                'type' => 'legacy',
                'label' => 'Klient X',
                'preview' => [
                    'company' => 'Klient X',
                    'phone' => '111',
                    'email' => 'x@example.com',
                ],
            ],
        ])
            ->assertSet('selected.label', 'Klient X')
            ->assertSee('Wybrany zamawiający')
            ->assertSee('Klient X');
    }

    public function test_event_client_lookup_quick_create_shows_selected_card(): void
    {
        Livewire::test(EventClientLookup::class)
            ->assertSee('Dodaj nowego klienta')
            ->call('openQuickCreate')
            ->assertSee('Zapisz klienta i wybierz go')
            ->set('firstName', 'Piotr')
            ->set('lastName', 'Nowy')
            ->set('phone', '700701702')
            ->set('companyName', 'Firma Testowa QC')
            ->call('quickCreate')
            ->assertSee('Wybrany zamawiający')
            ->assertSee('Piotr Nowy')
            ->assertSee('Firma Testowa QC')
            ->assertDispatched('client-lookup-applied');
    }

    public function test_edit_event_shows_additional_ordering_party_cards(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $contractor = Contractor::create([
            'name' => 'Szkoła Dwóch Kontaktów',
            'status' => 'active',
        ]);
        $teacher = Contact::create([
            'first_name' => 'Anna',
            'last_name' => 'Nauczyciel',
            'phone' => '501501501',
        ]);
        $parent = Contact::create([
            'first_name' => 'Piotr',
            'last_name' => 'Rodzic',
            'phone' => '502502502',
        ]);
        $contractor->contacts()->attach([$teacher->id, $parent->id]);

        $event = Event::factory()->create([
            'client_name' => 'Anna Nauczyciel · Szkoła Dwóch Kontaktów',
            'contractor_id' => $contractor->id,
            'status' => Event::STATUS_CONFIRMED,
        ]);
        $event->syncOrderingParties([
            [
                'contact_id' => $teacher->id,
                'contractor_id' => $contractor->id,
                'department_label' => null,
                'notes' => null,
            ],
            [
                'contact_id' => $parent->id,
                'contractor_id' => $contractor->id,
                'department_label' => null,
                'notes' => 'Rodzic odpowiedzialny za rozliczenie',
            ],
        ]);

        $this->actingAs($admin);

        Livewire::test(EditEvent::class, ['record' => $event->getKey()])
            ->assertSee('Wybrany zamawiający')
            ->assertSee('Dodatkowy kontakt')
            ->assertSee('Piotr Rodzic')
            ->assertSee('Rodzic odpowiedzialny za rozliczenie');
    }

    public function test_additional_lookup_lists_company_contacts_without_search(): void
    {
        $contractor = Contractor::create([
            'name' => 'Firma Quick Pick',
            'status' => 'active',
        ]);
        $contact = Contact::create([
            'first_name' => 'Ewa',
            'last_name' => 'Kontakt',
            'phone' => '600700800',
        ]);
        $contractor->contacts()->attach($contact->id);

        Livewire::test(EventAdditionalOrderingPartiesLookup::class, [
            'additional' => [],
            'primaryContractorId' => $contractor->id,
        ])
            ->call('openAddPanel')
            ->assertSee('Kontakty firmy głównego zamawiającego')
            ->assertSee('Ewa Kontakt')
            ->call('selectCompanyContact', 0)
            ->assertSee('Dodatkowy kontakt')
            ->assertSee('Ewa Kontakt')
            ->assertDispatched('additional-ordering-parties-updated');
    }

    public function test_additional_lookup_change_replaces_existing_card(): void
    {
        $contractor = Contractor::create([
            'name' => 'Firma Edit',
            'status' => 'active',
        ]);
        $first = Contact::create([
            'first_name' => 'Pierwszy',
            'last_name' => 'Kontakt',
            'phone' => '111111111',
        ]);
        $second = Contact::create([
            'first_name' => 'Drugi',
            'last_name' => 'Kontakt',
            'phone' => '222222222',
        ]);
        $contractor->contacts()->attach([$first->id, $second->id]);

        $lookup = app(\App\Services\ClientLookupService::class);
        $initial = [$lookup->makePairRow($first, $contractor, null, 'stara notatka')];

        $component = Livewire::test(EventAdditionalOrderingPartiesLookup::class, [
            'additional' => $initial,
            'primaryContractorId' => $contractor->id,
        ])
            ->assertSee('Pierwszy Kontakt')
            ->assertSee('Zmień')
            ->call('changeAdditional', 0)
            ->assertSet('editingIndex', 0)
            ->assertSee('Zmień dodatkowy kontakt');

        $companyContacts = $component->get('companyContacts');
        $secondIndex = collect($companyContacts)
            ->search(fn (array $row): bool => (int) ($row['contact_id'] ?? 0) === $second->id);

        $this->assertNotFalse($secondIndex);

        $component
            ->call('selectCompanyContact', (int) $secondIndex)
            ->assertSet('editingIndex', null)
            ->assertSee('Drugi Kontakt')
            ->assertDontSee('Pierwszy Kontakt')
            ->assertDispatched('additional-ordering-parties-updated');
    }

    public function test_apply_client_lookup_preserves_additional_parties(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $old = Contractor::create(['name' => 'Stary', 'status' => 'active']);
        $extra = Contractor::create(['name' => 'Dodatkowa firma', 'status' => 'active']);
        $new = Contractor::create(['name' => 'Nowy główny', 'status' => 'active']);
        $extraContact = Contact::create(['first_name' => 'Extra', 'last_name' => 'Person']);
        $newContact = Contact::create(['first_name' => 'Nowy', 'last_name' => 'Główny']);
        $extra->contacts()->attach($extraContact->id);
        $new->contacts()->attach($newContact->id);

        $event = Event::factory()->create([
            'client_name' => 'Stary',
            'contractor_id' => $old->id,
            'status' => Event::STATUS_CONFIRMED,
        ]);
        $event->syncOrderingParties([
            [
                'contact_id' => null,
                'contractor_id' => $old->id,
                'department_label' => null,
            ],
            [
                'contact_id' => $extraContact->id,
                'contractor_id' => $extra->id,
                'department_label' => null,
                'notes' => 'Dodatkowy',
            ],
        ]);

        $this->actingAs($admin);

        Livewire::test(EditEvent::class, ['record' => $event->getKey()])
            ->call('applyClientLookup', [
                [
                    'contact_id' => (string) $newContact->id,
                    'contractor_id' => (string) $new->id,
                    'department_label' => null,
                    'notes' => null,
                ],
            ], 'Nowy Główny · Nowy główny', null, null)
            ->assertSet('data.ordering_parties.0.contractor_id', (string) $new->id)
            ->assertSet('data.ordering_parties.1.contractor_id', (string) $extra->id);
    }

    public function test_saving_additional_party_keeps_existing_primary_without_manual_state(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $contractor = Contractor::create([
            'name' => 'Firma Główna',
            'status' => 'active',
        ]);
        $primaryContact = Contact::create([
            'first_name' => 'Główny',
            'last_name' => 'Kontakt',
            'phone' => '111222333',
        ]);
        $additionalContact = Contact::create([
            'first_name' => 'Dodatkowy',
            'last_name' => 'Kontakt',
            'phone' => '444555666',
        ]);
        $contractor->contacts()->attach([$primaryContact->id, $additionalContact->id]);

        $event = Event::factory()->create([
            'client_name' => 'Główny Kontakt · Firma Główna',
            'client_phone' => '111222333',
            'contractor_id' => $contractor->id,
            'status' => Event::STATUS_CONFIRMED,
        ]);
        $event->syncOrderingParties([[
            'contact_id' => $primaryContact->id,
            'contractor_id' => $contractor->id,
            'department_label' => null,
        ]]);

        $this->actingAs($admin);

        Livewire::test(EditEvent::class, ['record' => $event->getKey()])
            ->call('applyAdditionalOrderingParties', [
                [
                    'contact_id' => (string) $additionalContact->id,
                    'contractor_id' => (string) $contractor->id,
                    'department_label' => null,
                    'notes' => 'Drugi kontakt',
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $event->refresh();

        $this->assertStringContainsString('Główny Kontakt', (string) $event->client_name);

        $parties = $event->orderingContractors()->orderByPivot('sort_order')->get();
        $this->assertCount(2, $parties);
        $this->assertSame($primaryContact->id, (int) $parties[0]->pivot->contact_id);
        $this->assertSame($additionalContact->id, (int) $parties[1]->pivot->contact_id);
    }

    public function test_saving_additional_party_keeps_primary_when_repeater_state_is_empty(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $contractor = Contractor::create([
            'name' => 'Firma Główna',
            'status' => 'active',
        ]);
        $primaryContact = Contact::create([
            'first_name' => 'Główny',
            'last_name' => 'Kontakt',
            'phone' => '111222333',
        ]);
        $additionalContact = Contact::create([
            'first_name' => 'Dodatkowy',
            'last_name' => 'Kontakt',
            'phone' => '444555666',
        ]);
        $contractor->contacts()->attach([$primaryContact->id, $additionalContact->id]);

        $event = Event::factory()->create([
            'client_name' => 'Główny Kontakt · Firma Główna',
            'client_phone' => '111222333',
            'contractor_id' => $contractor->id,
            'status' => Event::STATUS_CONFIRMED,
        ]);
        $event->syncOrderingParties([[
            'contact_id' => $primaryContact->id,
            'contractor_id' => $contractor->id,
            'department_label' => null,
        ]]);

        $this->actingAs($admin);

        Livewire::test(EditEvent::class, ['record' => $event->getKey()])
            ->set('data.ordering_parties', [])
            ->dispatch('additional-ordering-parties-updated', additionalParties: [
                [
                    'contact_id' => (string) $additionalContact->id,
                    'contractor_id' => (string) $contractor->id,
                    'department_label' => null,
                    'notes' => 'Drugi kontakt',
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $event->refresh();

        $this->assertStringContainsString('Główny Kontakt', (string) $event->client_name);

        $parties = $event->orderingContractors()->orderByPivot('sort_order')->get();
        $this->assertCount(2, $parties);
        $this->assertSame($primaryContact->id, (int) $parties[0]->pivot->contact_id);
        $this->assertSame($additionalContact->id, (int) $parties[1]->pivot->contact_id);
        $this->assertSame('Drugi kontakt', $parties[1]->pivot->notes);
    }

    public function test_apply_additional_ordering_parties_preserves_primary_with_uuid_keys(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $contractor = Contractor::create([
            'name' => 'Firma Główna',
            'status' => 'active',
        ]);
        $primaryContact = Contact::create([
            'first_name' => 'Główny',
            'last_name' => 'Kontakt',
            'phone' => '111222333',
        ]);
        $additionalContact = Contact::create([
            'first_name' => 'Dodatkowy',
            'last_name' => 'Kontakt',
            'phone' => '444555666',
        ]);
        $contractor->contacts()->attach([$primaryContact->id, $additionalContact->id]);

        $event = Event::factory()->create([
            'client_name' => 'Główny Kontakt · Firma Główna',
            'client_email' => null,
            'client_phone' => '111222333',
            'contractor_id' => $contractor->id,
            'status' => Event::STATUS_CONFIRMED,
        ]);
        $event->syncOrderingParties([[
            'contact_id' => $primaryContact->id,
            'contractor_id' => $contractor->id,
            'department_label' => null,
        ]]);

        $this->actingAs($admin);

        $component = Livewire::test(EditEvent::class, ['record' => $event->getKey()]);

        // Symulacja stanu repeatera Filament (klucze UUID zamiast 0,1,2).
        $component->set('data.ordering_parties', [
            '9f1a2b3c-4d5e-6f70-8192-a3b4c5d6e7f8' => [
                'contact_id' => (string) $primaryContact->id,
                'contractor_id' => (string) $contractor->id,
                'department_label' => null,
                'notes' => null,
            ],
        ]);

        $component
            ->call('applyAdditionalOrderingParties', [
                [
                    'contact_id' => (string) $additionalContact->id,
                    'contractor_id' => (string) $contractor->id,
                    'department_label' => null,
                    'notes' => 'Drugi kontakt',
                ],
            ])
            ->assertSet('data.ordering_parties.0.contact_id', (string) $primaryContact->id)
            ->assertSet('data.ordering_parties.0.contractor_id', (string) $contractor->id)
            ->assertSet('data.ordering_parties.1.contact_id', (string) $additionalContact->id)
            ->assertSet('data.ordering_parties.1.notes', 'Drugi kontakt')
            ->call('save')
            ->assertHasNoFormErrors();

        $event->refresh();

        $this->assertSame($contractor->id, (int) $event->contractor_id);
        $this->assertStringContainsString('Główny Kontakt', (string) $event->client_name);

        $parties = $event->orderingContractors()->orderByPivot('sort_order')->get();
        $this->assertCount(2, $parties);
        $this->assertSame($primaryContact->id, (int) $parties[0]->pivot->contact_id);
        $this->assertSame($additionalContact->id, (int) $parties[1]->pivot->contact_id);
        $this->assertSame('Drugi kontakt', $parties[1]->pivot->notes);
    }

    public function test_additional_lookup_quick_create_dispatches_form_party(): void
    {
        $contractor = Contractor::create([
            'name' => 'Firma Ręczna',
            'status' => 'active',
        ]);
        $primaryContact = Contact::create([
            'first_name' => 'Jan',
            'last_name' => 'Główny',
            'phone' => '500600700',
        ]);
        $contractor->contacts()->attach($primaryContact->id);

        Livewire::test(EventAdditionalOrderingPartiesLookup::class, [
            'additional' => [],
            'primaryContractorId' => $contractor->id,
        ])
            ->call('openAddPanel')
            ->call('openQuickCreate')
            ->set('firstName', 'Maria')
            ->set('lastName', 'Nowa')
            ->set('phone', '501502503')
            ->set('notes', 'Ręcznie dodany')
            ->call('quickCreate')
            ->assertDispatched('additional-ordering-parties-updated')
            ->assertSee('Maria Nowa')
            ->assertSee('Ręcznie dodany');

        $this->assertDatabaseHas('contacts', [
            'first_name' => 'Maria',
            'last_name' => 'Nowa',
            'phone' => '501502503',
        ]);

        $created = Contact::query()
            ->where('first_name', 'Maria')
            ->where('last_name', 'Nowa')
            ->first();

        $this->assertNotNull($created);
        $this->assertTrue($contractor->contacts()->whereKey($created->id)->exists());
    }

    public function test_additional_lookup_quick_create_can_make_new_client_company(): void
    {
        $contractor = Contractor::create([
            'name' => 'Firma Główna',
            'status' => 'active',
        ]);
        $primaryContact = Contact::create([
            'first_name' => 'Jan',
            'last_name' => 'Główny',
            'phone' => '500600700',
        ]);
        $contractor->contacts()->attach($primaryContact->id);

        Livewire::test(EventAdditionalOrderingPartiesLookup::class, [
            'additional' => [],
            'primaryContractorId' => $contractor->id,
        ])
            ->call('openAddPanel')
            ->assertSee('Utwórz nową firmę / klienta')
            ->call('openQuickCreate', true)
            ->assertSet('createAsNewCompany', true)
            ->assertSee('Firma / instytucja')
            ->set('companyName', 'Nowa Szkoła Testowa')
            ->set('firstName', 'Anna')
            ->set('lastName', 'Nowak')
            ->set('phone', '501111222')
            ->set('notes', 'Drugi zamawiający')
            ->call('quickCreate')
            ->assertDispatched('additional-ordering-parties-updated')
            ->assertSee('Anna Nowak')
            ->assertSee('Nowa Szkoła Testowa')
            ->assertSee('Drugi zamawiający');

        $createdCompany = Contractor::query()->where('name', 'Nowa Szkoła Testowa')->first();
        $this->assertNotNull($createdCompany);
        $this->assertNotSame($contractor->id, $createdCompany->id);
        $this->assertTrue(
            $createdCompany->types()->whereRaw('LOWER(name) = ?', ['klient'])->exists(),
            'Nowa firma dodatkowego zamawiającego powinna mieć typ klient',
        );

        $createdContact = Contact::query()
            ->where('first_name', 'Anna')
            ->where('last_name', 'Nowak')
            ->where('phone', '501111222')
            ->first();

        $this->assertNotNull($createdContact);
        $this->assertTrue($createdCompany->contacts()->whereKey($createdContact->id)->exists());
        $this->assertFalse($contractor->contacts()->whereKey($createdContact->id)->exists());
    }
}
