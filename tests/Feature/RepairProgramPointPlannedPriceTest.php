<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\User;
use App\Services\RepairProgramPointPlannedPriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RepairProgramPointPlannedPriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_detects_and_repairs_unit_seeded_planned_price(): void
    {
        $user = User::factory()->create();
        $pln = Currency::factory()->pln()->create();

        $event = Event::factory()->create(['participant_count' => 20]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Kolacja bankietowa',
            'unit_price' => 220,
            'group_size' => 1,
            'quantity' => 21,
            'planned_price' => 220,
            'calculated_price' => 4620,
            'total_price' => 4620,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'include_pilot_in_cost' => true,
            'active' => true,
        ]);

        // Tworzenie mogło nadpisać planned przez hook — wymuszamy zły seed jak na serwerze.
        $point->forceFill(['planned_price' => 220])->saveQuietly();

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $settlement->importFromEvent();

        $service = app(RepairProgramPointPlannedPriceService::class);
        $detected = $service->detect($point->fresh(['event', 'currency', 'templatePoint']));

        $this->assertNotNull($detected);
        $this->assertSame(220.0, $detected['from']);
        $this->assertSame(4620.0, $detected['to']);

        $service->repair($event->id, dryRun: false);

        $point->refresh();
        $this->assertSame(4620.0, (float) $point->planned_price);

        $cost = EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->where('source_type', 'program_point')
            ->where('source_id', $point->id)
            ->first();

        $this->assertNotNull($cost);
        $this->assertSame(4620.0, (float) $cost->planned_amount);
    }

    public function test_skips_intentionally_matching_calculation(): void
    {
        $pln = Currency::factory()->pln()->create();
        $event = Event::factory()->create(['participant_count' => 1]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Bilet',
            'unit_price' => 100,
            'group_size' => 1,
            'planned_price' => 100,
            'currency_id' => $pln->id,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'include_pilot_in_cost' => false,
            'active' => true,
        ]);

        $this->assertNull(
            app(RepairProgramPointPlannedPriceService::class)
                ->detect($point->fresh(['event', 'currency', 'templatePoint']))
        );
    }

    public function test_artisan_dry_run_does_not_write(): void
    {
        $pln = Currency::factory()->pln()->create();
        $event = Event::factory()->create(['participant_count' => 20]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Kolacja',
            'unit_price' => 220,
            'group_size' => 1,
            'planned_price' => 220,
            'currency_id' => $pln->id,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'include_pilot_in_cost' => true,
            'active' => true,
        ]);
        $point->forceFill(['planned_price' => 220])->saveQuietly();

        Artisan::call('event:repair-program-point-planned-prices', [
            '--event' => $event->id,
        ]);

        $this->assertSame(220.0, (float) $point->fresh()->planned_price);
        $this->assertStringContainsString('Dry-run', Artisan::output());
    }
}
