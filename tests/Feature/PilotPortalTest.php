<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\User;
use App\Services\PilotSettlementService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PilotPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'pilot']);
        Role::firstOrCreate(['name' => 'admin']);
    }

    public function test_pilot_can_access_pilot_panel_but_admin_panel_denies_pilot(): void
    {
        $pilot = $this->createPilotUser();
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $this->actingAs($pilot)
            ->get('/pilot')
            ->assertOk();

        $this->actingAs($pilot)
            ->get('/admin')
            ->assertForbidden();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk();
    }

    public function test_pilot_can_save_settlement_report_for_assigned_event(): void
    {
        if (! Schema::hasTable('event_settlements')) {
            $this->markTestSkipped('Tabela event_settlements nie istnieje w tym środowisku testowym.');
        }

        $pilot = $this->createPilotUser();
        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
            'participant_count' => 30,
        ]);

        $this->actingAs($pilot);

        $settlement = app(PilotSettlementService::class)->saveReport($event, [
            'pilot_report_notes' => 'Wszystko przebiegło sprawnie.',
            'reported_participant_count' => 28,
            'odometer_start' => 120000,
            'odometer_end' => 120450,
            'submit_to_office' => true,
        ]);

        $this->assertSame('Wszystko przebiegło sprawnie.', $settlement->pilot_report_notes);
        $this->assertSame(28, $settlement->reported_participant_count);
        $this->assertSame(120000, $settlement->odometer_start);
        $this->assertSame(120450, $settlement->odometer_end);
        $this->assertSame('pilot_settled', $settlement->status);
        $this->assertNotNull($settlement->pilot_report_updated_at);
    }

    public function test_pilot_cannot_access_other_pilots_trip_settlement_page(): void
    {
        $pilot = $this->createPilotUser();
        $otherPilot = $this->createPilotUser('other.pilot@example.com');

        $event = Event::factory()->create([
            'assigned_to' => $otherPilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
        ]);

        $this->actingAs($pilot)
            ->get(route('pilot.trip.settle', $event))
            ->assertForbidden();
    }

    public function test_admin_with_pilot_role_sees_only_assigned_shared_trips_in_pilot_panel(): void
    {
        Role::firstOrCreate(['name' => 'admin']);

        $pilotAdmin = User::factory()->create(['status' => 'active']);
        $pilotAdmin->assignRole(['pilot', 'admin']);

        $otherPilot = $this->createPilotUser('other.pilot@example.com');

        $mine = Event::factory()->create([
            'assigned_to' => $pilotAdmin->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
        ]);

        $other = Event::factory()->create([
            'assigned_to' => $otherPilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
        ]);

        $ids = app(\App\Services\PilotAccessService::class)
            ->visibleTripsQuery($pilotAdmin)
            ->pluck('id')
            ->all();

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($other->id, $ids);
        $this->assertFalse($pilotAdmin->can('view', $other));
        $this->assertTrue($pilotAdmin->can('view', $mine));
    }

    public function test_office_preview_allows_admin_with_pilot_role_to_view_any_trip_in_pilot_panel(): void
    {
        $pilotAdmin = User::factory()->create(['status' => 'active']);
        $pilotAdmin->assignRole(['pilot', 'admin']);

        $otherPilot = $this->createPilotUser('other.pilot@example.com');

        $mine = Event::factory()->create([
            'assigned_to' => $pilotAdmin->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
        ]);

        $other = Event::factory()->create([
            'assigned_to' => $otherPilot->id,
            'shared_with_pilot' => false,
            'status' => Event::STATUS_CONFIRMED,
        ]);

        session(['pilot_preview_mode' => true]);

        $service = app(\App\Services\PilotAccessService::class);

        Filament::setServingStatus(true);
        Filament::setCurrentPanel(Filament::getPanel('pilot'));

        $this->actingAs($pilotAdmin);

        $ids = $service->visibleTripsQuery($pilotAdmin)->pluck('id')->all();

        $this->assertContains($mine->id, $ids);
        $this->assertContains($other->id, $ids);
        $this->assertTrue($pilotAdmin->can('view', $other));
        $this->assertTrue($pilotAdmin->can('viewPilotDetails', $other));

        Filament::setServingStatus(false);
        Filament::setCurrentPanel(null);
    }

    public function test_office_preview_http_loads_pilot_event_view(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $event = Event::factory()->create([
            'assigned_to' => $this->createPilotUser()->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
        ]);

        $this->actingAs($admin)
            ->get('/pilot/pilot-events/'.$event->id.'?preview=1')
            ->assertOk();
    }

    public function test_admin_with_pilot_role_can_edit_event_in_admin_panel(): void
    {
        Role::firstOrCreate(['name' => 'admin']);

        $pilotAdmin = User::factory()->create(['status' => 'active']);
        $pilotAdmin->assignRole(['pilot', 'admin']);

        $otherPilot = $this->createPilotUser('other.pilot@example.com');

        $event = Event::factory()->create([
            'assigned_to' => $otherPilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
        ]);

        Filament::setServingStatus(true);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs($pilotAdmin);

        $this->assertTrue($pilotAdmin->can('view', $event));
        $this->assertTrue($pilotAdmin->can('update', $event));

        Filament::setCurrentPanel(Filament::getPanel('pilot'));

        $this->assertFalse($pilotAdmin->can('view', $event));
        $this->assertFalse($pilotAdmin->can('update', $event));

        Filament::setServingStatus(false);
        Filament::setCurrentPanel(null);
    }

    public function test_pilot_can_edit_report_until_settlement_closed(): void
    {
        if (! Schema::hasTable('event_settlements')) {
            $this->markTestSkipped('Tabela event_settlements nie istnieje w tym środowisku testowym.');
        }

        $pilot = $this->createPilotUser();
        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_TO_SETTLE,
        ]);

        $this->actingAs($pilot);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $this->assertTrue($settlement->isEditableByPilot());

        app(PilotSettlementService::class)->saveReport($event, [
            'pilot_report_notes' => 'Pierwsza wersja',
            'submit_to_office' => true,
        ]);

        $settlement->refresh();
        $this->assertTrue($settlement->isEditableByPilot());

        $settlement->update(['status' => 'closed']);
        $this->assertFalse($settlement->fresh()->isEditableByPilot());
    }

    public function test_trip_expenses_sync_from_event_and_pilot_can_edit_line(): void
    {
        if (! Schema::hasTable('event_settlements') || ! Schema::hasTable('event_settlement_costs')) {
            $this->markTestSkipped('Tabele rozliczeń nie istnieją w tym środowisku testowym.');
        }

        $pilot = $this->createPilotUser();
        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
            'participant_count' => 25,
        ]);

        $this->actingAs($pilot);
        $service = app(PilotSettlementService::class);

        $settlement = $service->syncTripExpenses($event, force: true);
        $this->assertGreaterThanOrEqual(0, $settlement->costs()->count());

        $cost = $service->addExpense($event, [
            'name' => 'Parking',
            'actual_amount' => 40,
            'paid_by' => 'pilot',
        ]);

        $updated = $service->updateExpenseLine($event, $cost, [
            'paid_by' => 'pilot',
            'actual_amount' => 45,
            'notes' => 'Dopłata z własnej kieszeni',
        ]);

        $this->assertSame('pilot', $updated->paid_by);
        $this->assertSame('Dopłata z własnej kieszeni', $updated->notes);

        // Manual jest planem SSoT — kwota w wierszu płatności (source_id → plan).
        $paymentSum = (float) EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->where('source_id', $updated->id)
            ->get()
            ->filter(fn (EventSettlementCost $row) => EventSettlementCost::isPaymentSourceType($row->source_type))
            ->sum(fn (EventSettlementCost $row) => (float) ($row->actual_amount_pln ?? $row->actual_amount ?? 0));
        $this->assertEqualsWithDelta(45.0, $paymentSum, 0.01);

        $document = $service->uploadDocument($event, ['document_type' => 'receipt'], [], $updated);
        $this->assertContains($updated->id, $document->linked_cost_ids ?? []);
    }

    public function test_pilot_can_add_expense_and_document(): void
    {
        if (! Schema::hasTable('event_settlements') || ! Schema::hasTable('event_settlement_costs')) {
            $this->markTestSkipped('Tabele rozliczeń nie istnieją w tym środowisku testowym.');
        }

        $pilot = $this->createPilotUser();
        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
        ]);

        $this->actingAs($pilot);
        $service = app(PilotSettlementService::class);

        $cost = $service->addExpense($event, [
            'name' => 'Bilety muzeum',
            'actual_amount' => 120.50,
        ]);

        $this->assertSame('pilot', $cost->paid_by);
        $this->assertSame('manual', $cost->source_type);

        $document = $service->uploadDocument($event, [
            'vendor_name' => 'Muzeum',
            'total_amount' => 120.50,
            'document_type' => 'receipt',
        ], []);

        $this->assertSame('Muzeum', $document->vendor_name);
        $this->assertTrue($document->attach_to_pilot_pdf);
    }

    protected function createPilotUser(string $email = 'pilot.test@example.com'): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'status' => 'active',
        ]);
        $user->assignRole('pilot');

        return $user;
    }
}
