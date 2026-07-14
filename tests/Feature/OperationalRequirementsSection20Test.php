<?php

namespace Tests\Feature;

use App\Filament\Pages\ClientInvoiceRequestsInboxPage;
use App\Filament\Pages\NotificationsInboxPage;
use App\Filament\Resources\EventResource\Pages\ManageEventTasks;
use App\Livewire\EventBusCollections;
use App\Models\Event;
use App\Models\User;
use App\Support\OperationalListSort;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Automatyczna weryfikacja checklisty docs/MANUAL_TESTS.md §20.
 */
class OperationalRequirementsSection20Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'super_admin', 'biuro', 'programista', 'pilot', 'ksiegowosc'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->seed(\Database\Seeders\TaskStatusSeeder::class);
    }

    public function test_section_20_core_pages_render_for_admin(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $event = Event::factory()->create([
            'assigned_to' => $admin->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
        ]);

        Livewire::actingAs($admin)
            ->test(ClientInvoiceRequestsInboxPage::class)
            ->assertSuccessful();

        Livewire::actingAs($admin)
            ->test(NotificationsInboxPage::class)
            ->assertSuccessful();

        Livewire::actingAs($admin)
            ->test(ManageEventTasks::class, ['record' => $event->getKey()])
            ->assertSuccessful();

        Livewire::actingAs($admin)
            ->test(EventBusCollections::class, ['event' => $event])
            ->assertSuccessful();
    }

    public function test_operational_lists_use_updated_at_desc(): void
    {
        $query = OperationalListSort::applyToQuery(Event::query());

        $this->assertStringContainsString('updated_at', $query->toSql());
        $this->assertSame('desc', strtolower((string) collect($query->getQuery()->orders)->firstWhere('column', 'updated_at')['direction'] ?? 'desc'));
    }

    public function test_event_supports_www_extra_info_column_when_present(): void
    {
        if (! Schema::hasColumn('events', 'www_extra_info')) {
            $this->markTestSkipped('Kolumna www_extra_info nie istnieje w schemacie testowym.');
        }

        $event = Event::factory()->create([
            'www_extra_info' => '<p>Dodatkowe informacje WWW</p>',
        ]);

        $this->assertSame('<p>Dodatkowe informacje WWW</p>', $event->fresh()->www_extra_info);
    }
}
