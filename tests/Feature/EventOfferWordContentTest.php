<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Contractor;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventDocument;
use App\Models\EventHotelStay;
use App\Models\EventProgramPoint;
use App\Models\EventTemplate;
use App\Models\EventTemplatePricePerPerson;
use App\Models\EventTemplateQty;
use App\Models\EventType;
use App\Models\User;
use App\Services\EventOrderingPartyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use ZipArchive;

class EventOfferWordContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_offer_word_contains_qa_required_content(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $template = EventTemplate::factory()->create([
            'is_active' => true,
            'duration_days' => 1,
            'name' => 'Szablon jednodniowy',
        ]);

        $pln = Currency::factory()->create([
            'code' => 'PLN',
            'symbol' => 'PLN',
            'name' => 'Polski złoty',
        ]);
        $eur = Currency::factory()->create([
            'code' => 'EUR',
            'symbol' => 'EUR',
            'name' => 'Euro',
        ]);

        foreach ([20, 25, 30, 35, 40] as $qty) {
            $variant = EventTemplateQty::query()->firstOrCreate(
                ['qty' => $qty],
                ['gratis' => (int) ceil($qty / 15), 'staff' => 0, 'driver' => 0]
            );

            EventTemplatePricePerPerson::query()->create([
                'event_template_id' => $template->id,
                'event_template_qty_id' => $variant->id,
                'start_place_id' => null,
                'currency_id' => $pln->id,
                'price_per_person' => 400 - ($qty / 5),
            ]);

            EventTemplatePricePerPerson::query()->create([
                'event_template_id' => $template->id,
                'event_template_qty_id' => $variant->id,
                'start_place_id' => null,
                'currency_id' => $eur->id,
                'price_per_person' => 40,
            ]);
        }

        $event = Event::factory()->create([
            'name' => 'Wycieczka Testowa QA',
            'event_template_id' => $template->id,
            'duration_days' => 1,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'created_by' => $user->id,
            'assigned_to' => $user->id,
        ]);

        EventProgramPoint::query()->create([
            'event_id' => $event->id,
            'name' => 'Zwiedzanie rynku',
            'description' => 'Opis rynku',
            'day' => 1,
            'order' => 1,
            'include_in_program' => true,
            'active' => true,
            'show_title_style' => true,
            'show_description' => true,
        ]);

        EventProgramPoint::query()->create([
            'event_id' => $event->id,
            'name' => 'Opcja muzeum',
            'description' => 'Fakultatyw',
            'day' => 2,
            'order' => 1,
            'include_in_program' => true,
            'active' => true,
            'show_title_style' => true,
            'show_description' => true,
        ]);

        if (Schema::hasTable('event_contractor') && Schema::hasTable('contacts')) {
            $company = Contractor::create(['name' => 'Szkoła Podstawowa nr 7', 'status' => 'active']);
            $contact = Contact::create([
                'first_name' => 'Anna',
                'last_name' => 'Nowak',
                'phone' => '500600700',
                'email' => 'anna@example.com',
            ]);
            app(EventOrderingPartyService::class)->syncForEvent($event, [[
                'contact_id' => $contact->id,
                'contractor_id' => $company->id,
                'department_label' => null,
                'notes' => null,
                'goes_on_trip' => true,
            ]]);
        }

        $response = $this->get(route('admin.events.offer.word', $event));
        $response->assertOk();

        $document = EventDocument::query()
            ->where('event_id', $event->id)
            ->where('is_offer', true)
            ->latest('id')
            ->first();

        $this->assertNotNull($document);
        $this->assertStringContainsString('Oferta wycieczki - ', (string) $document->name);

        $path = storage_path('app/public/'.$document->file_path);
        $this->assertFileExists($path);

        $xml = $this->docxDocumentXml($path);

        $this->assertStringContainsString('Oferta wycieczki', $xml);
        $this->assertStringNotContainsString('OFERTA IMPREZY', $xml);
        $this->assertStringNotContainsString('Oferta imprezy', $xml);
        $this->assertStringContainsString('Wycieczka Testowa QA', $xml);
        $this->assertStringContainsString('Program wycieczki', $xml);
        $this->assertStringContainsString('Fakultatywnie proponujemy:', $xml);
        $this->assertStringNotContainsString('Dzień 2', $xml);
        $this->assertStringNotContainsString('Zapytaj o ofertę', $xml);
        $this->assertStringNotContainsString('Główny:', $xml);
        $this->assertStringContainsString('UWAGI', $xml);
        $this->assertStringNotContainsString('WARUNKI OFERTY', $xml);
        $this->assertStringContainsString('10 dni od daty przygotowania oferty', $xml);
        $this->assertStringContainsString('Biuro Podróży RAFA', $xml);
        $this->assertStringContainsString('ul. Marii Konopnickiej 6', $xml);
        $this->assertStringContainsString('00-491 Warszawa', $xml);
        $this->assertStringContainsString('PLN + 40 EUR za osobę dla grupy 46–55 uczestników', $xml);
        $this->assertStringContainsString('PLN + 40 EUR za osobę dla grupy 40–45 uczestników', $xml);
        $this->assertStringContainsString('Zwiedzanie rynku', $xml);

        if (Schema::hasTable('event_contractor') && Schema::hasTable('contacts')) {
            $this->assertStringContainsString('Szkoła Podstawowa nr 7', $xml);
            $this->assertStringContainsString('Anna Nowak', $xml);
            $this->assertStringNotContainsString('tel. 500600700', $xml);
            $this->assertStringNotContainsString('anna@example.com', $xml);
        }

        // Jednodniowa — bez sekcji zakwaterowania.
        $this->assertStringNotContainsString('ZAKWATEROWANIE', $xml);
    }

    public function test_program_set_keeps_parent_then_children_order_like_on_page(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $event = Event::factory()->create([
            'name' => 'Oferta z setem',
            'duration_days' => 1,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'created_by' => $user->id,
            'assigned_to' => $user->id,
        ]);

        // Flat order po samym `order` dałoby: Dziecko A, Zbiórka, Dziecko B, Set Muzeum
        EventProgramPoint::query()->create([
            'event_id' => $event->id,
            'name' => 'Zbiórka',
            'day' => 1,
            'order' => 2,
            'include_in_program' => true,
            'active' => true,
            'show_title_style' => true,
            'show_description' => false,
        ]);

        $set = EventProgramPoint::query()->create([
            'event_id' => $event->id,
            'name' => 'Set Muzeum',
            'day' => 1,
            'order' => 4,
            'include_in_program' => true,
            'active' => true,
            'show_title_style' => true,
            'show_description' => false,
        ]);

        EventProgramPoint::query()->create([
            'event_id' => $event->id,
            'name' => 'Dziecko B',
            'day' => 1,
            'order' => 3,
            'parent_id' => $set->id,
            'include_in_program' => true,
            'active' => true,
            'show_title_style' => true,
            'show_description' => false,
        ]);

        EventProgramPoint::query()->create([
            'event_id' => $event->id,
            'name' => 'Dziecko A',
            'day' => 1,
            'order' => 1,
            'parent_id' => $set->id,
            'include_in_program' => true,
            'active' => true,
            'show_title_style' => true,
            'show_description' => false,
        ]);

        $response = $this->get(route('admin.events.offer.word', $event));
        $response->assertOk();

        $document = EventDocument::query()
            ->where('event_id', $event->id)
            ->where('is_offer', true)
            ->latest('id')
            ->first();

        $xml = $this->docxDocumentXml(storage_path('app/public/'.$document->file_path));

        $posGather = strpos($xml, 'Zbiórka');
        $posSet = strpos($xml, 'Set Muzeum');
        $posChildA = strpos($xml, 'Dziecko A');
        $posChildB = strpos($xml, 'Dziecko B');

        $this->assertNotFalse($posGather);
        $this->assertNotFalse($posSet);
        $this->assertNotFalse($posChildA);
        $this->assertNotFalse($posChildB);

        // Jak na stronie: Zbiórka → Set → dzieci w kolejności order wewnątrz setu
        $this->assertTrue($posGather < $posSet);
        $this->assertTrue($posSet < $posChildA);
        $this->assertTrue($posChildA < $posChildB);
    }

    public function test_facultative_section_printed_only_once_even_with_split_days(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $event = Event::factory()->create([
            'name' => 'Oferta z fakultatywem',
            'duration_days' => 1,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'created_by' => $user->id,
            'assigned_to' => $user->id,
        ]);

        EventProgramPoint::query()->create([
            'event_id' => $event->id,
            'name' => 'Program dnia',
            'day' => 1,
            'order' => 1,
            'include_in_program' => true,
            'active' => true,
            'show_title_style' => true,
            'show_description' => false,
        ]);

        $set = EventProgramPoint::query()->create([
            'event_id' => $event->id,
            'name' => 'Set fakultatywny',
            'day' => 2,
            'order' => 1,
            'include_in_program' => true,
            'active' => true,
            'show_title_style' => true,
            'show_description' => false,
        ]);

        // Dziecko na day=3 (> core) — wcześniej tworzyło drugi nagłówek fakultatywny.
        EventProgramPoint::query()->create([
            'event_id' => $event->id,
            'name' => 'Opcja w secie',
            'day' => 3,
            'order' => 1,
            'parent_id' => $set->id,
            'include_in_program' => true,
            'active' => true,
            'show_title_style' => true,
            'show_description' => false,
        ]);

        EventProgramPoint::query()->create([
            'event_id' => $event->id,
            'name' => 'Inna opcja fakultatywna',
            'day' => 4,
            'order' => 1,
            'include_in_program' => true,
            'active' => true,
            'show_title_style' => true,
            'show_description' => false,
        ]);

        $response = $this->get(route('admin.events.offer.word', $event));
        $response->assertOk();

        $document = EventDocument::query()
            ->where('event_id', $event->id)
            ->where('is_offer', true)
            ->latest('id')
            ->first();

        $xml = $this->docxDocumentXml(storage_path('app/public/'.$document->file_path));

        $this->assertSame(1, substr_count($xml, 'Fakultatywnie proponujemy:'));
        $this->assertStringContainsString('Set fakultatywny', $xml);
        $this->assertStringContainsString('Opcja w secie', $xml);
        $this->assertStringContainsString('Inna opcja fakultatywna', $xml);
        $this->assertStringNotContainsString('Dzień 2', $xml);
        $this->assertStringNotContainsString('Dzień 3', $xml);
    }

    public function test_multi_day_offer_shows_accommodation_hotels_list(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $event = Event::factory()->create([
            'name' => 'Wyjazd 3-dniowy',
            'duration_days' => 3,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(12)->toDateString(),
            'created_by' => $user->id,
            'assigned_to' => $user->id,
        ]);

        if (! Schema::hasTable('event_hotel_stays')) {
            $this->markTestSkipped('Brak event_hotel_stays');
        }

        $hotelA = Contractor::create(['name' => 'Hotel Mazowsze', 'status' => 'active']);
        $hotelB = Contractor::create(['name' => 'Pensjonat Latarnia', 'status' => 'active']);

        EventHotelStay::query()->create([
            'event_id' => $event->id,
            'day' => 1,
            'contractor_id' => $hotelA->id,
        ]);
        EventHotelStay::query()->create([
            'event_id' => $event->id,
            'day' => 2,
            'contractor_id' => $hotelB->id,
        ]);

        $response = $this->get(route('admin.events.offer.word', $event));
        $response->assertOk();

        $document = EventDocument::query()
            ->where('event_id', $event->id)
            ->where('is_offer', true)
            ->latest('id')
            ->first();

        $xml = $this->docxDocumentXml(storage_path('app/public/'.$document->file_path));

        $this->assertStringContainsString('ZAKWATEROWANIE', $xml);
        $this->assertStringContainsString('Hotel Mazowsze', $xml);
        $this->assertStringContainsString('Pensjonat Latarnia', $xml);
        $this->assertStringContainsString('pokoje maksymalnie 4-osobowe', $xml);
    }

    public function test_foreign_trip_notes_include_passport_requirement(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $template = EventTemplate::factory()->create([
            'is_active' => true,
            'duration_days' => 2,
        ]);

        $foreignType = EventType::query()->firstOrCreate(
            ['name' => 'zagraniczne'],
            ['name' => 'zagraniczne']
        );
        $template->eventTypes()->sync([$foreignType->id]);

        $event = Event::factory()->create([
            'name' => 'Wyjazd zagraniczny',
            'event_template_id' => $template->id,
            'duration_days' => 2,
            'start_date' => now()->addDays(20)->toDateString(),
            'end_date' => now()->addDays(21)->toDateString(),
            'created_by' => $user->id,
            'assigned_to' => $user->id,
        ]);

        $response = $this->get(route('admin.events.offer.word', $event));
        $response->assertOk();

        $document = EventDocument::query()
            ->where('event_id', $event->id)
            ->where('is_offer', true)
            ->latest('id')
            ->first();

        $xml = $this->docxDocumentXml(storage_path('app/public/'.$document->file_path));
        $this->assertStringContainsString('dowód osobisty lub paszport', $xml);
    }

    private function docxDocumentXml(string $path): string
    {
        $this->assertTrue(class_exists(ZipArchive::class));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertNotSame('', $xml);

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
