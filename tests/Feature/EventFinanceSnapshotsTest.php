<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Pages\EventCalculation;
use App\Filament\Resources\EventResource\Pages\EventFinance;
use App\Filament\Resources\EventResource\Pages\EventFinanceSnapshots;
use App\Filament\Resources\EventResource\RelationManagers\SnapshotsRelationManager;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventQty;
use App\Models\EventSnapshot;
use App\Models\EventTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventFinanceSnapshotsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('event_snapshots')) {
            $this->markTestSkipped('Brak tabeli event_snapshots.');
        }

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
    }

    public function test_finance_snapshots_page_loads_and_lists_existing_snapshots(): void
    {
        $event = Event::factory()->create();
        EventSnapshot::createSnapshot($event, 'manual', 'Stan testowy', 'Przed zmianą klienta');

        Livewire::test(EventFinanceSnapshots::class, ['record' => $event->getKey()])
            ->assertSuccessful()
            ->assertSee('Migawki stanu imprezy');

        Livewire::test(SnapshotsRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => EventFinanceSnapshots::class,
        ])
            ->assertSuccessful()
            ->assertCanSeeTableRecords(EventSnapshot::query()->where('event_id', $event->id)->get());
    }

    public function test_finance_page_can_create_manual_snapshot(): void
    {
        $event = Event::factory()->create();

        Livewire::test(EventFinance::class, ['record' => $event->getKey()])
            ->callAction('create_snapshot', data: [
                'name' => 'Stan przed zmianami klienta 26.08.2026',
                'description' => 'Klient zgłosił zmianę liczby osób',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('event_snapshots', [
            'event_id' => $event->id,
            'type' => 'manual',
            'name' => 'Stan przed zmianami klienta 26.08.2026',
        ]);
    }

    public function test_snapshots_relation_manager_can_create_manual_snapshot(): void
    {
        $event = Event::factory()->create();

        Livewire::test(SnapshotsRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => EventFinanceSnapshots::class,
        ])
            ->callTableAction('create_manual_snapshot', data: [
                'name' => 'Migawka z listy',
                'description' => null,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('event_snapshots', [
            'event_id' => $event->id,
            'type' => 'manual',
            'name' => 'Migawka z listy',
        ]);
    }

    public function test_finance_snapshots_route_is_registered(): void
    {
        $event = Event::factory()->create();

        $url = EventResource::getUrl('finance-snapshots', ['record' => $event]);

        $this->assertStringContainsString('/finance/snapshots', $url);
        $this->get($url)->assertSuccessful();
    }

    public function test_manual_snapshot_stores_current_variant_calculation_with_price_per_person(): void
    {
        $template = EventTemplate::factory()->create();
        $event = Event::factory()->create([
            'assigned_to' => $this->admin->id,
            'event_template_id' => $template->id,
            'participant_count' => 20,
            'total_cost' => 0,
        ]);

        EventQty::create([
            'event_id' => $event->id,
            'qty' => 20,
            'gratis' => 2,
            'staff' => 1,
            'driver' => 1,
        ]);

        EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Bilet wstępu',
            'unit_price' => 100,
            'quantity' => 1,
            'group_size' => 1,
            'include_in_calculation' => true,
            'include_in_program' => true,
            'active' => true,
        ]);

        $snapshot = EventSnapshot::createSnapshot($event->fresh(), 'manual', 'Stan z kalkulacją');

        $this->assertSame(2, (int) ($snapshot->calculations['version'] ?? 0));
        $this->assertArrayHasKey('summary', $snapshot->calculations);
        $this->assertArrayHasKey('detailed_calculations', $snapshot->calculations);
        $this->assertArrayHasKey('current_variant', $snapshot->calculations);

        $variant = $snapshot->calculations['current_variant'];
        $this->assertSame(20, (int) $variant['qty']);
        $this->assertSame(2, (int) $variant['gratis']);

        $summary = $snapshot->calculations['summary'];
        $this->assertGreaterThan(0, (float) $summary['total_cost']);
        $this->assertGreaterThan(0, (float) $summary['price_per_person_rounded']);
        $this->assertNotNull($snapshot->pricePerPersonSnapshot());
        $this->assertEqualsWithDelta(
            (float) $summary['total_cost'],
            (float) $snapshot->total_cost_snapshot,
            0.01
        );

        $detailed = $snapshot->calculations['detailed_calculations'];
        $this->assertCount(1, $detailed);
        $this->assertTrue(
            array_key_exists('20', $detailed) || array_key_exists(20, $detailed),
            'Migawka powinna trzymać tylko bieżący wariant qty=20'
        );

        // JSON-safe: bez obiektów Eloquent.
        $encoded = json_encode($snapshot->calculations);
        $this->assertNotFalse($encoded);
        $this->assertJson($encoded);
    }

    public function test_calculation_page_exposes_create_snapshot_header_action(): void
    {
        $template = EventTemplate::factory()->create();
        $event = Event::factory()->create([
            'assigned_to' => $this->admin->id,
            'event_template_id' => $template->id,
            'participant_count' => 20,
        ]);

        EventQty::create([
            'event_id' => $event->id,
            'qty' => 20,
            'gratis' => 0,
            'staff' => 1,
            'driver' => 1,
        ]);

        Livewire::test(EventCalculation::class, ['record' => $event->getKey()])
            ->assertSuccessful()
            ->assertActionExists('create_snapshot')
            ->callAction('create_snapshot', data: [
                'name' => 'Migawka z kalkulacji',
                'description' => 'Przed zmianą programu',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('event_snapshots', [
            'event_id' => $event->id,
            'type' => 'manual',
            'name' => 'Migawka z kalkulacji',
        ]);

        $snapshot = EventSnapshot::query()->where('event_id', $event->id)->latest('id')->first();
        $this->assertNotNull($snapshot);
        $this->assertSame(2, (int) ($snapshot->calculations['version'] ?? 0));
        $this->assertNotNull($snapshot->pricePerPersonSnapshot());
    }
}
