<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorLocation;
use App\Models\ContractorType;
use App\Models\Event;
use App\Models\EventHotelStay;
use App\Services\EventHotelPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventHotelPlanLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_hotel_stay_saves_contractor_location(): void
    {
        [$contractor, $location] = $this->createHotelWithLocations();

        $event = Event::factory()->create([
            'status' => Event::STATUS_CONFIRMED,
            'duration_days' => 2,
        ]);

        $stay = EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 1,
            'contractor_id' => $contractor->id,
            'contractor_location_id' => $location->id,
        ]);

        $stay->refresh();

        $this->assertSame($contractor->id, $stay->contractor_id);
        $this->assertSame($location->id, $stay->contractor_location_id);
    }

    public function test_pilot_payload_uses_operational_hotel_address(): void
    {
        [$contractor, $location] = $this->createHotelWithLocations();

        $event = Event::factory()->create([
            'status' => Event::STATUS_CONFIRMED,
            'duration_days' => 2,
        ]);

        EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 1,
            'contractor_id' => $contractor->id,
            'contractor_location_id' => $location->id,
        ]);

        $event->load(['hotelStays.contractor', 'hotelStays.contractorLocation']);

        $plan = app(EventHotelPlanService::class)->buildHotelPlanForPdf($event);
        $night = $plan->first();

        $this->assertNotNull($night);
        $this->assertSame('Hotel Zakopane', $night['hotel_branch']);
        $this->assertStringContainsString('Górska', (string) $night['hotel_address']);
        $this->assertStringContainsString('Zakopane', (string) $night['hotel_address']);
        $this->assertSame('999888777', $night['hotel_phone']);
    }

    public function test_pilot_package_view_shows_hotel_contact_per_night(): void
    {
        $html = view('pdf.packages.pilot', [
            'audience' => 'pilot',
            'audienceLabel' => 'Pakiet dla pilota',
            'event' => Event::factory()->make([
                'name' => 'Impreza test',
                'status' => Event::STATUS_CONFIRMED,
            ]),
            'company' => ['name' => 'BP Rafa'],
            'logoDataUri' => null,
            'generatedAt' => now(),
            'participantCount' => 20,
            'staffCount' => 1,
            'driverCount' => 1,
            'gratisCount' => 0,
            'participantSummaryLine' => '20 uczestników',
            'travelLegends' => [
                'departure' => '—',
                'destination' => '—',
                'return' => '—',
                'return_place' => '—',
            ],
            'hotelNotes' => '',
            'hotelProgramPoints' => collect(),
            'programByDay' => collect(),
            'hotelPlan' => collect([
                [
                    'day' => 1,
                    'hotel_name' => 'Sieć Hoteli',
                    'hotel_branch' => 'Hotel Zakopane',
                    'hotel_address' => 'ul. Górska 10, 34-500 Zakopane',
                    'hotel_phone' => '999888777',
                    'hotel_email' => 'zakopane@example.com',
                    'offer_notes' => null,
                    'notes' => null,
                    'qty' => collect(),
                    'gratis' => collect(),
                    'staff' => collect(),
                    'driver' => collect(),
                ],
            ]),
            'agreements' => collect(),
            'individualAgreementRows' => [],
            'agreementsSummary' => ['total' => 0, 'payment_progress_label' => '0/0', 'amount_paid' => 0, 'amount_remaining' => 0],
            'documentFocus' => [],
            'selectedSettlementDocuments' => collect(),
            'attachedFiles' => collect(),
            'pilotSetFinanceCards' => [],
            'pilotExpenseRows' => [],
            'pilotContactPlaces' => [],
        ])->render();

        $this->assertStringContainsString('Hotel', $html);
        $this->assertStringContainsString('Sieć Hoteli', $html);
        $this->assertStringContainsString('Hotel Zakopane', $html);
        $this->assertStringContainsString('ul. Górska 10', $html);
        $this->assertStringContainsString('999888777', $html);
        $this->assertStringContainsString('zakopane@example.com', $html);
        $this->assertStringContainsString('Pokoje', $html);
    }

    public function test_hotel_plan_editor_shows_selected_hotel_contact_card(): void
    {
        [$contractor, $location] = $this->createHotelWithLocations();

        $user = \App\Models\User::factory()->create();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $event = Event::factory()->create([
            'status' => Event::STATUS_CONFIRMED,
            'duration_days' => 2,
        ]);

        EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 1,
            'contractor_id' => $contractor->id,
            'contractor_location_id' => $location->id,
        ]);

        \Livewire\Livewire::test(\App\Livewire\EventHotelPlanEditor::class, ['eventId' => $event->id])
            ->assertSee('Hotel tej nocy')
            ->assertSee('Sieć Hoteli')
            ->assertSee('ul. Górska')
            ->assertSee('999888777')
            ->assertSee('Edytuj hotel');
    }

    /**
     * @return array{0: Contractor, 1: ContractorLocation}
     */
    private function createHotelWithLocations(): array
    {
        $contractor = Contractor::create([
            'name' => 'Sieć Hoteli',
            'street' => 'ul. Centralna',
            'city' => 'Warszawa',
            'phone' => '111222333',
            'status' => 'active',
            'uses_business_locations' => true,
        ]);

        $type = ContractorType::query()->firstOrCreate(['name' => 'hotel']);
        $contractor->types()->sync([$type->id]);

        $location = ContractorLocation::create([
            'contractor_id' => $contractor->id,
            'name' => 'Hotel Zakopane',
            'street' => 'ul. Górska',
            'house_number' => '10',
            'postal_code' => '34-500',
            'city' => 'Zakopane',
            'phone' => '999888777',
            'status' => 'active',
            'is_primary' => true,
        ]);

        return [$contractor, $location];
    }
}
