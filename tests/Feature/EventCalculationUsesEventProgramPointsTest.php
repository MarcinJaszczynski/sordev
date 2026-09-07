<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventQty;
use App\Models\EventTemplate;
use App\Models\EventTemplateProgramPoint;
use App\Models\Markup;
use App\Models\User;
use App\Services\EventCalculationSnapshotBuilder;
use App\Services\EventCostCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventCalculationUsesEventProgramPointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_detailed_calculation_drops_soft_deleted_event_points_even_if_template_still_has_them(): void
    {
        $user = User::factory()->create();
        $pln = Currency::query()->create([
            'name' => 'Złoty',
            'symbol' => 'PLN',
            'code' => 'PLN',
            'exchange_rate' => 1,
        ]);
        $markup = Markup::query()->create([
            'name' => 'Test markup',
            'percent' => 0,
            'is_default' => true,
        ]);

        $template = EventTemplate::factory()->create([
            'markup_id' => $markup->id,
        ]);

        $templatePointKeep = EventTemplateProgramPoint::factory()->create([
            'name' => 'Bilet wstępu',
            'unit_price' => 100,
            'group_size' => 1,
            'currency_id' => $pln->id,
        ]);
        $templatePointDelete = EventTemplateProgramPoint::factory()->create([
            'name' => 'Obiad',
            'unit_price' => 50,
            'group_size' => 1,
            'currency_id' => $pln->id,
        ]);

        $template->programPoints()->attach($templatePointKeep->id, [
            'day' => 1,
            'order' => 1,
            'include_in_calculation' => true,
            'active' => true,
        ]);
        $template->programPoints()->attach($templatePointDelete->id, [
            'day' => 1,
            'order' => 2,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $event = Event::factory()->create([
            'assigned_to' => $user->id,
            'event_template_id' => $template->id,
            'markup_id' => $markup->id,
            'participant_count' => 20,
        ]);
        EventQty::create([
            'event_id' => $event->id,
            'qty' => 20,
            'gratis' => 0,
            'staff' => 1,
            'driver' => 1,
        ]);

        $keep = EventProgramPoint::create([
            'event_id' => $event->id,
            'event_template_program_point_id' => $templatePointKeep->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Bilet wstępu',
            'unit_price' => 100,
            'quantity' => 1,
            'group_size' => 1,
            'currency_id' => $pln->id,
            'include_in_calculation' => true,
            'include_in_program' => true,
            'active' => true,
        ]);
        $delete = EventProgramPoint::create([
            'event_id' => $event->id,
            'event_template_program_point_id' => $templatePointDelete->id,
            'day' => 1,
            'order' => 2,
            'name' => 'Obiad',
            'unit_price' => 50,
            'quantity' => 1,
            'group_size' => 1,
            'currency_id' => $pln->id,
            'include_in_calculation' => true,
            'include_in_program' => true,
            'active' => true,
        ]);

        $builder = app(EventCalculationSnapshotBuilder::class);
        $before = $builder->build($event->fresh());
        $beforeNames = collect($before['detailed_calculations'][20]['PLN']['points'] ?? [])
            ->pluck('name')
            ->all();

        $this->assertContains('Bilet wstępu', $beforeNames);
        $this->assertContains('Obiad', $beforeNames);
        $beforeBase = (float) ($before['detailed_calculations'][20]['PLN']['total_before_markup'] ?? 0);
        $this->assertSame(3000.0, $beforeBase); // 20*100 + 20*50

        $delete->delete();
        EventCostCalculator::clearRequestCache();

        $after = $builder->build($event->fresh());
        $afterNames = collect($after['detailed_calculations'][20]['PLN']['points'] ?? [])
            ->pluck('name')
            ->all();

        $this->assertContains('Bilet wstępu', $afterNames);
        $this->assertNotContains('Obiad', $afterNames);
        $afterBase = (float) ($after['detailed_calculations'][20]['PLN']['total_before_markup'] ?? 0);
        $this->assertSame(2000.0, $afterBase); // tylko 20*100
        $this->assertLessThan($beforeBase, $afterBase);

        // Oficjalny kalkulator i szczegółówka muszą być spójne co do bazy programu.
        $official = EventCostCalculator::for($event->fresh())->calculate(20, 0, 1, 1);
        $programOfficial = collect($official['lines'] ?? [])
            ->where('category', 'program')
            ->sum('cost_pln');
        $this->assertSame(2000.0, (float) $programOfficial);

        $this->assertTrue($keep->exists);
    }

    public function test_detailed_calculation_uses_event_unit_price_not_template_price(): void
    {
        $user = User::factory()->create();
        $pln = Currency::query()->create([
            'name' => 'Złoty',
            'symbol' => 'PLN',
            'code' => 'PLN',
            'exchange_rate' => 1,
        ]);
        $markup = Markup::query()->create([
            'name' => 'Zero',
            'percent' => 0,
            'is_default' => true,
        ]);

        $template = EventTemplate::factory()->create(['markup_id' => $markup->id]);
        $templatePoint = EventTemplateProgramPoint::factory()->create([
            'name' => 'Przewodnik',
            'unit_price' => 10,
            'group_size' => 1,
            'currency_id' => $pln->id,
        ]);
        $template->programPoints()->attach($templatePoint->id, [
            'day' => 1,
            'order' => 1,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $event = Event::factory()->create([
            'assigned_to' => $user->id,
            'event_template_id' => $template->id,
            'markup_id' => $markup->id,
            'participant_count' => 10,
        ]);

        EventProgramPoint::create([
            'event_id' => $event->id,
            'event_template_program_point_id' => $templatePoint->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Przewodnik',
            'unit_price' => 25,
            'quantity' => 1,
            'group_size' => 1,
            'currency_id' => $pln->id,
            'include_in_calculation' => true,
            'include_in_program' => true,
            'active' => true,
        ]);

        $detail = app(EventCalculationSnapshotBuilder::class)->build($event->fresh());
        $point = collect($detail['detailed_calculations'][10]['PLN']['points'] ?? [])
            ->firstWhere('name', 'Przewodnik');

        $this->assertNotNull($point);
        $this->assertSame(25.0, (float) $point['unit_price']);
        $this->assertSame(250.0, (float) $point['cost']); // 10 * 25
        $this->assertSame(250.0, (float) ($detail['detailed_calculations'][10]['PLN']['total_before_markup'] ?? 0));
    }
}
