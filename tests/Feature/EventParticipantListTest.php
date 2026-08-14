<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventHotelRoomLine;
use App\Models\EventHotelStay;
use App\Models\EventParticipant;
use App\Models\HotelRoom;
use App\Models\User;
use App\Services\EventParticipantImporter;
use App\Services\EventParticipantPropagationService;
use App\Services\EventParticipantVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventParticipantListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
    }

    public function test_imports_participants_from_csv(): void
    {
        if (! Schema::hasTable('event_participants')) {
            $this->markTestSkipped('Tabela event_participants nie istnieje.');
        }

        $event = Event::factory()->create(['participant_count' => 5]);
        $csv = implode("\n", [
            'Imię,Nazwisko,Data urodzenia',
            'Jan,Kowalski,2010-05-15',
            'Anna,Nowak,12.03.2011',
        ]);
        $path = tempnam(sys_get_temp_dir(), 'participants');
        file_put_contents($path, $csv);

        $result = app(EventParticipantImporter::class)->importFromPath($event, $path);

        $this->assertSame(2, $result['imported']);
        $this->assertDatabaseCount('event_participants', 2);
        $this->assertDatabaseHas('event_participants', [
            'event_id' => $event->id,
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'source' => EventParticipant::SOURCE_IMPORT,
        ]);

        @unlink($path);
    }

    public function test_import_rejects_empty_file(): void
    {
        if (! Schema::hasTable('event_participants')) {
            $this->markTestSkipped('Tabela event_participants nie istnieje.');
        }

        $event = Event::factory()->create();
        $path = tempnam(sys_get_temp_dir(), 'participants');
        file_put_contents($path, "Imię,Nazwisko,Data urodzenia\n");

        $this->expectException(ValidationException::class);
        app(EventParticipantImporter::class)->importFromPath($event, $path);

        @unlink($path);
    }

    public function test_verification_detects_missing_links(): void
    {
        if (! Schema::hasTable('event_participants')) {
            $this->markTestSkipped('Tabela event_participants nie istnieje.');
        }

        $event = Event::factory()->create();
        EventParticipant::create([
            'event_id' => $event->id,
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'birth_date' => '2010-01-01',
            'source' => EventParticipant::SOURCE_MANUAL,
            'status' => EventParticipant::STATUS_ACTIVE,
        ]);

        $result = app(EventParticipantVerificationService::class)->verify($event);

        $this->assertSame(1, $result['summary']['roster_count']);
        $this->assertSame('missing', $result['rows'][0]['status']);
    }

    public function test_propagate_to_payments_creates_records(): void
    {
        if (! Schema::hasTable('event_participants')) {
            $this->markTestSkipped('Tabela event_participants nie istnieje.');
        }

        $event = Event::factory()->create();
        EventParticipant::create([
            'event_id' => $event->id,
            'first_name' => 'Ewa',
            'last_name' => 'Test',
            'source' => EventParticipant::SOURCE_IMPORT,
            'status' => EventParticipant::STATUS_ACTIVE,
        ]);

        $result = app(EventParticipantPropagationService::class)->propagateToPayments($event);

        $this->assertSame(1, $result['created']);
        $this->assertDatabaseHas('event_settlement_participant_payments', [
            'participant_name' => 'Ewa Test',
        ]);
    }

    public function test_propagate_to_hotel_assigns_empty_slots(): void
    {
        if (! Schema::hasTable('event_hotel_stays')) {
            $this->markTestSkipped('Brak modułu planu noclegów.');
        }

        $event = Event::factory()->create(['duration_days' => 2, 'participant_count' => 4]);
        $stay = EventHotelStay::create(['event_id' => $event->id, 'day' => 1]);
        $room = HotelRoom::create([
            'name' => 'Dwuosobowy',
            'people_count' => 2,
            'price' => 100,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);
        EventHotelRoomLine::create([
            'event_hotel_stay_id' => $stay->id,
            'hotel_room_id' => $room->id,
            'quantity' => 1,
            'unit_price' => 100,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        EventParticipant::create([
            'event_id' => $event->id,
            'first_name' => 'Piotr',
            'last_name' => 'Hotel',
            'source' => EventParticipant::SOURCE_MANUAL,
            'status' => EventParticipant::STATUS_ACTIVE,
        ]);

        $result = app(EventParticipantPropagationService::class)->propagateToHotelPlan($event);

        $this->assertSame(1, $result['assigned']);
        $this->assertDatabaseHas('event_hotel_room_occupants', [
            'name' => 'Piotr Hotel',
        ]);
    }

    public function test_propagate_to_hotel_assigns_participants_on_each_night(): void
    {
        if (! Schema::hasTable('event_hotel_stays')) {
            $this->markTestSkipped('Brak modułu planu noclegów.');
        }

        $event = Event::factory()->create(['duration_days' => 3, 'participant_count' => 2]);

        foreach ([1, 2] as $day) {
            $stay = EventHotelStay::create(['event_id' => $event->id, 'day' => $day]);
            $room = HotelRoom::create([
                'name' => 'Dwuosobowy '.$day,
                'people_count' => 2,
                'price' => 100,
                'currency' => 'PLN',
                'convert_to_pln' => true,
            ]);
            EventHotelRoomLine::create([
                'event_hotel_stay_id' => $stay->id,
                'hotel_room_id' => $room->id,
                'role' => 'qty',
                'quantity' => 1,
                'unit_price' => 100,
                'convert_to_pln' => true,
                'order' => 0,
            ]);
        }

        EventParticipant::create([
            'event_id' => $event->id,
            'first_name' => 'Anna',
            'last_name' => 'Noc',
            'source' => EventParticipant::SOURCE_MANUAL,
            'status' => EventParticipant::STATUS_ACTIVE,
        ]);

        $result = app(EventParticipantPropagationService::class)->propagateToHotelPlan($event);

        $this->assertSame(2, $result['assigned']);
        $this->assertSame(2, \App\Models\EventHotelRoomOccupant::query()->where('name', 'Anna Noc')->count());
    }

    public function test_participants_page_is_registered_in_event_workflow(): void
    {
        if (! Schema::hasTable('event_participants')) {
            $this->markTestSkipped('Tabela event_participants nie istnieje.');
        }

        $pages = \App\Filament\Resources\EventResource::getPages();

        $this->assertArrayHasKey('participants', $pages);
        $this->assertArrayHasKey('participant-payments', $pages);
        $this->assertArrayHasKey('participant-resignations', $pages);
        $this->assertArrayHasKey('participant-portal', $pages);
    }

    public function test_legacy_participant_routes_redirect_to_new_urls(): void
    {
        if (! Schema::hasTable('event_participants')) {
            $this->markTestSkipped('Tabela event_participants nie istnieje.');
        }

        $event = Event::factory()->create();
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $this->get(\App\Filament\Resources\EventResource::getUrl('resignations', ['record' => $event->id]))
            ->assertRedirect(\App\Filament\Resources\EventResource::getUrl('participant-resignations', ['record' => $event->id]));

        $this->get(\App\Filament\Resources\EventResource::getUrl('client-portal', ['record' => $event->id]))
            ->assertRedirect(\App\Filament\Resources\EventResource::getUrl('participant-portal', ['record' => $event->id]));

        $this->get(\App\Filament\Resources\EventResource::getUrl('settlement-payments', ['record' => $event->id]))
            ->assertRedirect(\App\Filament\Resources\EventResource::getUrl('finance-participant-payments', ['record' => $event->id]));

        $this->get(\App\Filament\Resources\EventResource::getUrl('participant-payments', ['record' => $event->id]))
            ->assertRedirect(\App\Filament\Resources\EventResource::getUrl('finance-participant-payments', ['record' => $event->id]));
    }

    public function test_participant_payments_page_contains_bank_import_panel(): void
    {
        if (! Schema::hasTable('event_participants')) {
            $this->markTestSkipped('Tabela event_participants nie istnieje.');
        }

        $event = Event::factory()->create();
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $this->get(\App\Filament\Resources\EventResource::getUrl('finance-participant-payments', ['record' => $event->id]))
            ->assertOk()
            ->assertSee('Millennium');
    }

    public function test_participants_workflow_context_matches_standard_event_header(): void
    {
        if (! Schema::hasTable('event_participants')) {
            $this->markTestSkipped('Tabela event_participants nie istnieje.');
        }

        $event = Event::factory()->create(['code' => 'TEST-WF', 'name' => 'Impreza testowa']);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $paymentsPage = new \App\Filament\Resources\EventResource\Pages\EventFinanceParticipantPayments;
        $paymentsPage->record = $event;

        $documentsPage = new \App\Filament\Resources\EventResource\Pages\ManageEventDocuments;
        $documentsPage->record = $event;

        $paymentsContext = $paymentsPage->getWorkflowContext();
        $documentsContext = $documentsPage->getWorkflowContext();

        $this->assertNotNull($paymentsContext);
        $this->assertSame($documentsContext['title'], $paymentsContext['title']);
        $this->assertSame($documentsContext['subtitle'], $paymentsContext['subtitle']);
        $this->assertSame($documentsContext['title_url'], $paymentsContext['title_url']);
        $this->assertNotNull($paymentsContext['finance'] ?? null);
    }

    public function test_workflow_context_includes_operational_contacts(): void
    {
        $pilotUser = User::factory()->create([
            'status' => 'active',
            'name' => 'Jan Pilot',
            'phone' => '500600700',
        ]);

        $hotelA = \App\Models\Contractor::create(['name' => 'Hotel Alfa', 'status' => 'active']);
        $hotelB = \App\Models\Contractor::create(['name' => 'Hotel Beta', 'status' => 'active']);

        $event = Event::factory()->create([
            'code' => 'OPS-1',
            'name' => 'Impreza kontakty',
            'client_name' => 'Anna Zamawiająca',
            'client_phone' => '111222333',
            'assigned_to' => $pilotUser->id,
            'driver_name' => 'Piotr Kierowca',
            'driver_phone' => '444555666',
        ]);

        $event->hotelStays()->create(['day' => 1, 'contractor_id' => $hotelA->id]);
        $event->hotelStays()->create(['day' => 2, 'contractor_id' => $hotelB->id]);
        $event->hotelStays()->create(['day' => 3, 'contractor_id' => $hotelA->id]);

        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $page = new \App\Filament\Resources\EventResource\Pages\ManageEventDocuments;
        $page->record = $event->fresh();

        $context = $page->getWorkflowContext();
        $this->assertNotNull($context);

        $metaByLabel = collect($context['meta'])->mapWithKeys(
            fn (array $item) => [$item['label'] => $item['value']]
        );

        $this->assertSame('Anna Zamawiająca · 111222333', $metaByLabel->get('Zamawiający'));
        $this->assertSame('Jan Pilot · 500600700', $metaByLabel->get('Pilot'));
        $this->assertSame('Piotr Kierowca · 444555666', $metaByLabel->get('Kierowca'));
        $this->assertSame('Hotel Alfa (+1)', $metaByLabel->get('Hotel'));
    }
}
