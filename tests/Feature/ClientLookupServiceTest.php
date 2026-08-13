<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Contractor;
use App\Models\ContractorType;
use App\Services\ClientLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientLookupServiceTest extends TestCase
{
    use RefreshDatabase;

    private function markAsClient(Contractor $contractor): void
    {
        $type = ContractorType::query()->firstOrCreate(['name' => 'klient']);
        ContractorType::clearIdsForNamesCache();
        $contractor->types()->syncWithoutDetaching([(int) $type->id]);
    }

    public function test_search_by_phone_returns_contact_contractor_pair(): void
    {
        $contractor = Contractor::create([
            'name' => 'Szkoła Podstawowa nr 1',
            'phone' => '123456789',
            'email' => 'szkola@example.com',
            'status' => 'active',
        ]);
        $this->markAsClient($contractor);

        $contact = Contact::create([
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
            'phone' => '123456789',
            'email' => 'anna@example.com',
        ]);

        if (Contractor::hasContactPivotTable()) {
            $contact->contractors()->attach($contractor->id);
        }

        $results = app(ClientLookupService::class)->search([
            'phone' => '123456',
        ]);

        $this->assertTrue($results->contains(fn (array $row): bool => $row['type'] === 'pair'
            && (int) $row['contact_id'] === $contact->id
            && (int) $row['contractor_id'] === $contractor->id));
    }

    public function test_contact_with_multiple_contractors_returns_multiple_pair_rows(): void
    {
        $contact = Contact::create([
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'phone' => '555111222',
        ]);

        $contractorA = Contractor::create([
            'name' => 'Firma A',
            'status' => 'active',
        ]);

        $contractorB = Contractor::create([
            'name' => 'Firma B',
            'status' => 'active',
        ]);

        $this->markAsClient($contractorA);
        $this->markAsClient($contractorB);

        if (Contractor::hasContactPivotTable()) {
            $contact->contractors()->attach([$contractorA->id, $contractorB->id]);
        }

        $results = app(ClientLookupService::class)->search([
            'last_name' => 'Kowal',
        ]);

        $pairs = $results->where('type', 'pair')->values();

        $this->assertGreaterThanOrEqual(2, $pairs->count());
        $this->assertEqualsCanonicalizing(
            [$contractorA->id, $contractorB->id],
            $pairs->pluck('contractor_id')->map(fn ($id) => (int) $id)->all(),
        );
    }

    public function test_search_excludes_non_client_contractors(): void
    {
        $hotel = Contractor::create([
            'name' => 'Hotel Testowy',
            'phone' => '111222333',
            'status' => 'active',
        ]);
        $hotelType = ContractorType::query()->firstOrCreate(['name' => 'hotel']);
        $hotel->types()->syncWithoutDetaching([(int) $hotelType->id]);

        $results = app(ClientLookupService::class)->searchFromQuery('Hotel');

        $this->assertFalse($results->contains(
            fn (array $row): bool => (int) ($row['contractor_id'] ?? 0) === $hotel->id
        ));
    }

    public function test_search_all_includes_non_client_contractors_and_attaches_client_type_on_select(): void
    {
        $hotel = Contractor::create([
            'name' => 'Hotel Testowy',
            'phone' => '111222333',
            'status' => 'active',
        ]);
        $hotelType = ContractorType::query()->firstOrCreate(['name' => 'hotel']);
        $hotel->types()->syncWithoutDetaching([(int) $hotelType->id]);

        $results = app(ClientLookupService::class)->searchFromQuery('Hotel', searchAll: true);

        $this->assertTrue($results->contains(
            fn (array $row): bool => (int) ($row['contractor_id'] ?? 0) === $hotel->id
        ));

        $row = $results->first(
            fn (array $row): bool => (int) ($row['contractor_id'] ?? 0) === $hotel->id
        );

        app(ClientLookupService::class)->resultToOrderingParties($row);

        $this->assertTrue(
            $hotel->fresh()->types()->whereRaw('LOWER(name) = ?', ['klient'])->exists(),
        );
    }

    public function test_search_without_active_criteria_returns_empty_collection(): void
    {
        Contact::create([
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);

        $results = app(ClientLookupService::class)->search([
            'phone' => '1',
        ]);

        $this->assertTrue($results->isEmpty());
    }

    public function test_search_from_query_searches_across_all_fields(): void
    {
        $contractor = Contractor::create([
            'name' => 'Gimnazjum w Testowie',
            'email' => 'gimnazjum@test.pl',
            'status' => 'active',
        ]);
        $this->markAsClient($contractor);

        $results = app(ClientLookupService::class)->searchFromQuery('gimnazjum');

        $this->assertTrue($results->contains(fn (array $row): bool => (int) ($row['contractor_id'] ?? 0) === $contractor->id));
    }

    public function test_quick_create_with_phone_only_creates_contact_contractor_and_pivot(): void
    {
        $payload = app(ClientLookupService::class)->quickCreate([
            'first_name' => 'Ewa',
            'last_name' => 'Test',
            'phone' => '600700800',
        ]);

        $this->assertCount(1, $payload['ordering_parties']);
        $this->assertNotEmpty($payload['client_name']);

        $contractorId = (int) $payload['ordering_parties'][0]['contractor_id'];
        $contactId = (int) $payload['ordering_parties'][0]['contact_id'];

        $this->assertDatabaseHas('contractors', [
            'id' => $contractorId,
            'phone' => '600700800',
        ]);

        $this->assertDatabaseHas('contacts', [
            'id' => $contactId,
            'first_name' => 'Ewa',
            'last_name' => 'Test',
        ]);

        $contractor = Contractor::query()->findOrFail($contractorId);
        $this->assertTrue(
            $contractor->types()->whereRaw('LOWER(name) = ?', ['klient'])->exists(),
            'Quick create powinien oznaczyć kontrahenta typem klient',
        );

        if (Contractor::hasContactPivotTable()) {
            $this->assertDatabaseHas(Contractor::contactPivotTable(), [
                'contact_id' => $contactId,
                'contractor_id' => $contractorId,
            ]);
        }
    }
}
