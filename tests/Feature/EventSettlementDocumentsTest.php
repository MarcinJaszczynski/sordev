<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\ManageEventSettlementDocuments;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventSettlementDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
    }

    public function test_finance_sub_navigation_has_single_documents_tab(): void
    {
        $tabs = ManageEventSettlementDocuments::financeSubNavigationTabs(1);
        $labels = collect($tabs)->pluck('label')->all();

        $this->assertContains('Dokumenty', $labels);
        $this->assertNotContains('Faktury', $labels);
    }

    public function test_legacy_vendor_invoices_route_redirects_to_unified_documents(): void
    {
        if (! Schema::hasTable('vendor_invoices')) {
            $this->markTestSkipped('Brak tabeli vendor_invoices.');
        }

        $event = Event::factory()->create();
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $this->get(\App\Filament\Resources\EventResource::getUrl('vendor-invoices', ['record' => $event->id]))
            ->assertRedirect(\App\Filament\Resources\EventResource::getUrl('settlement-documents', [
                'record' => $event->id,
                'filter' => 'invoices',
            ]));
    }

    public function test_document_filter_controls_visible_sections(): void
    {
        $page = new ManageEventSettlementDocuments;
        $page->documentFilter = 'all';
        $this->assertTrue($page->showsSettlementDocuments());

        $page->documentFilter = 'receipt';
        $this->assertTrue($page->showsSettlementDocuments());
        $this->assertFalse($page->showsVendorInvoices());
        $this->assertSame('receipt', $page->settlementDocumentTypeFilter());

        $page->documentFilter = 'invoices';
        $this->assertSame('invoice', $page->settlementDocumentTypeFilter());
    }
}
