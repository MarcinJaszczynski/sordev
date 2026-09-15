<?php

namespace Tests\Unit;

use App\Filament\Forms\EventProgramPointPricingFields;
use App\Models\Currency;
use App\Models\EventTemplateProgramPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventProgramPointPricingFieldsTemplateDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_total_from_template_uses_event_headcount_with_pilot(): void
    {
        $pln = Currency::factory()->pln()->create();

        $template = EventTemplateProgramPoint::factory()->create([
            'name' => 'Kolacja bankietowa',
            'unit_price' => 220,
            'group_size' => 1,
            'currency_id' => $pln->id,
            'include_pilot_in_cost' => true,
            'include_gratis_in_cost' => false,
            'include_driver_in_cost' => false,
        ]);

        $total = EventProgramPointPricingFields::defaultTotalFromTemplate(
            $template,
            participantCount: 20,
            gratisCount: 0,
            pilotCount: 1,
            driverCount: 0,
        );

        $this->assertSame(4620.0, $total);
    }

    public function test_default_total_without_pilot_flag_excludes_pilot(): void
    {
        $pln = Currency::factory()->pln()->create();

        $template = EventTemplateProgramPoint::factory()->create([
            'unit_price' => 220,
            'group_size' => 1,
            'currency_id' => $pln->id,
            'include_pilot_in_cost' => false,
        ]);

        $total = EventProgramPointPricingFields::defaultTotalFromTemplate(
            $template,
            participantCount: 20,
            pilotCount: 1,
        );

        $this->assertSame(4400.0, $total);
    }
}
