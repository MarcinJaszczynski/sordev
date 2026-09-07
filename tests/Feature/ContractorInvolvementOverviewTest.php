<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventHotelStay;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\User;
use App\Models\VendorInvoice;
use App\Services\ContractorInvolvementOverviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContractorInvolvementOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregates_events_points_costs_and_invoices_for_contractor(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $contractor = Contractor::create([
            'name' => 'Muzeum Testowe',
            'nip' => '5250000000',
            'status' => 'active',
        ]);

        $eventAsOrdering = Event::factory()->create([
            'name' => 'Impreza zamawiający',
            'start_date' => now()->addDays(10)->toDateString(),
        ]);
        if (Schema::hasTable('event_contractor')) {
            $eventAsOrdering->orderingContractors()->sync([$contractor->id]);
        }

        $eventAsPilot = Event::factory()->create([
            'name' => 'Impreza pilot',
            'pilot_contractor_id' => $contractor->id,
            'start_date' => now()->addDays(20)->toDateString(),
        ]);

        $eventWithPoint = Event::factory()->create([
            'name' => 'Impreza punkt',
            'start_date' => now()->addDays(5)->toDateString(),
        ]);
        $point = EventProgramPoint::create([
            'event_id' => $eventWithPoint->id,
            'name' => 'Zwiedzanie',
            'day' => 1,
            'contractor_id' => $contractor->id,
            'order' => 1,
        ]);

        if (Schema::hasTable('event_hotel_stays')) {
            $hotelEvent = Event::factory()->create([
                'name' => 'Impreza hotel',
                'start_date' => now()->addDays(15)->toDateString(),
            ]);
            EventHotelStay::create([
                'event_id' => $hotelEvent->id,
                'day' => 1,
                'contractor_id' => $contractor->id,
            ]);
        }

        $settlement = EventSettlement::create([
            'event_id' => $eventWithPoint->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point',
            'source_id' => $point->id,
            'name' => 'Bilet muzeum',
            'planned_amount' => 500,
            'planned_amount_pln' => 500,
            'payment_status' => 'advance_required',
            'paid_by' => 'office',
            'advance_type' => 'advance',
            'contractor_id' => $contractor->id,
        ]);

        if (Schema::hasTable('vendor_invoices')) {
            VendorInvoice::create([
                'invoice_number' => 'FV/1/2026',
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(14)->toDateString(),
                'currency' => 'PLN',
                'gross_amount' => 200,
                'paid_amount' => 0,
                'payment_status' => 'due',
                'contractor_id' => $contractor->id,
                'event_id' => $eventWithPoint->id,
                'seller_nip' => '5250000000',
                'seller_name' => 'Muzeum Testowe',
            ]);
        }

        $overview = app(ContractorInvolvementOverviewService::class)->for($contractor);

        $this->assertGreaterThanOrEqual(3, $overview['summary']['events_count']);
        $this->assertSame(1, $overview['summary']['points_count']);
        $this->assertGreaterThanOrEqual(1, $overview['summary']['costs_count']);
        $this->assertTrue($overview['summary']['due_danger']);

        $eventNames = collect($overview['events'])->pluck('name')->all();
        $this->assertContains('Impreza zamawiający', $eventNames);
        $this->assertContains('Impreza pilot', $eventNames);
        $this->assertContains('Impreza punkt', $eventNames);

        $pilotRow = collect($overview['events'])->firstWhere('name', 'Impreza pilot');
        $this->assertNotNull($pilotRow);
        $this->assertContains(
            ContractorInvolvementOverviewService::ROLE_PILOT,
            collect($pilotRow['roles'])->pluck('key')->all()
        );

        $this->assertSame('Zwiedzanie', $overview['points'][0]['name']);
        $this->assertTrue(
            collect($overview['settlement_costs'])->contains(fn (array $row): bool => $row['name'] === 'Bilet muzeum')
        );

        if (Schema::hasTable('vendor_invoices')) {
            $this->assertSame(1, $overview['summary']['invoices_count']);
            $this->assertSame('FV/1/2026', $overview['vendor_invoices'][0]['name']);
        }

        $this->assertNotEmpty($overview['rows']);
        $kinds = collect($overview['rows'])->pluck('kind')->unique()->values()->all();
        $this->assertContains('event', $kinds);
        $this->assertContains('point', $kinds);
        $this->assertContains('cost', $kinds);

        $filtered = app(ContractorInvolvementOverviewService::class)->filterUnifiedRows(
            $overview['rows'],
            'Zwiedzanie',
            'point',
            'all',
            'all',
        );
        $this->assertCount(1, $filtered);
        $this->assertSame('Zwiedzanie', $filtered[0]['title']);
    }

    public function test_deduplicates_event_when_multiple_roles(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $contractor = Contractor::create([
            'name' => 'Multi Role',
            'status' => 'active',
        ]);

        $event = Event::factory()->create([
            'name' => 'Jedna impreza',
            'pilot_contractor_id' => $contractor->id,
            'transport_contractor_id' => $contractor->id,
            'start_date' => now()->toDateString(),
        ]);

        EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Punkt',
            'day' => 1,
            'contractor_id' => $contractor->id,
            'order' => 1,
        ]);

        $overview = app(ContractorInvolvementOverviewService::class)->for($contractor);

        $this->assertSame(1, $overview['summary']['events_count']);
        $roles = collect($overview['events'][0]['roles'])->pluck('key')->all();
        $this->assertContains(ContractorInvolvementOverviewService::ROLE_PILOT, $roles);
        $this->assertContains(ContractorInvolvementOverviewService::ROLE_TRANSPORT, $roles);
        $this->assertContains(ContractorInvolvementOverviewService::ROLE_PROGRAM_POINT, $roles);
    }
}
