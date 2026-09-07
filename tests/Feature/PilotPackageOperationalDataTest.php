<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventHotelStay;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Services\Documents\PilotPackageOperationalDataBuilder;
use App\Services\EventPrintPdfDataFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilotPackageOperationalDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_rows_include_only_pilot_costs(): void
    {
        $event = Event::factory()->create([
            'status' => Event::STATUS_CONFIRMED,
            'diet_info' => "1 x dieta bezglutenowa\n2 x dieta wegetariańska",
            'pickup_place_details' => 'Warszawa, róg Chełmskiej',
            'program_day_routes' => ['1' => 'Warszawa → Kraków'],
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $contractor = Contractor::create([
            'name' => 'Muzeum Testowe',
            'street' => 'ul. Wawelska',
            'house_number' => '1',
            'postal_code' => '30-001',
            'city' => 'Kraków',
            'phone' => '500100200',
            'status' => 'active',
        ]);

        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'name' => 'Bilet muzeum',
            'include_in_program' => true,
            'active' => true,
            'contractor_id' => $contractor->id,
        ]);

        EventSettlementCost::query()->create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point',
            'source_id' => $point->id,
            'name' => 'Bilet muzeum',
            'planned_amount' => 450,
            'planned_amount_pln' => 450,
            'planned_rate' => 1,
            'paid_by' => 'pilot',
            'payment_status' => 'planned',
            'order' => 1,
            'contractor_id' => $contractor->id,
        ]);

        EventSettlementCost::query()->create([
            'settlement_id' => $settlement->id,
            'source_type' => 'manual',
            'source_id' => null,
            'name' => 'Faktura biuro',
            'planned_amount' => 999,
            'planned_amount_pln' => 999,
            'planned_rate' => 1,
            'paid_by' => 'office',
            'payment_status' => 'planned',
            'order' => 2,
        ]);

        $event->load([
            'programPoints.contractor',
            'programPoints.contractorLocation',
            'programPoints.templatePoint',
            'activeSettlement',
            'hotelStays',
            'startPlace',
        ]);

        $factoryPayload = app(EventPrintPdfDataFactory::class)->make($event, 'pilot');

        $this->assertCount(1, $factoryPayload['pilotExpenseRows']);
        $this->assertSame('Bilet muzeum', $factoryPayload['pilotExpenseRows'][0]['name']);
        $this->assertStringContainsString('Muzeum', $factoryPayload['pilotExpenseRows'][0]['payee']);
        $this->assertNotEmpty($factoryPayload['pilotExpenseRows'][0]['planned_amount_label']);
        $this->assertArrayHasKey($point->id, $factoryPayload['pilotDueByPointId']);

        $this->assertNotEmpty($factoryPayload['pilotContactPlaces']);
        $this->assertSame([
            '1 x dieta bezglutenowa',
            '2 x dieta wegetariańska',
        ], $factoryPayload['dietInfoLines']);

        $html = view('pdf.packages.pilot', $factoryPayload)->render();

        $this->assertStringContainsString('Diety', $html);
        $this->assertStringContainsString('1 x dieta bezglutenowa', $html);
        $this->assertStringContainsString('Tabela wydatków', $html);
        $this->assertStringContainsString('Komu / adres', $html);
        $this->assertStringContainsString('Kwota zapłacona', $html);
        $this->assertStringContainsString('Bilet muzeum', $html);
        $this->assertStringContainsString('Adresy i kontakty', $html);
        $this->assertStringContainsString('Warszawa → Kraków', $html);
        $this->assertStringContainsString('Do zapłaty', $html);
        $this->assertStringNotContainsString('Faktura biuro', $html);
    }

    public function test_contact_places_include_hotel_and_program_contractor(): void
    {
        $hotel = Contractor::create([
            'name' => 'Folk Resort',
            'street' => 'Majerczykówka',
            'house_number' => '11d',
            'postal_code' => '34-520',
            'city' => 'Zakopane',
            'phone' => '182075100',
            'status' => 'active',
        ]);

        $event = Event::factory()->create([
            'status' => Event::STATUS_CONFIRMED,
            'duration_days' => 2,
            'adress_transport_start' => 'Warszawa, róg Chełmskiej',
        ]);

        EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 1,
            'contractor_id' => $hotel->id,
        ]);

        $guide = Contractor::create([
            'name' => 'Przewodnik Test',
            'phone' => '500687012',
            'city' => 'Kraków',
            'status' => 'active',
        ]);

        EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'name' => 'Spacer po Krakowie',
            'include_in_program' => true,
            'active' => true,
            'contractor_id' => $guide->id,
        ]);

        $event->load([
            'programPoints.contractor',
            'programPoints.contractorLocation',
            'programPoints.templatePoint',
            'hotelStays.contractor',
            'hotelStays.contractorLocation',
            'startPlace',
        ]);

        $payload = app(EventPrintPdfDataFactory::class)->make($event, 'pilot');
        $html = view('pdf.packages.pilot', $payload)->render();

        $this->assertStringContainsString('Folk Resort', $html);
        $this->assertStringContainsString('Zakopane', $html);
        $this->assertStringContainsString('Przewodnik Test', $html);
        $this->assertStringContainsString('Pokoje', $html);
        $this->assertStringContainsString('182075100', $html);

        $rows = app(PilotPackageOperationalDataBuilder::class)->contactPlaces(
            $event,
            collect($payload['hotelPlan']),
            $payload['programByDay'],
            $payload['travelLegends'],
            $payload['programDayRoutes'],
        );

        $roles = collect($rows)->pluck('role')->implode(' | ');
        $this->assertStringContainsString('Hotel', $roles);
        $this->assertStringContainsString('Program', $roles);
        $this->assertStringContainsString('Podstawienie', $roles);
    }
}
