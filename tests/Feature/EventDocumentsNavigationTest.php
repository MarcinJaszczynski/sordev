<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Pages\ManageEventContracts;
use App\Filament\Resources\EventResource\Pages\ManageEventDocuments;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventDocumentsNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
    }

    public function test_documents_module_lands_on_files_not_contracts(): void
    {
        $this->assertTrue(Schema::hasTable('event_documents'));

        $url = ManageEventDocuments::documentsModuleUrl(1);

        $this->assertSame(
            EventResource::getUrl('documents', ['record' => 1]),
            $url
        );
        $this->assertNotSame(
            EventResource::getUrl('contracts', ['record' => 1]),
            $url
        );
    }

    public function test_documents_sub_navigation_orders_files_before_contracts(): void
    {
        $tabs = ManageEventDocuments::documentsSubNavigationTabs(1);
        $keys = collect($tabs)->pluck('key')->all();

        $this->assertSame('files', $keys[0] ?? null);
        $this->assertContains('files', $keys);

        if (Schema::hasTable('contracts') || Schema::hasTable('event_agreements')) {
            $this->assertContains('contracts', $keys);
            $this->assertTrue(array_search('files', $keys, true) < array_search('contracts', $keys, true));
        }
    }

    public function test_primary_documents_nav_registers_files_page(): void
    {
        $this->assertTrue(ManageEventDocuments::shouldRegisterNavigation());
        $this->assertFalse(ManageEventContracts::shouldRegisterNavigation());

        $items = ManageEventDocuments::getNavigationItems(['record' => 1]);
        $this->assertCount(1, $items);
        $this->assertSame(
            EventResource::getUrl('documents', ['record' => 1]),
            $items[0]->getUrl()
        );
    }

    public function test_documents_page_is_reachable(): void
    {
        $event = Event::factory()->create();
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $this->get(EventResource::getUrl('documents', ['record' => $event->id]))
            ->assertOk()
            ->assertSee('Pakiety PDF')
            ->assertSee('Załączniki ręczne')
            ->assertSee('Dokumenty z programu i rozliczenia');
    }
}
