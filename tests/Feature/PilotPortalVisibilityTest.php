<?php

namespace Tests\Feature;

use App\Filament\Pilot\Pages\PilotAdvancePage;
use App\Livewire\PilotTripSettlementForm;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PilotPortalVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'pilot']);
    }

    public function test_pilot_advance_page_hides_currency_exchange_when_disabled(): void
    {
        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
            'pilot_portal_show_currency_exchange' => false,
        ]);

        $this->actingAs($pilot);

        Livewire::test(PilotAdvancePage::class, ['event' => $event])
            ->assertDontSee('Wymiana walut')
            ->assertSee('Zaliczka od biura');
    }

    public function test_pilot_settlement_hides_bus_collections_by_default(): void
    {
        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
            'pilot_portal_show_bus_collections' => false,
        ]);

        $this->actingAs($pilot);

        Livewire::test(PilotTripSettlementForm::class, ['event' => $event])
            ->assertDontSee('Zbiórka gotówki w autokarze')
            ->assertSee('Wydatki pilota');
    }

    public function test_pilot_settlement_shows_bus_collections_when_enabled(): void
    {
        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
            'pilot_portal_show_bus_collections' => true,
        ]);

        $this->actingAs($pilot);

        Livewire::test(PilotTripSettlementForm::class, ['event' => $event])
            ->assertSee('Zbiórka gotówki w autokarze');
    }
}
