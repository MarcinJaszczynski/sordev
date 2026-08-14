<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Finance\UpsertFinanceProgramCostAction;
use App\Data\UpsertFinanceProgramCostData;
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

class EventFinanceProgramCostTest extends TestCase
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

    public function test_overview_shows_foreign_amount_without_pln_when_not_converted(): void
    {
        if (! Schema::hasTable('event_settlement_costs')) {
            $this->markTestSkipped('Brak tabeli event_settlement_costs.');
        }

        $eur = Currency::factory()->eur()->create(['exchange_rate' => 4.30]);
        $event = Event::factory()->create(['duration_days' => 3, 'participant_count' => 10]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Wstęp EUR',
            'planned_amount' => 100,
            'planned_currency_id' => $eur->id,
            'planned_convert_to_pln' => false,
            'planned_rate' => 4.30,
            'planned_amount_pln' => null,
            'paid_by' => 'office',
            'payment_status' => 'planned',
            'order' => 1,
        ]);

        $overview = app(EventFinanceOverviewService::class)->forEvent($event->fresh(), hideZero: false);
        $row = collect($overview['rows'])->firstWhere('name', 'Wstęp EUR');

        $this->assertNotNull($row);
        $this->assertSame('100,00 EUR', $row['planned_label']);
        $this->assertStringNotContainsString('≈', $row['planned_label']);
    }

    public function test_overview_shows_indicative_pln_when_converted(): void
    {
        if (! Schema::hasTable('event_settlement_costs')) {
            $this->markTestSkipped('Brak tabeli event_settlement_costs.');
        }

        $eur = Currency::factory()->eur()->create(['exchange_rate' => 4.30]);
        $event = Event::factory()->create(['duration_days' => 3, 'participant_count' => 10]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Wstęp EUR',
            'planned_amount' => 100,
            'planned_currency_id' => $eur->id,
            'planned_convert_to_pln' => true,
            'planned_rate' => 4.30,
            'planned_amount_pln' => 430,
            'paid_by' => 'office',
            'payment_status' => 'planned',
            'order' => 1,
        ]);

        $overview = app(EventFinanceOverviewService::class)->forEvent($event->fresh(), hideZero: false);
        $row = collect($overview['rows'])->firstWhere('name', 'Wstęp EUR');

        $this->assertNotNull($row);
        $this->assertSame('100,00 EUR (≈ 430,00 PLN)', $row['planned_label']);
    }

    public function test_upsert_finance_program_cost_creates_program_point_and_settlement_cost(): void
    {
        if (! Schema::hasTable('event_settlement_costs') || ! Schema::hasTable('event_program_points')) {
            $this->markTestSkipped('Brak wymaganych tabel.');
        }

        $pln = Currency::factory()->pln()->create();
        $event = Event::factory()->create(['duration_days' => 2, 'participant_count' => 12]);

        $cost = app(UpsertFinanceProgramCostAction::class)(new UpsertFinanceProgramCostData(
            event: $event,
            name: 'Przewodnik lokalny',
            amount: 850,
            currencyId: $pln->id,
            convertToPln: true,
            paidBy: 'pilot',
            day: 2,
            notes: 'Z finansów',
        ));

        $this->assertSame('program_point', $cost->source_type);
        $this->assertSame('pilot', $cost->paid_by);
        $this->assertEqualsWithDelta(850.0, (float) $cost->planned_amount, 0.01);

        $point = EventProgramPoint::query()->find($cost->source_id);
        $this->assertNotNull($point);
        $this->assertSame('Przewodnik lokalny', $point->name);
        $this->assertSame(2, (int) $point->day);
        $this->assertTrue((bool) $point->include_in_calculation);
        $this->assertSame(0, (int) $point->group_size);
    }

    public function test_event_finance_livewire_add_and_edit_cost(): void
    {
        if (! Schema::hasTable('event_settlement_costs') || ! Schema::hasTable('event_program_points')) {
            $this->markTestSkipped('Brak wymaganych tabel.');
        }

        $pln = Currency::factory()->pln()->create();
        $eur = Currency::factory()->eur()->create(['exchange_rate' => 4.5]);
        $event = Event::factory()->create(['duration_days' => 3, 'participant_count' => 8]);
        EventSettlement::findOrCreateActiveForEvent($event);

        Livewire::test(EventFinance::class, ['record' => $event->getKey()])
            ->call('startAddCost')
            ->set('costForm.name', 'Bilet wstępu')
            ->set('costForm.amount', 120)
            ->set('costForm.currency_id', $eur->id)
            ->set('costForm.convert_to_pln', false)
            ->set('costForm.paid_by', 'office')
            ->set('costForm.day', 1)
            ->call('saveCost')
            ->assertNotified();

        $cost = EventSettlementCost::query()
            ->where('source_type', 'program_point')
            ->where('name', 'Bilet wstępu')
            ->first();
        $this->assertNotNull($cost);
        $this->assertFalse((bool) $cost->planned_convert_to_pln);
        $this->assertEqualsWithDelta(120.0, (float) $cost->planned_amount, 0.01);

        Livewire::test(EventFinance::class, ['record' => $event->getKey()])
            ->call('openCost', $cost->id)
            ->call('startEditPlan')
            ->assertSet('showPlanForm', true)
            ->set('planForm.unit_price', 150)
            ->call('recalculateProgramPointPlanTotals')
            ->set('planForm.paid_by', 'pilot')
            ->call('savePlan')
            ->assertNotified();

        $cost->refresh();
        $this->assertEqualsWithDelta(150.0, (float) $cost->planned_amount, 0.01);
        $this->assertSame('pilot', $cost->paid_by);

        $point = EventProgramPoint::query()->find($cost->source_id);
        $this->assertNotNull($point);
        $this->assertEqualsWithDelta(150.0, (float) $point->planned_price, 0.01);

        // PLN currency still present for other flows / defaults
        $this->assertNotNull($pln->fresh());
    }
}
