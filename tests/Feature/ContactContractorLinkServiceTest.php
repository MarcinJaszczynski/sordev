<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Contractor;
use App\Services\ContactContractorLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactContractorLinkServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_link_creates_pivot_row(): void
    {
        if (! Contractor::hasContactPivotTable()) {
            $this->markTestSkipped('Brak tabeli pivot contractor_contact.');
        }

        $contact = Contact::create([
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
        ]);

        $contractor = Contractor::create([
            'name' => 'Szkoła Podstawowa nr 1',
            'status' => 'active',
        ]);

        app(ContactContractorLinkService::class)->link($contact->id, $contractor->id);

        $this->assertDatabaseHas(Contractor::contactPivotTable(), [
            'contact_id' => $contact->id,
            'contractor_id' => $contractor->id,
        ]);
    }

    public function test_link_is_idempotent(): void
    {
        if (! Contractor::hasContactPivotTable()) {
            $this->markTestSkipped('Brak tabeli pivot contractor_contact.');
        }

        $contact = Contact::create([
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
        ]);

        $contractor = Contractor::create([
            'name' => 'Firma Test',
            'status' => 'active',
        ]);

        $service = app(ContactContractorLinkService::class);
        $service->link($contact->id, $contractor->id);
        $service->link($contact->id, $contractor->id);

        $this->assertSame(
            1,
            (int) \Illuminate\Support\Facades\DB::table(Contractor::contactPivotTable())
                ->where('contact_id', $contact->id)
                ->where('contractor_id', $contractor->id)
                ->count()
        );
    }

    public function test_link_parties_links_multiple_pairs(): void
    {
        if (! Contractor::hasContactPivotTable()) {
            $this->markTestSkipped('Brak tabeli pivot contractor_contact.');
        }

        $contactA = Contact::create(['first_name' => 'A', 'last_name' => 'One']);
        $contactB = Contact::create(['first_name' => 'B', 'last_name' => 'Two']);
        $contractorA = Contractor::create(['name' => 'Firma A', 'status' => 'active']);
        $contractorB = Contractor::create(['name' => 'Firma B', 'status' => 'active']);

        app(ContactContractorLinkService::class)->linkParties([
            ['contact_id' => $contactA->id, 'contractor_id' => $contractorA->id],
            ['contact_id' => $contactB->id, 'contractor_id' => $contractorB->id],
        ]);

        $this->assertDatabaseHas(Contractor::contactPivotTable(), [
            'contact_id' => $contactA->id,
            'contractor_id' => $contractorA->id,
        ]);

        $this->assertDatabaseHas(Contractor::contactPivotTable(), [
            'contact_id' => $contactB->id,
            'contractor_id' => $contractorB->id,
        ]);
    }

    public function test_link_skips_when_ids_missing(): void
    {
        if (! Contractor::hasContactPivotTable()) {
            $this->markTestSkipped('Brak tabeli pivot contractor_contact.');
        }

        $contractor = Contractor::create(['name' => 'Firma', 'status' => 'active']);

        app(ContactContractorLinkService::class)->link(null, $contractor->id);

        $this->assertSame(0, (int) \Illuminate\Support\Facades\DB::table(Contractor::contactPivotTable())->count());
    }
}
