<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\VendorInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VendorInvoiceProgramPointLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('vendor_invoices')) {
            $this->artisan('migrate', [
                '--path' => 'database/migrations/2026_06_12_100000_create_vendor_invoices_tables.php',
            ]);
        }

        if (! Schema::hasTable('vendor_invoice_program_point')) {
            $this->artisan('migrate', [
                '--path' => 'database/migrations/2026_08_12_100000_create_vendor_invoice_program_point_table.php',
            ]);
        }
    }

    public function test_invoice_can_link_multiple_program_points(): void
    {
        $event = Event::factory()->create();

        $lodging = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Nocleg',
            'day' => 1,
            'order' => 1,
            'active' => true,
            'include_in_calculation' => true,
        ]);
        $dinner = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Obiad',
            'day' => 1,
            'order' => 2,
            'active' => true,
            'include_in_calculation' => true,
        ]);
        $breakfast = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Śniadanie',
            'day' => 2,
            'order' => 1,
            'active' => true,
            'include_in_calculation' => true,
        ]);

        $invoice = VendorInvoice::create([
            'source' => 'test',
            'invoice_number' => 'FV/HOTEL/1',
            'gross_amount' => 1500,
            'currency' => 'PLN',
            'event_id' => $event->id,
            'matching_status' => 'manual',
            'payment_status' => 'due',
        ]);

        $ids = $invoice->syncProgramPointLinks([$lodging->id, $dinner->id, $breakfast->id]);

        $this->assertSame(
            [$lodging->id, $dinner->id, $breakfast->id],
            $ids
        );
        $this->assertSame($lodging->id, (int) $invoice->fresh()->event_program_point_id);
        $this->assertCount(3, $invoice->fresh()->programPoints);

        $this->assertTrue($invoice->fresh()->isLinkedToProgramPoint($dinner->id));
        $this->assertTrue(
            VendorInvoice::queryForProgramPoints([$breakfast->id])->whereKey($invoice->id)->exists()
        );
        $this->assertTrue($dinner->fresh()->vendorInvoices()->whereKey($invoice->id)->exists());
    }

    public function test_setting_primary_fk_also_attaches_pivot_row(): void
    {
        $event = Event::factory()->create();
        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Muzeum',
            'day' => 1,
            'order' => 1,
            'active' => true,
            'include_in_calculation' => true,
        ]);

        $invoice = VendorInvoice::create([
            'source' => 'test',
            'invoice_number' => 'FV/FK/1',
            'gross_amount' => 100,
            'currency' => 'PLN',
            'event_id' => $event->id,
            'event_program_point_id' => $point->id,
            'matching_status' => 'manual',
            'payment_status' => 'due',
        ]);

        $this->assertTrue($invoice->fresh()->programPoints()->whereKey($point->id)->exists());
    }
}
