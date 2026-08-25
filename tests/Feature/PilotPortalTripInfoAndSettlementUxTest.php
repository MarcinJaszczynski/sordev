<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pilot\Resources\PilotEventResource;
use App\Livewire\PilotCashDesk;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\Reservation;
use App\Models\User;
use App\Services\PilotSettlementService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PilotPortalTripInfoAndSettlementUxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'pilot', 'guard_name' => 'web']);
        Filament::setCurrentPanel(Filament::getPanel('pilot'));
    }

    public function test_pilot_trip_info_shows_pickup_times_and_hides_km(): void
    {
        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $payload = [
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
            'name' => 'Wycieczka UX',
        ];

        if (Schema::hasColumn('events', 'pickup_place_details')) {
            $payload['pickup_place_details'] = '<p>ul. Testowa 1, brama B</p>';
        }
        if (Schema::hasColumn('events', 'substitution_time')) {
            $payload['substitution_time'] = '07:15:00';
        }
        if (Schema::hasColumn('events', 'departure_time')) {
            $payload['departure_time'] = '07:45:00';
        }
        if (Schema::hasColumn('events', 'return_time')) {
            $payload['return_time'] = '19:30:00';
        }
        if (Schema::hasColumn('events', 'transfer_km')) {
            $payload['transfer_km'] = 120;
        }
        if (Schema::hasColumn('events', 'program_km')) {
            $payload['program_km'] = 80;
        }
        if (Schema::hasColumn('events', 'office_notes')) {
            $payload['office_notes'] = '<p>TAJNE UWAGI DLA BIURA - nie dla pilota</p>';
        }
        if (Schema::hasColumn('events', 'pilot_notes')) {
            $payload['pilot_notes'] = '<p>Uwagi widoczne dla pilota - checklista</p>';
        }

        $event = Event::factory()->create($payload);

        $this->actingAs($pilot)
            ->get(PilotEventResource::getUrl('view', ['record' => $event], panel: 'pilot'))
            ->assertOk()
            ->assertSee('Adres podstawienia autokaru')
            ->assertSee('ul. Testowa 1, brama B')
            ->assertSee('Podstawienie')
            ->assertSee('07:15')
            ->assertSee('Wyjazd')
            ->assertSee('07:45')
            ->assertSee('Powrót')
            ->assertSee('19:30')
            ->assertSee('Planowana liczba uczestników')
            ->assertDontSee('Km transferu')
            ->assertDontSee('Km programu')
            ->assertDontSee('Miejsce startu')
            ->assertSee('Uwagi widoczne dla pilota - checklista')
            ->assertDontSee('TAJNE UWAGI DLA BIURA')
            ->assertDontSee('Uwagi dla biura');

        if (Schema::hasTable('event_hotel_stays') && Schema::hasTable('contractors')) {
            $hotel = \App\Models\Contractor::create([
                'name' => 'Hotel Testowy Panorama',
                'street' => 'ul. Noclegowa',
                'house_number' => '12',
                'postal_code' => '00-001',
                'city' => 'Warszawa',
                'status' => 'active',
            ]);

            \App\Models\EventHotelStay::create([
                'event_id' => $event->id,
                'day' => 1,
                'contractor_id' => $hotel->id,
            ]);

            $this->actingAs($pilot)
                ->get(PilotEventResource::getUrl('view', ['record' => $event->fresh()], panel: 'pilot'))
                ->assertOk()
                ->assertSee('Hotele')
                ->assertSee('Hotel Testowy Panorama')
                ->assertSee('ul. Noclegowa 12')
                ->assertSee('00-001 Warszawa');
        }
    }

    public function test_pilot_program_shows_reservation_status(): void
    {
        if (! Schema::hasTable('reservations') || ! Schema::hasTable('event_program_points')) {
            $this->markTestSkipped('Brak tabel programu/rezerwacji.');
        }

        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $payload = [
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-12',
            'duration_days' => 3,
            'departure_time' => '07:45:00',
        ];
        if (Schema::hasColumn('events', 'program_day_routes')) {
            $payload['program_day_routes'] = [
                '1' => 'Warszawa – Paryż',
            ];
        }
        if (Schema::hasColumn('events', 'pickup_place_details')) {
            $payload['pickup_place_details'] = '<p>Plac Defilad 1</p>';
        }
        if (Schema::hasColumn('events', 'substitution_time')) {
            $payload['substitution_time'] = '07:15:00';
        }
        if (Schema::hasColumn('events', 'return_time')) {
            $payload['return_time'] = '19:30:00';
        }

        $event = Event::factory()->create($payload);

        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'event_template_program_point_id' => null,
            'name' => 'Muzeum Testowe',
            'day' => 1,
            'start_time' => '11:00:00',
            'active' => true,
            'include_in_program' => true,
        ]);

        $reservation = new Reservation([
            'event_id' => $event->id,
            'program_point_id' => $point->id,
            'status' => 'confirmed',
            'booking_reference' => 'MUS-9',
            'reserved_at' => now(),
        ]);
        $reservation->saveQuietly();

        $this->actingAs($pilot);

        $html = view('pilot.partials.trip-program-timeline', [
            'event' => $event->fresh(['startPlace']),
        ])->render();

        $this->assertStringContainsString('Muzeum Testowe', $html);
        $this->assertStringContainsString('Rez. potwierdzona', $html);
        $this->assertStringContainsString('godz. 11:00', $html);
        $this->assertStringContainsString('nr MUS-9', $html);
        $this->assertStringContainsString('Podstawienie i wyjazd', $html);
        $this->assertStringContainsString('Powrót / podstawienie', $html);
        $this->assertStringContainsString('Plac Defilad 1', $html);
        $this->assertStringContainsString('07:15', $html);
        $this->assertStringContainsString('07:45', $html);
        $this->assertStringContainsString('19:30', $html);
        $this->assertStringContainsString('10.08.2026', $html);
        $this->assertStringContainsString('12.08.2026', $html);
        $this->assertStringNotContainsString('Brak rezerwacji', $html);
        $this->assertStringContainsString('Trasa: Warszawa – Paryż', $html);
    }

    public function test_pilot_trip_info_shows_program_day_routes(): void
    {
        if (! Schema::hasColumn('events', 'program_day_routes')) {
            $this->markTestSkipped('Kolumna program_day_routes nie istnieje.');
        }

        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
            'duration_days' => 3,
            'program_day_routes' => [
                '1' => 'Warszawa – Paryż (nocny przejazd)',
                '2' => 'Paryż bez autokaru',
                '3' => 'Paryż – Warszawa',
            ],
        ]);

        $this->actingAs($pilot)
            ->get(PilotEventResource::getUrl('view', ['record' => $event], panel: 'pilot'))
            ->assertOk()
            ->assertSee('Trasy przejazdu')
            ->assertSee('Dzień 1')
            ->assertSee('Warszawa – Paryż (nocny przejazd)')
            ->assertSee('Paryż bez autokaru')
            ->assertSee('Paryż – Warszawa');
    }

    public function test_pilot_program_return_date_uses_duration_when_end_date_stale(): void
    {
        if (! Schema::hasTable('event_program_points')) {
            $this->markTestSkipped('Brak tabeli punktów programu.');
        }

        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-10', // niesynchroniczne z duration
            'duration_days' => 5,
        ]);

        EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'event_template_program_point_id' => null,
            'name' => 'Punkt dnia 1',
            'day' => 1,
            'active' => true,
            'include_in_program' => true,
        ]);

        $html = view('pilot.partials.trip-program-timeline', [
            'event' => $event->fresh(),
        ])->render();

        $this->assertStringContainsString('Podstawienie i wyjazd', $html);
        $this->assertStringContainsString('10.09.2026', $html);
        $this->assertStringContainsString('Powrót / podstawienie', $html);
        $this->assertStringContainsString('14.09.2026', $html);
    }

    public function test_pilot_cash_summary_hides_needed_column_and_marks_unplanned_expense(): void
    {
        if (! Schema::hasTable('event_settlement_costs')) {
            $this->markTestSkipped('Tabela event_settlement_costs nie istnieje.');
        }

        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
        ]);

        $this->actingAs($pilot);

        app(PilotSettlementService::class)->addExpense($event, [
            'name' => 'Parking awaryjny',
            'actual_amount' => 35,
        ]);

        Livewire::test(PilotCashDesk::class, [
            'event' => $event,
            'context' => 'pilot',
            'compact' => true,
            'showOfficePayoutBlock' => false,
        ])
            ->assertOk()
            ->assertDontSee('Do przygotowania')
            ->assertSee('Rozliczenie zaliczki od biura')
            ->assertSee('Parking awaryjny')
            ->assertSee('Nieplanowany')
            ->assertSee('Dodaj wydatek');
    }

    public function test_pilot_expense_ledger_highlights_office_advance_and_defaults_to_top_up(): void
    {
        if (! Schema::hasTable('event_settlement_costs')) {
            $this->markTestSkipped('Tabela event_settlement_costs nie istnieje.');
        }

        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $admin = User::factory()->create(['status' => 'active']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->assignRole('admin');

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
        ]);

        $settlement = \App\Models\EventSettlement::findOrCreateActiveForEvent($event);
        $plnId = \App\Models\Currency::query()->firstOrCreate(
            ['code' => 'PLN'],
            ['name' => 'PLN', 'symbol' => 'PLN', 'exchange_rate' => 1],
        )->id;

        $hotel = $settlement->costs()->create([
            'source_type' => 'program_point',
            'source_id' => 9901,
            'name' => 'Hotel z zaliczką',
            'planned_amount' => 800,
            'planned_amount_pln' => 800,
            'planned_currency_id' => $plnId,
            'paid_by' => 'pilot',
            'payment_status' => 'planned',
            'order' => 1,
        ]);

        $this->actingAs($admin);
        app(\App\Actions\Finance\RecordSettlementCostPaymentAction::class)(new \App\Data\RecordSettlementCostPaymentData(
            planCost: $hotel->fresh(['plannedCurrency']),
            amountPln: 300,
            paymentMethod: 'transfer',
            paidBy: 'office',
            advanceType: 'advance',
            paidAt: now(),
            paidByUserId: $admin->id,
            amount: 300,
            rate: 1,
            currencyId: $plnId,
        ));

        $this->actingAs($pilot);

        Livewire::test(PilotCashDesk::class, [
            'event' => $event->fresh(),
            'context' => 'pilot',
            'compact' => true,
            'showOfficePayoutBlock' => false,
        ])
            ->assertOk()
            ->assertSee('Hotel z zaliczką')
            ->assertSee('Zaliczka biura')
            ->assertSee('Biuro wpłaciło')
            ->assertSee('300')
            ->assertSee('pilot dopłaca')
            ->assertSee('500')
            ->call('startEditCost', $hotel->id)
            ->assertSet('editCostActualAmount', '500')
            ->assertSee('Biuro wpłaciło zaliczkę')
            ->assertSee('Nie płacisz pełnej kwoty planu');
    }
}
