<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Pages\EventFinance;
use App\Filament\Resources\EventResource\Pages\EventFinanceSettlementDocuments;
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

    public function test_finance_sub_navigation_includes_settlement_documents_tab(): void
    {
        $tabs = EventFinance::financeSubNavigationTabs(1);
        $labels = collect($tabs)->pluck('label')->all();
        $keys = collect($tabs)->pluck('key')->all();

        $this->assertContains('Dok. rozliczenia', $labels);
        $this->assertContains('settlement-documents', $keys);
        $this->assertNotContains('Faktury', $labels);
        $this->assertContains('Koszty', $labels);
        $this->assertContains('Wpłaty', $labels);
        $this->assertContains('Gotówka pilota', $labels);
    }

    public function test_legacy_vendor_invoices_route_redirects_to_event_finance(): void
    {
        if (! Schema::hasTable('vendor_invoices')) {
            $this->markTestSkipped('Brak tabeli vendor_invoices.');
        }

        $event = Event::factory()->create();
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $this->get(EventResource::getUrl('vendor-invoices', ['record' => $event->id]))
            ->assertRedirect(EventResource::getUrl('finance', [
                'record' => $event->id,
            ]));
    }

    public function test_legacy_settlement_documents_redirects_to_finance_settlement_documents(): void
    {
        $event = Event::factory()->create();
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $this->get(EventResource::getUrl('settlement-documents', ['record' => $event->id]))
            ->assertRedirect(EventResource::getUrl('finance-settlement-documents', [
                'record' => $event->id,
            ]));
    }

    public function test_finance_settlement_documents_page_is_registered(): void
    {
        $this->assertSame(
            'finance-settlement-documents',
            EventFinanceSettlementDocuments::getResourcePageName()
        );
    }
}
