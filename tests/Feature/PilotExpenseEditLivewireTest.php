<?php

namespace Tests\Feature;

use App\Livewire\PilotTripSettlementForm;
use App\Models\Event;
use App\Models\User;
use App\Services\PilotSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PilotExpenseEditLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'pilot']);
    }

    public function test_pilot_can_save_actual_expense_amount_via_livewire(): void
    {
        if (! Schema::hasTable('event_settlement_costs')) {
            $this->markTestSkipped('Tabela event_settlement_costs nie istnieje.');
        }

        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
        ]);

        $this->actingAs($pilot);

        $cost = app(PilotSettlementService::class)->addExpense($event, [
            'name' => 'Bilety',
            'actual_amount' => 40,
        ]);

        Livewire::test(PilotTripSettlementForm::class, ['event' => $event])
            ->call('startEditCost', $cost->id)
            ->set('editCostActualAmount', '125.75')
            ->set('editCostNotes', 'Po fakcie drożej')
            ->call('saveCost')
            ->assertHasNoErrors();

        $cost->refresh();

        $this->assertSame('125.75', $cost->actual_amount);
        $this->assertSame('Po fakcie drożej', $cost->notes);
    }

    public function test_pilot_can_save_planned_program_point_actual_amount(): void
    {
        if (! Schema::hasTable('event_settlement_costs')) {
            $this->markTestSkipped('Tabela event_settlement_costs nie istnieje.');
        }

        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
        ]);

        $this->actingAs($pilot);

        $settlement = app(PilotSettlementService::class)->getOrCreateSettlement($event);

        $cost = $settlement->costs()->create([
            'source_type' => 'program_point',
            'source_id' => 999,
            'name' => 'Wejście do muzeum',
            'planned_amount' => 80,
            'planned_amount_pln' => 80,
            'paid_by' => 'pilot',
            'payment_status' => 'planned',
            'payment_method' => null,
        ]);

        Livewire::test(PilotTripSettlementForm::class, ['event' => $event])
            ->call('startEditCost', $cost->id)
            ->set('editCostActualAmount', '95,50')
            ->call('saveCost')
            ->assertHasNoErrors();

        $cost->refresh();

        $this->assertSame('95.50', $cost->actual_amount);
        $this->assertSame('paid', $cost->payment_status);
    }
}
