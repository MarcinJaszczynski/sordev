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

        $event = Event::factory()->create($payload);

        $this->actingAs($pilot)
            ->get(PilotEventResource::getUrl('view', ['record' => $event], panel: 'pilot'))
            ->assertOk()
            ->assertSee('Adres podstawienia autokaru')
            ->assertSee('ul. Testowa 1, brama B')
            ->assertSee('Godzina podstawienia')
            ->assertSee('07:15')
            ->assertSee('Godzina wyjazdu')
            ->assertSee('07:45')
            ->assertSee('Godzina powrotu')
            ->assertSee('19:30')
            ->assertDontSee('Km transferu')
            ->assertDontSee('Km programu')
            ->assertDontSee('Miejsce startu');
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
            'departure_time' => '07:45:00',
        ];
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
            ->assertSee('Od biura')
            ->assertSee('Parking awaryjny')
            ->assertSee('Nieplanowany')
            ->assertSee('Dodaj wydatek');
    }
}
