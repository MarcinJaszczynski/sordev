<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Crm\CreateClientTripInquiryAction;
use App\Data\CreateClientTripInquiryData;
use App\Http\Middleware\PilotPreviewMiddleware;
use App\Models\ClientTripInquiry;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\EventTemplate;
use App\Models\User;
use App\Services\PilotAccessService;
use App\Services\PilotSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PilotPortalUxRebuildTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'pilot', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'biuro', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'client_participant', 'guard_name' => 'web']);
    }

    public function test_pilot_sees_only_assigned_shared_trips(): void
    {
        $pilot = User::factory()->create();
        $pilot->assignRole('pilot');
        $other = User::factory()->create();
        $other->assignRole('pilot');

        $mine = $this->makeEvent($pilot->id, shared: true);
        $this->makeEvent($other->id, shared: true);
        $this->makeEvent($pilot->id, shared: false);

        $ids = app(PilotAccessService::class)
            ->visibleTripsQuery($pilot)
            ->pluck('id')
            ->all();

        $this->assertSame([$mine->id], $ids);
    }

    public function test_preview_as_specific_pilot_scopes_and_blocks_mutations(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('biuro');
        $pilot = User::factory()->create(['name' => 'Pilot Test']);
        $pilot->assignRole('pilot');

        $event = $this->makeEvent($pilot->id, shared: false);
        $other = $this->makeEvent(User::factory()->create()->id, shared: true);

        $this->actingAs($staff);
        session([
            PilotPreviewMiddleware::SESSION_MODE => true,
            PilotPreviewMiddleware::SESSION_USER_ID => $pilot->id,
        ]);

        $service = app(PilotAccessService::class);
        $this->assertTrue($service->isPreviewReadOnly());
        $this->assertTrue($service->canViewTrip($staff, $event));
        $this->assertFalse($service->canViewTrip($staff, $other));
        $this->assertSame([$event->id], $service->visibleTripsQuery($staff)->pluck('id')->all());

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->assertPilotMutationsAllowed();
    }

    public function test_pilot_inquiry_source_and_expense_lines_only_pilot_paid(): void
    {
        if (! Schema::hasTable('client_trip_inquiries')) {
            $this->markTestSkipped('Brak client_trip_inquiries');
        }

        $pilot = User::factory()->create();
        $pilot->assignRole('pilot');
        $event = $this->makeEvent($pilot->id, shared: true);

        $inquiry = app(CreateClientTripInquiryAction::class)(new CreateClientTripInquiryData(
            event: $event,
            user: $pilot,
            subject: 'Pytanie o hotel',
            body: 'Czy są pokoje twin?',
            source: ClientTripInquiry::SOURCE_PILOT,
        ));

        $this->assertSame(ClientTripInquiry::SOURCE_PILOT, $inquiry->source);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        EventSettlementCost::query()->create([
            'settlement_id' => $settlement->id,
            'name' => 'Hotel — biuro',
            'paid_by' => 'office',
            'planned_amount' => 1000,
            'actual_amount' => 0,
            'payment_status' => 'pending',
        ]);
        EventSettlementCost::query()->create([
            'settlement_id' => $settlement->id,
            'name' => 'Parking — pilot',
            'paid_by' => 'pilot',
            'planned_amount' => 50,
            'actual_amount' => 0,
            'payment_status' => 'pending',
        ]);

        $lines = app(PilotSettlementService::class)->getExpenseLines($settlement->fresh());
        $this->assertTrue($lines->every(fn ($row) => ($row->paid_by ?? '') === 'pilot'));
        $this->assertSame(1, $lines->count());
        $this->assertSame('Parking — pilot', $lines->first()->name);
    }

    private function makeEvent(int $assignedTo, bool $shared): Event
    {
        $template = EventTemplate::factory()->create();

        $attrs = [
            'event_template_id' => $template->id,
            'name' => 'Wycieczka '.$assignedTo,
            'client_name' => 'Klient',
            'client_email' => 'k@test.com',
            'client_phone' => '500600700',
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(15)->toDateString(),
            'participant_count' => 20,
            'total_cost' => 1000,
            'status' => 'confirmed',
            'created_by' => $assignedTo,
            'assigned_to' => $assignedTo,
        ];

        if (Schema::hasColumn('events', 'shared_with_pilot')) {
            $attrs['shared_with_pilot'] = $shared;
        }

        return Event::query()->create($attrs);
    }
}
