<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventDocument;
use App\Models\EventHotelStay;
use App\Models\Place;
use App\Models\User;
use App\Services\Documents\WordAgreementContent;
use App\Services\EventOrderingPartyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use ZipArchive;

class EventAgreementWordContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_agreement_word_contains_pdf_template_content(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $place = Place::factory()->create([
            'name' => 'Warszawa, ul. Podchorążych 83',
        ]);

        $event = Event::factory()->create([
            'name' => 'Karkonosze, Praga, Skalne Miasto',
            'code' => '20260420/1001',
            'participant_count' => 42,
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-11',
            'departure_time' => '07:00',
            'substitution_time' => '06:30',
            'return_time' => '20:00',
            'start_place_id' => $place->id,
            'pickup_place_details' => 'Warszawa, ul. Podchorążych 83 (zatoki parkingowe przy Łazienkach Królewskich)',
            'created_by' => $user->id,
            'assigned_to' => $user->id,
        ]);

        if (Schema::hasTable('event_contractor') && Schema::hasTable('contacts')) {
            $company = Contractor::create(['name' => 'Szkoła Testowa', 'status' => 'active']);
            $contact = Contact::create([
                'first_name' => 'Joanna',
                'last_name' => 'Budkiewicz',
                'phone' => '500600700',
                'email' => 'joanna@example.com',
            ]);
            app(EventOrderingPartyService::class)->syncForEvent($event, [[
                'contact_id' => $contact->id,
                'contractor_id' => $company->id,
                'department_label' => null,
                'notes' => null,
                'goes_on_trip' => true,
            ]]);
        }

        if (Schema::hasTable('event_hotel_stays') && Schema::hasTable('contractors')) {
            $hotel = Contractor::create([
                'name' => 'Dom Pod koziołkami',
                'status' => 'active',
                'street' => 'Kasprowicza',
                'house_number' => '13 F',
                'postal_code' => '58-580',
                'city' => 'Szklarska Poręba',
            ]);
            EventHotelStay::query()->create([
                'event_id' => $event->id,
                'contractor_id' => $hotel->id,
                'day' => 1,
            ]);
        }

        $response = $this->get(route('admin.events.agreement.word', $event));
        $response->assertOk();

        $document = EventDocument::query()
            ->where('event_id', $event->id)
            ->where('name', 'like', 'Umowa — %')
            ->latest('id')
            ->first();

        $this->assertNotNull($document);
        $path = storage_path('app/public/'.$document->file_path);
        $this->assertFileExists($path);

        $content = file_get_contents($path);
        $this->assertNotFalse($content);
        $this->assertSame('PK', substr($content, 0, 2));

        $xml = $this->docxDocumentXml($path);

        $this->assertStringContainsString('Potwierdzenie zawarcia umowy o organizację imprezy turystycznej', $xml);
        $this->assertStringContainsString('20260420/1001', $xml);
        $this->assertStringContainsString('Karkonosze, Praga, Skalne Miasto', $xml);
        $this->assertStringContainsString('Informacje o imprezie turystycznej', $xml);
        $this->assertStringContainsString('Podstawienie i miejsce zbiórki', $xml);
        $this->assertStringContainsString('Cena imprezy i harmonogram wpłat', $xml);
        $this->assertStringContainsString('Ogólne Warunki Uczestnictwa', $xml);
        $this->assertStringContainsString('STANDARDOWY FORMULARZ INFORMACYJNY', $xml);
        $this->assertStringContainsString('Warunki Uczestnictwa obowiązują do umów zawartych po 20 lutego 2026', $xml);
        $this->assertStringContainsString('Biuro Podróży RAFA', $xml);
        $this->assertStringContainsString('10 1160 2202 0000 0002 0065 6958', $xml);
        $this->assertStringContainsString('Dokument wygenerowany elektronicznie', $xml);

        if (Schema::hasTable('event_contractor') && Schema::hasTable('contacts')) {
            $this->assertStringContainsString('Joanna Budkiewicz', $xml);
        }
    }

    public function test_amount_in_words_uses_polish_spellout(): void
    {
        $content = new WordAgreementContent;
        $words = $content->amountInWordsPln(79170);

        $this->assertStringContainsString('złotych brutto', $words);
        $this->assertMatchesRegularExpression('/^[A-ZĄĆĘŁŃÓŚŹŻ]/u', $words);
    }

    private function docxDocumentXml(string $path): string
    {
        $this->assertTrue(class_exists(ZipArchive::class), 'ZipArchive required');

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertNotFalse($xml);

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
