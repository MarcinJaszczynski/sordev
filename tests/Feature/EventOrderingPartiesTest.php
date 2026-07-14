<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\User;
use App\Services\EventOrderingPartyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EventOrderingPartiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_can_have_multiple_ordering_contractors(): void
    {
        if (! Schema::hasTable('event_contractor')) {
            $this->markTestSkipped('Tabela event_contractor nie istnieje w tym środowisku testowym.');
        }

        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();

        $first = Contractor::create(['name' => 'Szkoła Podstawowa nr 1', 'status' => 'active']);
        $second = Contractor::create(['name' => 'Szkoła Podstawowa nr 2', 'status' => 'active']);

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Wycieczka klasowa',
            'client_name' => 'Placeholder',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 30,
            'total_cost' => 1000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $event->syncOrderingContractors([$first->id, $second->id]);

        $event->refresh()->load('orderingContractors');

        $this->assertCount(2, $event->orderingContractors);
        $this->assertSame('Szkoła Podstawowa nr 1', $event->client_name);
        $this->assertSame($first->id, $event->contractor_id);
        $this->assertStringContainsString('Szkoła Podstawowa nr 1', $event->formattedOrderingPartiesNames());
        $this->assertStringContainsString('Szkoła Podstawowa nr 2', $event->formattedOrderingPartiesNames());
    }

    public function test_event_ordering_party_can_store_contact_and_department(): void
    {
        if (! Schema::hasTable('event_contractor') || ! Schema::hasTable('contacts')) {
            $this->markTestSkipped('Brak tabel event_contractor lub contacts.');
        }

        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();
        $company = Contractor::create(['name' => 'Urząd Miasta', 'status' => 'active', 'email' => 'biuro@miasto.pl']);
        $contact = Contact::create([
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
            'email' => 'anna@miasto.pl',
            'phone' => '500600700',
        ]);

        if (Contractor::hasContactPivotTable()) {
            $contact->contractors()->attach($company->id);
        }

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Wycieczka urzędnicza',
            'client_name' => 'Placeholder',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 20,
            'total_cost' => 1000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $service = app(EventOrderingPartyService::class);
        $service->syncForEvent($event, [[
            'contact_id' => $contact->id,
            'contractor_id' => $company->id,
            'department_label' => 'Dział kultury',
        ]]);

        $event->refresh()->load('orderingContractors');

        $this->assertCount(1, $event->orderingContractors);
        $this->assertSame('anna@miasto.pl', $event->client_email);
        $this->assertStringContainsString('Anna Nowak', $event->client_name);
        $this->assertStringContainsString('Dział kultury', $event->client_name);
        $this->assertStringContainsString('Anna Nowak', $event->formattedOrderingPartiesNames());
    }

    public function test_sync_for_event_links_contact_and_contractor_without_existing_pivot(): void
    {
        if (! Schema::hasTable('event_contractor') || ! Schema::hasTable('contacts') || ! Contractor::hasContactPivotTable()) {
            $this->markTestSkipped('Brak tabel event_contractor, contacts lub contractor_contact.');
        }

        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();
        $company = Contractor::create(['name' => 'Nowa Szkoła', 'status' => 'active']);
        $contact = Contact::create([
            'first_name' => 'Ewa',
            'last_name' => 'Test',
            'email' => 'ewa@test.pl',
        ]);

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Wycieczka testowa',
            'client_name' => 'Placeholder',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 20,
            'total_cost' => 1000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        app(EventOrderingPartyService::class)->syncForEvent($event, [[
            'contact_id' => $contact->id,
            'contractor_id' => $company->id,
            'department_label' => null,
        ]]);

        $this->assertDatabaseHas(Contractor::contactPivotTable(), [
            'contact_id' => $contact->id,
            'contractor_id' => $company->id,
        ]);
    }

    public function test_sync_for_event_does_not_duplicate_contact_contractor_pivot(): void
    {
        if (! Schema::hasTable('event_contractor') || ! Schema::hasTable('contacts') || ! Contractor::hasContactPivotTable()) {
            $this->markTestSkipped('Brak tabel event_contractor, contacts lub contractor_contact.');
        }

        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();
        $company = Contractor::create(['name' => 'Szkoła', 'status' => 'active']);
        $contact = Contact::create([
            'first_name' => 'Marek',
            'last_name' => 'Test',
        ]);

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Wycieczka',
            'client_name' => 'Placeholder',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 20,
            'total_cost' => 1000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $service = app(EventOrderingPartyService::class);
        $party = [
            'contact_id' => $contact->id,
            'contractor_id' => $company->id,
            'department_label' => null,
        ];

        $service->syncForEvent($event, [$party]);
        $service->syncForEvent($event->fresh(), [$party]);

        $this->assertSame(
            1,
            (int) \Illuminate\Support\Facades\DB::table(Contractor::contactPivotTable())
                ->where('contact_id', $contact->id)
                ->where('contractor_id', $company->id)
                ->count()
        );
    }
}
