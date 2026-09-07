<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\User;
use App\Services\ProgramPointPaymentStatusResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramPointPaymentStatusResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_settlement_cost_returns_green(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['client_name' => 'Test']);
        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Muzeum',
            'day' => 1,
            'order' => 1,
            'unit_price' => 100,
            'quantity' => 1,
            'active' => true,
            'include_in_calculation' => true,
        ]);

        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point',
            'source_id' => $point->id,
            'name' => 'Muzeum',
            'planned_amount' => 100,
            'planned_amount_pln' => 100,
            'actual_amount_pln' => 100,
            'payment_status' => 'paid',
            'paid_by' => 'office',
            'advance_type' => 'full',
        ]);

        $badge = app(ProgramPointPaymentStatusResolver::class)->resolve($point->fresh(), $event);

        $this->assertSame('green', $badge['color']);
    }

    public function test_overdue_due_date_returns_red(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['client_name' => 'Test']);
        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Hotel',
            'day' => 1,
            'order' => 1,
            'unit_price' => 500,
            'quantity' => 1,
            'active' => true,
            'include_in_calculation' => true,
        ]);

        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point',
            'source_id' => $point->id,
            'name' => 'Hotel',
            'planned_amount' => 500,
            'planned_amount_pln' => 500,
            'payment_status' => 'advance_required',
            'advance_due_date' => Carbon::now()->subDays(3),
            'paid_by' => 'office',
            'advance_type' => 'advance',
        ]);

        $badge = app(ProgramPointPaymentStatusResolver::class)->resolve($point->fresh(), $event);

        $this->assertSame('red', $badge['color']);
    }

    public function test_upcoming_due_date_returns_orange(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['client_name' => 'Test']);
        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Autokar',
            'day' => 1,
            'order' => 1,
            'unit_price' => 2000,
            'quantity' => 1,
            'active' => true,
            'include_in_calculation' => true,
        ]);

        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point',
            'source_id' => $point->id,
            'name' => 'Autokar',
            'planned_amount' => 2000,
            'planned_amount_pln' => 2000,
            'payment_status' => 'planned',
            'advance_due_date' => Carbon::now()->addDays(5),
            'paid_by' => 'office',
            'advance_type' => 'advance',
        ]);

        $badge = app(ProgramPointPaymentStatusResolver::class)->resolve($point->fresh(), $event);

        $this->assertSame('orange', $badge['color']);
    }

    public function test_paid_flag_without_amounts_is_not_green(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['client_name' => 'Test']);
        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Muzeum',
            'day' => 1,
            'order' => 1,
            'unit_price' => 100,
            'quantity' => 1,
            'active' => true,
            'include_in_calculation' => true,
        ]);

        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point',
            'source_id' => $point->id,
            'name' => 'Muzeum',
            'planned_amount' => 100,
            'planned_amount_pln' => 100,
            'actual_amount_pln' => null,
            'payment_status' => 'paid',
            'paid_by' => 'office',
            'advance_type' => 'full',
        ]);

        $badge = app(ProgramPointPaymentStatusResolver::class)->resolve($point->fresh(), $event);

        $this->assertNotSame('green', $badge['color']);
    }

    public function test_unpaid_with_non_planned_status_is_due_not_in_progress(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['client_name' => 'Test']);
        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Muzeum',
            'day' => 1,
            'order' => 1,
            'unit_price' => 100,
            'quantity' => 1,
            'active' => true,
            'include_in_calculation' => true,
        ]);

        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point',
            'source_id' => $point->id,
            'name' => 'Muzeum',
            'planned_amount' => 800,
            'planned_amount_pln' => 800,
            'payment_status' => 'advance_paid',
            'paid_by' => 'office',
            'advance_type' => 'advance',
        ]);

        $badge = app(ProgramPointPaymentStatusResolver::class)->resolve($point->fresh(), $event);

        $this->assertSame('red', $badge['color']);
        $this->assertStringContainsString('do zapłaty', mb_strtolower($badge['tooltip']));
        $this->assertStringNotContainsString('w toku', mb_strtolower($badge['tooltip']));
    }

    public function test_two_payment_rows_sum_to_green(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['client_name' => 'Test']);
        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Muzeum',
            'day' => 1,
            'order' => 1,
            'unit_price' => 100,
            'quantity' => 1,
            'active' => true,
            'include_in_calculation' => true,
        ]);

        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point',
            'source_id' => $point->id,
            'name' => 'Muzeum',
            'planned_amount' => 100,
            'planned_amount_pln' => 100,
            'payment_status' => 'partially_paid',
            'paid_by' => 'office',
            'advance_type' => 'advance',
        ]);

        EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point_payment',
            'source_id' => $point->id,
            'name' => 'Zaliczka',
            'actual_amount_pln' => 40,
            'payment_status' => 'advance_paid',
            'paid_by' => 'office',
        ]);
        EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point_payment',
            'source_id' => $point->id,
            'name' => 'Dopłata',
            'actual_amount_pln' => 60,
            'payment_status' => 'paid',
            'paid_by' => 'office',
        ]);

        $badge = app(ProgramPointPaymentStatusResolver::class)->resolve($point->fresh(), $event);

        $this->assertSame('green', $badge['color']);
    }

    public function test_foreign_payments_without_pln_amount_still_resolve_green(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['client_name' => 'Test']);
        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Luwr',
            'day' => 1,
            'order' => 1,
            'unit_price' => 560,
            'quantity' => 1,
            'active' => true,
            'include_in_calculation' => true,
        ]);

        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point',
            'source_id' => $point->id,
            'name' => 'Luwr',
            'planned_amount' => 560,
            'planned_amount_pln' => null,
            'planned_rate' => 4.35,
            'planned_convert_to_pln' => false,
            'payment_status' => 'advance_required',
            'paid_by' => 'office',
        ]);

        EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point_payment',
            'source_id' => $point->id,
            'name' => 'Zaliczka',
            'actual_amount' => 110,
            'actual_amount_pln' => null,
            'actual_rate' => 4.35,
            'payment_status' => 'advance_paid',
            'paid_by' => 'office',
        ]);
        EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point_payment',
            'source_id' => $point->id,
            'name' => 'Dopłata',
            'actual_amount' => 450,
            'actual_amount_pln' => null,
            'actual_rate' => 4.35,
            'payment_status' => 'paid',
            'paid_by' => 'office',
        ]);

        $badge = app(ProgramPointPaymentStatusResolver::class)->resolve($point->fresh(), $event);

        $this->assertSame('green', $badge['color']);
        $this->assertStringContainsString('opłacona', mb_strtolower($badge['tooltip']));
    }
}
