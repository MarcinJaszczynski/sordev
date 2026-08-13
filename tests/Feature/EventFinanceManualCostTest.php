<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Finance\UpsertFinanceManualCostAction;
use App\Data\UpsertFinanceManualCostData;
use App\Filament\Resources\EventResource\Pages\EventFinance;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\User;
use App\Services\EventFinanceOverviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventFinanceManualCostTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
    }

    public function test_upsert_manual_cost_creates_settlement_row_without_program_point(): void
    {
        if (! Schema::hasTable('event_settlement_costs') || ! Schema::hasTable('event_program_points')) {
            $this->markTestSkipped('Brak wymaganych tabel.');
        }

        Currency::factory()->pln()->create();
        $event = Event::factory()->create(['duration_days' => 2, 'participant_count' => 12]);
        $pointsBefore = EventProgramPoint::query()->where('event_id', $event->id)->count();

        $cost = app(UpsertFinanceManualCostAction::class)(new UpsertFinanceManualCostData(
            event: $event,
            name: 'Nagłe ubezpieczenie',
            amount: 350,
            paidBy: 'pilot',
            notes: 'Na miejscu',
        ));

        $this->assertSame('manual', $cost->source_type);
        $this->assertNull($cost->source_id);
        $this->assertSame('Nagłe ubezpieczenie', $cost->name);
        $this->assertEqualsWithDelta(350.0, (float) $cost->planned_amount, 0.01);
        $this->assertSame($pointsBefore, EventProgramPoint::query()->where('event_id', $event->id)->count());

        $overview = app(EventFinanceOverviewService::class)->forEvent($event->fresh(), hideZero: false);
        $row = collect($overview['rows'])->firstWhere('cost_id', $cost->id);
        $this->assertNotNull($row);
        $this->assertSame('Nieprzewidziany', $row['source_label']);
        $this->assertFalse($row['is_approved']);
    }

    public function test_finance_page_can_add_manual_cost_and_toggle_approval(): void
    {
        if (! Schema::hasTable('event_settlement_costs')) {
            $this->markTestSkipped('Brak tabeli event_settlement_costs.');
        }

        Currency::factory()->pln()->create();
        $event = Event::factory()->create();
        EventSettlement::findOrCreateActiveForEvent($event);

        Livewire::test(EventFinance::class, ['record' => $event->getKey()])
            ->call('startAddManualCost')
            ->assertSet('showCostForm', true)
            ->assertSet('costFormMode', 'manual')
            ->set('costForm.name', 'Drobne wydatki')
            ->set('costForm.amount', 120)
            ->set('costForm.paid_by', 'pilot')
            ->call('saveCost')
            ->assertSet('showCostForm', false);

        $cost = EventSettlementCost::query()
            ->where('name', 'Drobne wydatki')
            ->where('source_type', 'manual')
            ->first();

        $this->assertNotNull($cost);
        $this->assertSame('pending', $cost->approval_status);

        Livewire::test(EventFinance::class, ['record' => $event->getKey()])
            ->call('toggleCostApproval', $cost->id);

        $cost->refresh();
        $this->assertSame('approved', $cost->approval_status);
        $this->assertNotNull($cost->reviewed_at);
        $this->assertSame($this->admin->id, $cost->reviewed_by);
    }
}
