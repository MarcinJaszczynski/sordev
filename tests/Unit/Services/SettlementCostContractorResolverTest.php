<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\VendorInvoice;
use App\Services\SettlementCostContractorResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SettlementCostContractorResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_contractor_from_program_point_when_missing_on_cost(): void
    {
        if (! Schema::hasTable('event_settlement_costs')) {
            $this->markTestSkipped('Brak tabeli event_settlement_costs.');
        }

        $event = Event::factory()->create();
        $contractor = Contractor::create(['name' => 'Muzeum Test', 'status' => 'active']);
        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'contractor_id' => $contractor->id,
            'name' => 'Bilety',
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $cost = $settlement->costs()->create([
            'source_type' => 'program_point',
            'source_id' => $point->id,
            'name' => 'Bilety',
            'planned_amount' => 500,
            'planned_amount_pln' => 500,
            'paid_by' => 'office',
            'payment_status' => 'planned',
            'order' => 1,
        ]);

        $resolved = app(SettlementCostContractorResolver::class)->resolve(
            $cost->fresh(),
            $event->fresh(),
            $point->fresh(),
        );

        $this->assertSame('Muzeum Test', $resolved['contractor']);
        $this->assertSame($contractor->id, $resolved['contractor_id']);
        $this->assertSame('program_point', $resolved['contractor_source']);
    }

    public function test_resolves_transport_contractor_from_event_panel(): void
    {
        if (! Schema::hasTable('event_settlement_costs') || ! Schema::hasColumn('events', 'transport_contractor_id')) {
            $this->markTestSkipped('Brak kolumn transportu.');
        }

        $carrier = Contractor::create(['name' => 'Busy ABC', 'status' => 'active']);
        $event = Event::factory()->create(['transport_contractor_id' => $carrier->id]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $cost = $settlement->costs()->create([
            'source_type' => 'transport',
            'name' => 'Autokar',
            'planned_amount' => 3000,
            'planned_amount_pln' => 3000,
            'paid_by' => 'office',
            'payment_status' => 'planned',
            'order' => 1000,
        ]);

        $resolved = app(SettlementCostContractorResolver::class)->resolve(
            $cost->fresh(),
            $event->fresh(),
        );

        $this->assertSame('Busy ABC', $resolved['contractor']);
        $this->assertSame('transport', $resolved['contractor_source']);
    }

    public function test_resolves_vendor_invoice_seller_when_linked(): void
    {
        if (! Schema::hasTable('event_settlement_costs') || ! Schema::hasTable('vendor_invoices')) {
            $this->markTestSkipped('Brak tabel faktur.');
        }

        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $cost = $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Usługa',
            'planned_amount' => 1200,
            'planned_amount_pln' => 1200,
            'paid_by' => 'office',
            'payment_status' => 'planned',
            'order' => 1,
        ]);

        $invoice = VendorInvoice::create([
            'source' => 'manual',
            'invoice_number' => 'FV/1/2026',
            'seller_name' => 'Hotel Górski Sp. z o.o.',
            'seller_nip' => '1234567890',
            'event_id' => $event->id,
            'event_settlement_cost_id' => $cost->id,
            'payment_status' => 'due',
            'approval_status' => 'pending',
            'matching_status' => 'manual',
        ]);

        $resolved = app(SettlementCostContractorResolver::class)->resolve(
            $cost->fresh(),
            $event->fresh(),
            null,
            collect([$cost->id => $invoice]),
        );

        $this->assertSame('Hotel Górski Sp. z o.o.', $resolved['contractor']);
        $this->assertSame('invoice', $resolved['contractor_source']);
        $this->assertStringContainsString('1234567890', $resolved['contractor_search']);
    }
}
