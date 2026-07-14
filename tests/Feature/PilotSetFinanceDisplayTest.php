<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Services\PilotSetFinanceDisplay;
use App\Support\PilotSetFinanceMemberLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilotSetFinanceDisplayTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function seedProgramPointCost(EventSettlement $settlement, EventProgramPoint $point, array $attributes): EventSettlementCost
    {
        return EventSettlementCost::query()->updateOrCreate(
            [
                'settlement_id' => $settlement->id,
                'source_type' => 'program_point',
                'source_id' => $point->id,
            ],
            [
                'name' => $point->name,
                'payment_status' => 'planned',
                'order' => 1,
                ...$attributes,
            ],
        );
    }

    public function test_child_outside_program_with_pilot_cost_appears_in_breakdown_and_total(): void
    {
        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $parent = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'include_in_program' => true,
            'active' => true,
        ]);

        $child = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'parent_id' => $parent->id,
            'include_in_program' => false,
            'active' => true,
            'planned_price' => 200,
        ]);

        $this->seedProgramPointCost($settlement, $child, [
            'planned_amount' => 200,
            'planned_amount_pln' => 200,
            'planned_rate' => 1,
            'paid_by' => 'pilot',
            'payment_status' => 'planned',
        ]);

        $cards = app(PilotSetFinanceDisplay::class)->cardsForEvent($event->fresh());

        $this->assertCount(1, $cards);
        $card = $cards[(int) $parent->id];
        $this->assertStringContainsString('200', $card->totalPilotDueLabel);
        $this->assertTrue($card->hasPilotObligation);
        $this->assertCount(1, $card->memberLines);
        $this->assertSame(PilotSetFinanceMemberLine::STATUS_PILOT_DUE, $card->memberLines[0]->status);
    }

    public function test_office_paid_child_shows_office_label_and_excludes_from_pilot_total(): void
    {
        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $parent = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'active' => true,
        ]);

        $child = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'parent_id' => $parent->id,
            'active' => true,
            'planned_price' => 300,
        ]);

        $this->seedProgramPointCost($settlement, $child, [
            'planned_amount' => 300,
            'planned_amount_pln' => 300,
            'planned_rate' => 1,
            'paid_by' => 'office',
            'actual_amount' => 300,
            'actual_amount_pln' => 300,
            'payment_status' => 'paid',
        ]);

        $cards = app(PilotSetFinanceDisplay::class)->cardsForEvent($event->fresh());
        $card = $cards[(int) $parent->id];

        $this->assertSame('0 PLN', $card->totalPilotDueLabel);
        $this->assertFalse($card->hasPilotObligation);
        $this->assertSame(PilotSetFinanceMemberLine::STATUS_OFFICE_PAID, $card->memberLines[0]->status);
        $this->assertStringContainsString('opłacone przez biuro', $card->memberLines[0]->displayLabel);
    }

    public function test_parent_outside_program_has_in_program_false(): void
    {
        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $parent = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'include_in_program' => false,
            'active' => true,
            'planned_price' => 0,
        ]);

        $child = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'parent_id' => $parent->id,
            'include_in_program' => false,
            'active' => true,
            'planned_price' => 150,
        ]);

        $this->seedProgramPointCost($settlement, $child, [
            'planned_amount' => 150,
            'planned_amount_pln' => 150,
            'planned_rate' => 1,
            'paid_by' => 'pilot',
            'payment_status' => 'planned',
        ]);

        $programPointIds = collect();
        $cards = app(PilotSetFinanceDisplay::class)->cardsForEvent($event->fresh(), $programPointIds);

        $card = $cards[(int) $parent->id];
        $this->assertFalse($card->inProgram);
    }

    public function test_mixed_currencies_in_pilot_total(): void
    {
        $pln = Currency::factory()->pln()->create();
        $eur = Currency::factory()->eur()->create(['exchange_rate' => 4.5]);

        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $parent = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'active' => true,
            'currency_id' => $pln->id,
        ]);

        $childPln = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'parent_id' => $parent->id,
            'order' => 1,
            'active' => true,
            'currency_id' => $pln->id,
            'planned_price' => 100,
        ]);

        $childEur = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'parent_id' => $parent->id,
            'order' => 2,
            'active' => true,
            'currency_id' => $eur->id,
            'convert_to_pln' => false,
            'planned_price' => 50,
        ]);

        $this->seedProgramPointCost($settlement, $childPln, [
            'planned_amount' => 100,
            'planned_amount_pln' => 100,
            'planned_currency_id' => $pln->id,
            'planned_rate' => 1,
            'paid_by' => 'pilot',
        ]);

        $this->seedProgramPointCost($settlement, $childEur, [
            'planned_amount' => 50,
            'planned_currency_id' => $eur->id,
            'planned_convert_to_pln' => false,
            'planned_rate' => 4.5,
            'paid_by' => 'pilot',
        ]);

        $card = app(PilotSetFinanceDisplay::class)->cardsForEvent($event->fresh())[(int) $parent->id];

        $this->assertStringContainsString('100 PLN', $card->totalPilotDueLabel);
        $this->assertStringContainsString('50 EUR', $card->totalPilotDueLabel);
    }

    public function test_office_only_set_shows_card_without_pilot_obligation(): void
    {
        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $parent = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'active' => true,
        ]);

        $child = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'parent_id' => $parent->id,
            'active' => true,
            'planned_price' => 400,
        ]);

        $this->seedProgramPointCost($settlement, $child, [
            'planned_amount' => 400,
            'planned_amount_pln' => 400,
            'planned_rate' => 1,
            'paid_by' => 'office',
            'payment_status' => 'planned',
        ]);

        $card = app(PilotSetFinanceDisplay::class)->cardsForEvent($event->fresh())[(int) $parent->id];

        $this->assertFalse($card->hasPilotObligation);
        $this->assertSame('0 PLN', $card->totalPilotDueLabel);
        $this->assertSame(PilotSetFinanceMemberLine::STATUS_OFFICE_DUE, $card->memberLines[0]->status);
        $this->assertStringContainsString('płaci biuro', $card->memberLines[0]->displayLabel);
    }
}
