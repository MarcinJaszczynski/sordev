<?php

namespace Tests\Feature;

use App\Livewire\PilotCashDesk;
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

    public function test_pilot_settlement_hides_currency_exchange_when_disabled(): void
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

        Livewire::test(PilotTripSettlementForm::class, ['event' => $event, 'showTripHeader' => false])
            ->assertSee('Zaliczka od biura');

        Livewire::test(PilotCashDesk::class, [
            'event' => $event,
            'context' => 'pilot',
            'compact' => true,
            'showOfficePayoutBlock' => false,
        ])->assertDontSee('Wymiana walut');
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
            ->assertDontSee('Zbiórka gotówki w autokarze');
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

    public function test_trip_nav_has_single_cash_settlement_tab(): void
    {
        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
        ]);

        $this->actingAs($pilot);

        $tabs = \App\Support\PilotTripModuleNavigation::tabs($event);
        $keys = collect($tabs)->pluck('key')->all();

        $this->assertContains('settlement', $keys);
        $this->assertNotContains('advance', $keys);
        $this->assertNotContains('attendance', $keys);
        $this->assertStringContainsString(
            '/pilot/settlement/',
            collect($tabs)->firstWhere('key', 'settlement')['url'] ?? '',
        );
    }

    public function test_trip_nav_shows_attendance_when_enabled(): void
    {
        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
            'pilot_portal_show_attendance' => true,
        ]);

        $this->actingAs($pilot);

        $keys = collect(\App\Support\PilotTripModuleNavigation::tabs($event))->pluck('key')->all();

        $this->assertContains('attendance', $keys);
    }

    public function test_admin_pilot_cash_desk_respects_portal_exchange_visibility(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $event = Event::factory()->create([
            'status' => Event::STATUS_CONFIRMED,
            'pilot_portal_show_currency_exchange' => false,
        ]);

        $this->actingAs($admin);

        // Admin zawsze widzi i edytuje; flaga steruje tylko panelem pilota.
        Livewire::test(PilotCashDesk::class, [
            'event' => $event,
            'context' => 'admin',
            'respectPortalVisibility' => true,
        ])
            ->assertSee('Wymiana walut')
            ->assertSee('Portal: wymiana wyłączona')
            ->assertSee('Zapisz wymianę');

        Livewire::test(PilotCashDesk::class, [
            'event' => $event,
            'context' => 'admin',
            'respectPortalVisibility' => false,
        ])
            ->assertSee('Wymiana walut')
            ->assertSee('Zapisz wymianę')
            ->assertDontSee('Portal: wymiana wyłączona');
    }

    public function test_admin_cash_desk_toggles_exchange_after_portal_visibility_event(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $event = Event::factory()->create([
            'status' => Event::STATUS_CONFIRMED,
            'pilot_portal_show_currency_exchange' => true,
        ]);

        $this->actingAs($admin);

        $component = Livewire::test(PilotCashDesk::class, [
            'event' => $event,
            'context' => 'admin',
            'respectPortalVisibility' => true,
        ])
            ->assertSee('Zapisz wymianę')
            ->assertDontSee('Portal: wymiana wyłączona');

        $event->update(['pilot_portal_show_currency_exchange' => false]);

        $component
            ->dispatch('pilot-portal-visibility-updated', eventId: $event->id)
            ->assertSee('Portal: wymiana wyłączona')
            ->assertSee('Zapisz wymianę');
    }
}
