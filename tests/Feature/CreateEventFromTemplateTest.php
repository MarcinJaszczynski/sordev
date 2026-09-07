<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Event;
use App\Models\EventSettlementCost;
use App\Models\EventTemplate;
use App\Models\EventTemplateDayInsurance;
use App\Models\EventTemplateProgramPoint;
use App\Models\EventTemplateQty;
use App\Models\Insurance;
use App\Models\Markup;
use App\Models\Place;
use App\Models\User;
use App\Services\EventCostCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateEventFromTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_event_from_template_saves_template_prices_snapshot()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $place = Place::create(['name' => 'Test Place']);
        $template = EventTemplate::factory()->create(['name' => 'T1', 'start_place_id' => $place->id]);

        $event = Event::createFromTemplate($template, [
            'name' => 'Event z szablonu',
            'client_name' => 'K',
            'start_date' => now()->format('Y-m-d'),
            'participant_count' => 10,
            'hotel_notes' => 'Pokój dla pilota od 8:00.',
        ]);

        $this->assertNotNull($event);
        $this->assertSame('Pokój dla pilota od 8:00.', $event->hotel_notes);
        $this->assertDatabaseHas('event_snapshots', [
            'event_id' => $event->id,
            'type' => 'original',
        ]);

        $snapshot = \App\Models\EventSnapshot::where('event_id', $event->id)->first();
        $this->assertNotNull($snapshot->template_prices_snapshot);

        $settlement = \App\Models\EventSettlement::findActiveForEvent($event);
        $this->assertNotNull($settlement);
        $this->assertContains($settlement->status, ['draft', 'active', 'pilot_settled']);
    }

    public function test_create_from_template_imports_insurance_settlement_costs_and_matches_calculator(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $start = Place::create(['name' => 'Start A']);
        $markup = Markup::query()->create([
            'name' => 'Test 10%',
            'percent' => 10,
        ]);
        $bus = Bus::factory()->create([
            'capacity' => 50,
            'package_price_per_day' => 1000,
            'package_km_per_day' => 5000,
            'extra_km_price' => 5,
            'currency' => 'PLN',
        ]);

        $template = EventTemplate::factory()->create([
            'name' => 'Szablon z ubezpieczeniem',
            'start_place_id' => $start->id,
            'end_place_id' => $start->id,
            'duration_days' => 2,
            'transfer_km' => 100,
            'program_km' => 50,
            'bus_id' => $bus->id,
            'markup_id' => $markup->id,
        ]);

        $point = EventTemplateProgramPoint::factory()->create([
            'name' => 'Zwiedzanie',
            'unit_price' => 100,
            'group_size' => 1,
        ]);
        $template->programPoints()->attach($point->id, [
            'day' => 1,
            'order' => 1,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $insurance = Insurance::query()->create([
            'name' => 'NNW test',
            'price_per_person' => 2.5,
            'active' => true,
            'insurance_enabled' => true,
            'insurance_per_day' => 1,
            'insurance_per_person' => 1,
        ]);
        EventTemplateDayInsurance::create([
            'event_template_id' => $template->id,
            'day' => 1,
            'insurance_id' => $insurance->id,
        ]);
        EventTemplateDayInsurance::create([
            'event_template_id' => $template->id,
            'day' => 2,
            'insurance_id' => $insurance->id,
        ]);

        EventTemplateQty::query()->firstOrCreate(
            ['qty' => 20],
            ['gratis' => 2, 'staff' => 1, 'driver' => 1],
        );

        $event = Event::createFromTemplate($template, [
            'name' => 'Impreza z ubezpieczeniem',
            'client_name' => 'Klient',
            'start_date' => now()->format('Y-m-d'),
            'start_place_id' => $start->id,
            'participant_count' => 20,
            'transfer_km' => 100,
            'program_km' => 50,
            'bus_id' => $bus->id,
            'markup_id' => $markup->id,
            'gratis_count' => 2,
        ]);

        $this->assertSame(2, $event->dayInsurances()->count());

        $insuranceCosts = EventSettlementCost::query()
            ->whereHas('settlement', fn ($q) => $q->where('event_id', $event->id))
            ->where('source_type', 'insurance_day')
            ->count();
        $this->assertSame(2, $insuranceCosts);

        $fresh = $event->fresh([
            'bus',
            'markup',
            'eventTemplate.taxes',
            'qtyVariants',
            'programPoints.currency',
            'dayInsurances.insurance',
            'hotelStays.roomLines.currency',
        ]);
        EventCostCalculator::clearRequestCache();
        $calc = EventCostCalculator::for($fresh)->calculate(20, 2, 1, 1);

        $ppp = $fresh->pricePerPerson()->get()->first(
            fn ($row) => (int) optional($row->eventTemplateQty)->qty === 20
                || (float) $row->price_per_person > 0
        );
        $this->assertNotNull($ppp);
        $this->assertEqualsWithDelta((float) $calc['price_per_person'], (float) $ppp->price_per_person, 0.05);
        $this->assertEqualsWithDelta((float) $calc['base_pln'], (float) $ppp->price_base, 0.05);
        $this->assertGreaterThan(0, (float) collect($calc['lines'])->where('category', 'insurance')->sum('cost_pln'));
    }
}
