<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Services\PilotProgramPointFinanceDisplay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilotProgramPointFinanceDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_finance_hint_when_payer_is_pilot(): void
    {
        $event = Event::factory()->create(['participant_count' => 20]);
        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'pilot_notes' => '<p>Zabierz gotówkę na bilety</p>',
            'include_in_program' => true,
            'active' => true,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point',
            'source_id' => $point->id,
            'name' => $point->name,
            'planned_amount' => 120,
            'planned_amount_pln' => 120,
            'planned_rate' => 1,
            'paid_by' => 'pilot',
            'advance_amount' => 50,
            'advance_due_date' => '2026-08-15',
            'payment_status' => 'planned',
            'order' => 1,
        ]);

        $hints = app(PilotProgramPointFinanceDisplay::class)->hintsForPoints($event->fresh(), collect([$point]));

        $this->assertArrayHasKey($point->id, $hints);
        $this->assertTrue($hints[$point->id]['has_pilot_obligation']);
        $this->assertSame('Pilot płaci', $hints[$point->id]['payer_label']);
        $this->assertSame('15.08.2026', $hints[$point->id]['due_date_label']);
    }

    public function test_shows_office_pays_hint_when_office_pays(): void
    {
        $event = Event::factory()->create();
        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'include_in_program' => true,
            'active' => true,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point',
            'source_id' => $point->id,
            'name' => $point->name,
            'planned_amount' => 120,
            'planned_amount_pln' => 120,
            'planned_rate' => 1,
            'paid_by' => 'office',
            'payment_status' => 'planned',
            'order' => 1,
        ]);

        $hints = app(PilotProgramPointFinanceDisplay::class)->hintsForPoints($event->fresh(), collect([$point]));

        $this->assertArrayHasKey($point->id, $hints);
        $this->assertFalse($hints[$point->id]['has_pilot_obligation']);
        $this->assertTrue($hints[$point->id]['has_office_obligation']);
        $this->assertSame('Płaci biuro', $hints[$point->id]['payer_label']);
    }

    public function test_set_parent_shows_rollup_hint_without_child_hint(): void
    {
        $event = Event::factory()->create(['participant_count' => 20]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $parent = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
            'planned_price' => 100,
        ]);

        $child = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'parent_id' => $parent->id,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
            'planned_price' => 200,
        ]);

        EventSettlementCost::query()->updateOrCreate(
            [
                'settlement_id' => $settlement->id,
                'source_type' => 'program_point',
                'source_id' => $parent->id,
            ],
            [
                'name' => $parent->name,
                'planned_amount' => 100,
                'planned_amount_pln' => 100,
                'planned_rate' => 1,
                'paid_by' => 'office',
                'payment_status' => 'planned',
                'order' => 1,
            ],
        );

        EventSettlementCost::query()->updateOrCreate(
            [
                'settlement_id' => $settlement->id,
                'source_type' => 'program_point',
                'source_id' => $child->id,
            ],
            [
                'name' => $child->name,
                'planned_amount' => 200,
                'planned_amount_pln' => 200,
                'planned_rate' => 1,
                'paid_by' => 'pilot',
                'advance_amount' => 50,
                'advance_due_date' => '2026-08-10',
                'payment_status' => 'planned',
                'order' => 2,
            ],
        );

        $points = collect([
            EventProgramPoint::withCount('children')->find($parent->id),
            $child->fresh(),
        ])->filter();
        $hints = app(PilotProgramPointFinanceDisplay::class)->hintsForPoints($event->fresh(), $points);

        $this->assertArrayHasKey($parent->id, $hints);
        $this->assertTrue($hints[$parent->id]['is_set_rollup']);
        $this->assertTrue($hints[$parent->id]['has_pilot_obligation']);
        $this->assertSame('Pilot płaci (set)', $hints[$parent->id]['payer_label']);
        $this->assertArrayHasKey($child->id, $hints);
        $this->assertTrue($hints[$child->id]['is_set_child']);
        $this->assertTrue($hints[$child->id]['has_pilot_obligation']);
    }

    public function test_shows_hint_when_only_advance_is_pilot_responsibility(): void
    {
        $event = Event::factory()->create();
        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'include_in_program' => true,
            'active' => true,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        EventSettlementCost::query()->updateOrCreate(
            [
                'settlement_id' => $settlement->id,
                'source_type' => 'program_point',
                'source_id' => $point->id,
            ],
            [
                'name' => $point->name,
                'planned_amount' => 500,
                'planned_amount_pln' => 500,
                'planned_rate' => 1,
                'paid_by' => 'office',
                'payment_status' => 'planned',
                'order' => 1,
            ],
        );

        EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point_payment',
            'source_id' => $point->id,
            'name' => $point->name.' • zaliczka',
            'paid_by' => 'pilot',
            'advance_type' => 'advance',
            'advance_amount' => 120,
            'advance_due_date' => '2026-08-20',
            'payment_status' => 'advance_required',
            'order' => 2,
        ]);

        $hints = app(PilotProgramPointFinanceDisplay::class)->hintsForPoints($event->fresh(), collect([$point]));

        $this->assertArrayHasKey($point->id, $hints);
        $this->assertTrue($hints[$point->id]['has_pilot_obligation']);
        $this->assertSame('Pilot płaci część', $hints[$point->id]['payer_label']);
        $this->assertStringContainsString('Zaliczka:', implode(' ', $hints[$point->id]['lines']));
    }
}
