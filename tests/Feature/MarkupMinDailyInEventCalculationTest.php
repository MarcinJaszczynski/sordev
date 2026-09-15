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
use App\Services\EventCostCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkupMinDailyInEventCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_cost_calculator_applies_min_daily_markup_floor(): void
    {
        $user = User::factory()->create();
        $pln = Currency::query()->create([
            'name' => 'Złoty',
            'symbol' => 'PLN',
            'code' => 'PLN',
            'exchange_rate' => 1,
        ]);
        $markup = Markup::query()->create([
            'name' => '20% z min 750',
            'percent' => 20,
            'min_daily_amount_pln' => 750,
            'is_default' => true,
        ]);

        $template = EventTemplate::factory()->create([
            'markup_id' => $markup->id,
            'duration_days' => 2,
        ]);

        $templatePoint = EventTemplateProgramPoint::factory()->create([
            'name' => 'Bilet',
            'unit_price' => 50,
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
            'duration_days' => 2,
        ]);
        EventQty::create([
            'event_id' => $event->id,
            'qty' => 10,
            'gratis' => 0,
            'staff' => 0,
            'driver' => 0,
        ]);

        EventProgramPoint::query()->create([
            'event_id' => $event->id,
            'event_template_program_point_id' => $templatePoint->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Bilet',
            'unit_price' => 50,
            'group_size' => 1,
            'currency_id' => $pln->id,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        EventCostCalculator::clearRequestCache();
        $calc = EventCostCalculator::for($event->fresh(['markup', 'eventTemplate.markup', 'programPoints.currency']))
            ->calculate(10, 0, 0, 0);

        // baza: 10 × 50 = 500; 20% = 100; min = 750 × 2 = 1500
        $this->assertSame(500.0, $calc['base_pln']);
        $this->assertSame(1500.0, $calc['markup_pln']);
        $this->assertTrue($calc['min_daily_applied']);
    }
}
