<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\EventFinance;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\User;
use App\Services\EventCalculationSnapshotBuilder;
use App\Services\EventCostCalculator;
use App\Services\EventFinanceOverviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProgramPointPlannedPriceInCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculator_defaults_to_template_unit_price(): void
    {
        $event = $this->eventWithPoint(
            participantCount: 20,
            unitPrice: 10,
            plannedPrice: 150,
            groupSize: 1,
        );

        $line = $this->programLine($event, 20);

        $this->assertEqualsWithDelta(200.0, $line, 0.01);
    }

    public function test_flag_uses_planned_price_at_operational_qty(): void
    {
        $event = $this->eventWithPoint(
            participantCount: 20,
            unitPrice: 10,
            plannedPrice: 150,
            groupSize: 1,
            usePlanned: true,
        );

        $this->assertEqualsWithDelta(150.0, $this->programLine($event, 20), 0.01);
    }

    public function test_variants_scale_planned_price_by_implied_unit(): void
    {
        $event = $this->eventWithPoint(
            participantCount: 20,
            unitPrice: 10,
            plannedPrice: 150,
            groupSize: 1,
            usePlanned: true,
        );

        // implied 7.50 × 30 os. = 225
        $this->assertEqualsWithDelta(225.0, $this->programLine($event, 30), 0.01);
        $this->assertEqualsWithDelta(150.0, $this->programLine($event, 20), 0.01);
    }

    public function test_group_pricing_scales_by_billable_units(): void
    {
        $event = $this->eventWithPoint(
            participantCount: 20,
            unitPrice: 100,
            plannedPrice: 150,
            groupSize: 10,
            usePlanned: true,
        );

        // szablon: 2 × 100 = 200; plan 150 / 2 grupy → 75; 30 os. = 3 grupy → 225
        $this->assertEqualsWithDelta(150.0, $this->programLine($event, 20), 0.01);
        $this->assertEqualsWithDelta(225.0, $this->programLine($event, 30), 0.01);
    }

    public function test_per_piece_planned_price_does_not_scale_with_qty(): void
    {
        $event = $this->eventWithPoint(
            participantCount: 20,
            unitPrice: 25,
            plannedPrice: 80,
            groupSize: 0,
            quantity: 4,
            usePlanned: true,
        );

        $this->assertEqualsWithDelta(80.0, $this->programLine($event, 20), 0.01);
        $this->assertEqualsWithDelta(80.0, $this->programLine($event, 40), 0.01);
    }

    public function test_empty_planned_price_falls_back_to_template(): void
    {
        $event = $this->eventWithPoint(
            participantCount: 20,
            unitPrice: 10,
            plannedPrice: 150,
            groupSize: 1,
            usePlanned: true,
        );

        $point = $event->programPoints->first();
        $point->forceFill(['planned_price' => 0])->saveQuietly();

        $this->assertEqualsWithDelta(200.0, $this->programLine($event->fresh(['programPoints']), 20), 0.01);
    }

    public function test_finance_overview_szablon_stays_template_when_flag_is_on(): void
    {
        if (! Schema::hasTable('event_settlement_costs')) {
            $this->markTestSkipped('Brak tabeli event_settlement_costs.');
        }

        $event = $this->eventWithPoint(
            participantCount: 20,
            unitPrice: 10,
            plannedPrice: 150,
            groupSize: 1,
            usePlanned: true,
        );
        $point = $event->programPoints->first();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $settlement->upsertCostFromProgramPoint($point->fresh(['currency', 'templatePoint']));

        $overview = app(EventFinanceOverviewService::class)->forEvent($event->fresh(), hideZero: false);
        $row = collect($overview['rows'])->firstWhere('name', 'Bilet planowany');

        $this->assertNotNull($row);
        $this->assertTrue($row['use_planned_price_in_calculation']);
        $this->assertEqualsWithDelta(200.0, (float) $row['calculation_pln'], 0.01);
        $this->assertEqualsWithDelta(150.0, (float) $row['planned_pln'], 0.01);
    }

    public function test_snapshot_marks_point_as_using_planned_price(): void
    {
        $event = $this->eventWithPoint(
            participantCount: 20,
            unitPrice: 10,
            plannedPrice: 150,
            groupSize: 1,
            usePlanned: true,
        );

        $snapshot = app(EventCalculationSnapshotBuilder::class)->buildDetailedForVariant($event->fresh([
            'programPoints.templatePoint',
            'programPoints.currency',
        ]), [
            'qty' => 20,
            'gratis' => 0,
            'staff' => 0,
            'driver' => 0,
        ]);

        $point = collect($snapshot['detailed_calculations'][20]['PLN']['points'] ?? [])
            ->firstWhere('name', 'Bilet planowany');

        $this->assertNotNull($point);
        $this->assertTrue($point['uses_planned_price']);
        $this->assertEqualsWithDelta(150.0, (float) $point['cost'], 0.01);
        $this->assertEqualsWithDelta(7.5, (float) $point['unit_price'], 0.01);
    }

    public function test_finance_drawer_toggles_flag_and_recalculates_offer(): void
    {
        if (! Schema::hasTable('event_settlement_costs') || ! Schema::hasTable('event_program_points')) {
            $this->markTestSkipped('Brak wymaganych tabel.');
        }

        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $event = $this->eventWithPoint(
            participantCount: 20,
            unitPrice: 10,
            plannedPrice: 150,
            groupSize: 1,
        );
        $point = $event->programPoints->first();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $cost = $settlement->upsertCostFromProgramPoint($point->fresh(['currency', 'templatePoint']));

        $this->assertInstanceOf(EventSettlementCost::class, $cost);
        $this->assertFalse($point->fresh()->usesPlannedPriceInCalculation());
        $this->assertEqualsWithDelta(200.0, $this->programLine($event, 20), 0.01);

        Livewire::test(EventFinance::class, ['record' => $event->getKey()])
            ->call('openCost', $cost->id)
            ->assertSee('Użyj ceny planowanej w kalkulacji')
            ->call('toggleUsePlannedPriceInCalculation')
            ->assertNotified();

        $this->assertTrue($point->fresh()->usesPlannedPriceInCalculation());
        EventCostCalculator::clearRequestCache();
        $this->assertEqualsWithDelta(150.0, $this->programLine($event->fresh(['programPoints']), 20), 0.01);
    }

    private function eventWithPoint(
        int $participantCount,
        float $unitPrice,
        float $plannedPrice,
        int $groupSize,
        int $quantity = 1,
        bool $usePlanned = false,
    ): Event {
        Currency::query()->firstOrCreate(
            ['code' => 'PLN'],
            ['name' => 'Złoty', 'symbol' => 'PLN', 'exchange_rate' => 1],
        );

        $event = Event::factory()->create([
            'participant_count' => $participantCount,
            'duration_days' => 3,
            'transfer_km' => 0,
            'program_km' => 0,
        ]);

        EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'event_template_program_point_id' => null,
            'name' => 'Bilet planowany',
            'unit_price' => $unitPrice,
            'group_size' => $groupSize,
            'quantity' => $quantity,
            'planned_price' => $plannedPrice,
            'use_planned_price_in_calculation' => $usePlanned,
            'include_in_calculation' => true,
            'include_in_program' => true,
            'active' => true,
            'is_hotel' => false,
            'is_transport' => false,
        ]);

        return $event->fresh(['programPoints']);
    }

    private function programLine(Event $event, int $qty): float
    {
        EventCostCalculator::clearRequestCache();

        $calc = EventCostCalculator::for($event->fresh([
            'programPoints.templatePoint',
            'programPoints.currency',
            'markup',
        ]))->calculate($qty, 0, 0, 0);

        $line = collect($calc['lines'] ?? [])->firstWhere('name', 'Bilet planowany');

        return (float) ($line['cost_pln'] ?? 0);
    }
}
