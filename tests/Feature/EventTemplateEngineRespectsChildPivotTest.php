<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\EventTemplate;
use App\Models\EventTemplateProgramPoint;
use App\Models\EventTemplateQty;
use App\Services\EventTemplateCalculationEngine;
use App\Services\EventTemplateUiCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * WWW bierze ceny z Engine (persist). Admin pokazuje UiService.
 * Oba muszą szanować include_in_calculation z child_pivot.
 */
class EventTemplateEngineRespectsChildPivotTest extends TestCase
{
    use RefreshDatabase;

    public function test_engine_excludes_child_with_include_in_calculation_false(): void
    {
        $pln = Currency::factory()->create([
            'name' => 'Polski złoty',
            'symbol' => 'PLN',
            'code' => 'PLN',
            'exchange_rate' => 1,
        ]);

        EventTemplateQty::create(['qty' => 40, 'gratis' => 0, 'staff' => 0, 'driver' => 0]);

        $template = EventTemplate::factory()->create([
            'duration_days' => 1,
            'program_km' => 0,
            'is_active' => true,
        ]);

        $parent = EventTemplateProgramPoint::factory()->create([
            'name' => 'Atrakcja',
            'unit_price' => 0,
            'group_size' => 1,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
        ]);

        $includedChild = EventTemplateProgramPoint::factory()->create([
            'name' => 'Bilet wliczony',
            'unit_price' => 100,
            'group_size' => 1,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
        ]);

        $excludedChild = EventTemplateProgramPoint::factory()->create([
            'name' => 'Przewodnik wyłączony',
            'unit_price' => 450,
            'group_size' => 1,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
        ]);

        $template->programPoints()->attach($parent->id, [
            'day' => 1,
            'order' => 1,
            'include_in_program' => true,
            'include_in_calculation' => false,
            'active' => true,
        ]);

        DB::table('event_template_program_point_parent')->insert([
            ['parent_id' => $parent->id, 'child_id' => $includedChild->id, 'order' => 1],
            ['parent_id' => $parent->id, 'child_id' => $excludedChild->id, 'order' => 2],
        ]);

        $template->programPointChildren()->attach($includedChild->id, [
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $template->programPointChildren()->attach($excludedChild->id, [
            'include_in_program' => true,
            'include_in_calculation' => false,
            'active' => true,
        ]);

        $engine = app(EventTemplateCalculationEngine::class)->calculateDetailed($template, null, 0.0);
        $ui = app(EventTemplateUiCalculationService::class)->calculate($template, null, 0.0);

        $this->assertArrayHasKey(40, $engine);
        $this->assertArrayHasKey(40, $ui);

        // 40 os. × 100 zł (tylko wliczony child) — bez 450 zł wyłączonego
        $this->assertSame(4000.0, (float) $engine[40]['price_base']);
        $this->assertEqualsWithDelta(4000.0, (float) $ui[40]['PLN']['total_before_markup'], 0.01);
        $this->assertEqualsWithDelta(
            (float) $ui[40]['PLN']['total_before_markup'],
            (float) $engine[40]['price_base'],
            0.01,
            'Engine (WWW) musi mieć ten sam koszt bazowy co UiService (admin)'
        );
    }
}
