<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\User;
use App\Models\VendorInvoice;
use App\Services\Invoices\VendorInvoiceProgramPointSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VendorInvoiceProgramPointSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('vendor_invoices')) {
            $this->artisan('migrate', ['--path' => 'database/migrations/2026_06_12_100000_create_vendor_invoices_tables.php']);
        }
    }

    public function test_assigning_invoice_updates_program_point_paid_price(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['client_name' => 'Test']);
        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Muzeum',
            'day' => 1,
            'order' => 1,
            'unit_price' => 100,
            'quantity' => 1,
            'active' => true,
            'include_in_calculation' => true,
            'planned_price' => 100,
        ]);

        Currency::create(['name' => 'PLN', 'symbol' => 'PLN', 'code' => 'PLN', 'exchange_rate' => 1]);

        EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $invoice = VendorInvoice::create([
            'source' => 'test',
            'invoice_number' => 'FV/TEST/1',
            'gross_amount' => 100,
            'currency' => 'PLN',
            'event_id' => $event->id,
            'event_program_point_id' => $point->id,
            'matching_status' => 'manual',
            'payment_status' => 'paid',
        ]);

        app(VendorInvoiceProgramPointSync::class)->sync($invoice->fresh());

        $point->refresh();
        $this->assertEquals(100.0, (float) $point->paid_price);
    }
}
