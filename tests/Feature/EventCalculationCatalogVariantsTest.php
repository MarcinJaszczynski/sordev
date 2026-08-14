<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Widgets\EventPriceTable;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventQty;
use App\Models\EventTemplate;
use App\Models\EventTemplateQty;
use App\Models\User;
use App\Services\EventCalculationSnapshotBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventCalculationCatalogVariantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_summaries_use_event_template_qty_and_event_qty_overrides(): void
    {
        $user = User::factory()->create();
        Currency::query()->create([
            'name' => 'Złoty',
            'symbol' => 'PLN',
            'code' => 'PLN',
            'exchange_rate' => 1,
        ]);

        EventTemplateQty::query()->create(['qty' => 20, 'gratis' => 2, 'staff' => 1, 'driver' => 1]);
        EventTemplateQty::query()->create(['qty' => 30, 'gratis' => 3, 'staff' => 1, 'driver' => 1]);
        // Duplikat qty — unique bierze pierwszy po id.
        EventTemplateQty::query()->create(['qty' => 20, 'gratis' => 9, 'staff' => 1, 'driver' => 1]);

        $template = EventTemplate::factory()->create();
        $event = Event::factory()->create([
            'assigned_to' => $user->id,
            'event_template_id' => $template->id,
            'participant_count' => 27,
        ]);

        EventQty::create([
            'event_id' => $event->id,
            'qty' => 20,
            'gratis' => 5,
            'staff' => 2,
            'driver' => 1,
        ]);

        EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Bilet',
            'unit_price' => 100,
            'quantity' => 1,
            'group_size' => 1,
            'include_in_calculation' => true,
            'include_in_program' => true,
            'active' => true,
        ]);

        $rows = app(EventCalculationSnapshotBuilder::class)
            ->buildCatalogVariantSummaries($event->fresh());

        $this->assertCount(2, $rows);
        $this->assertSame([20, 30], array_column($rows, 'qty'));

        $row20 = collect($rows)->firstWhere('qty', 20);
        $this->assertTrue($row20['from_event_qty']);
        $this->assertSame(5, $row20['gratis']);
        $this->assertSame(2, $row20['staff']);
        $this->assertGreaterThan(0, $row20['price_per_person']);
        $this->assertGreaterThan(0, $row20['total_pln']);

        $row30 = collect($rows)->firstWhere('qty', 30);
        $this->assertFalse($row30['from_event_qty']);
        $this->assertSame(3, $row30['gratis']);
    }

    public function test_build_detailed_for_variant_returns_requested_qty(): void
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();
        $event = Event::factory()->create([
            'assigned_to' => $user->id,
            'event_template_id' => $template->id,
            'participant_count' => 27,
        ]);

        $detail = app(EventCalculationSnapshotBuilder::class)->buildDetailedForVariant($event, [
            'qty' => 25,
            'gratis' => 2,
            'staff' => 1,
            'driver' => 1,
        ]);

        $this->assertArrayHasKey(25, $detail['qty_variants']);
        $this->assertSame(25, (int) $detail['qty_variants'][25]['qty']);
        // Przy pustym programie szablonu kalkulacja może być pusta — ważne, że nie wybucha
        // i zwraca strukturę pod lazy load.
        $this->assertIsArray($detail['detailed_calculations']);
    }

    public function test_price_table_lazy_loads_catalog_variant_detail(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        EventTemplateQty::query()->create(['qty' => 20, 'gratis' => 1, 'staff' => 1, 'driver' => 1]);
        EventTemplateQty::query()->create(['qty' => 30, 'gratis' => 2, 'staff' => 1, 'driver' => 1]);

        $template = EventTemplate::factory()->create();
        $event = Event::factory()->create([
            'assigned_to' => $user->id,
            'event_template_id' => $template->id,
            'participant_count' => 27,
        ]);
        EventQty::create([
            'event_id' => $event->id,
            'qty' => 27,
            'gratis' => 2,
            'staff' => 1,
            'driver' => 1,
        ]);

        $component = Livewire::test(EventPriceTable::class, ['record' => $event])
            ->assertSet('expandedCatalogQty', null)
            ->assertCount('catalogVariantSummaries', 2)
            ->call('toggleCatalogVariant', 20)
            ->assertSet('expandedCatalogQty', 20);

        $this->assertArrayHasKey(20, $component->get('catalogVariantDetails'));

        $component->call('toggleCatalogVariant', 20)
            ->assertSet('expandedCatalogQty', null);
    }
}
