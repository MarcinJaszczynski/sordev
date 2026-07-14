<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventDayInsurance;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\EventTemplate;
use App\Models\Insurance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventDayInsuranceSettlementSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_day_insurance_creates_settlement_cost_on_import(): void
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza ubezpieczenia',
            'client_name' => 'Szkola',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 20,
            'total_cost' => 2000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $insurance = Insurance::create([
            'name' => 'Polisa podstawowa',
            'price_per_person' => 5.50,
            'active' => true,
            'insurance_enabled' => true,
        ]);

        EventDayInsurance::create([
            'event_id' => $event->id,
            'day' => 1,
            'insurance_id' => $insurance->id,
        ]);

        $settlement->importFromEvent();

        $cost = EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->where('source_type', 'insurance_day')
            ->first();

        $this->assertNotNull($cost);
        $this->assertSame('110.00', $cost->planned_amount_pln);
        $this->assertStringContainsString('Polisa podstawowa', (string) $cost->name);
    }
}
