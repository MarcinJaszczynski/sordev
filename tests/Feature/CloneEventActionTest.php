<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Events\CloneEventAction;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventDayInsurance;
use App\Models\EventHotelStay;
use App\Models\EventProgramPoint;
use App\Models\EventQty;
use App\Models\Insurance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CloneEventActionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('admin');
    }

    public function test_clone_event_copies_skeleton_with_new_code(): void
    {
        $this->actingAs($this->admin);

        $contractor = Contractor::query()->create([
            'name' => 'Klient Clone',
            'email' => 'klon@test.pl',
            'phone' => '501501501',
            'status' => 'active',
        ]);

        $source = Event::factory()->create([
            'name' => 'Impreza do klonu',
            'client_name' => 'Klient Clone',
            'client_email' => 'klon@test.pl',
            'client_phone' => '501501501',
            'contractor_id' => $contractor->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-03',
            'duration_days' => 3,
            'participant_count' => 20,
            'status' => Event::STATUS_CONFIRMED,
            'shared_with_pilot' => true,
            'insurance_policy_number' => 'POL-999',
            'created_by' => $this->admin->id,
        ]);

        if (Schema::hasTable('event_contractor')) {
            $source->syncOrderingParties([[
                'contact_id' => null,
                'contractor_id' => $contractor->id,
                'department_label' => 'Sekretariat',
            ]]);
        }

        $parent = EventProgramPoint::factory()->create([
            'event_id' => $source->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Zwiedzanie',
            'unit_price' => 100,
            'planned_price' => 120,
            'paid_price' => 50,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        EventProgramPoint::factory()->create([
            'event_id' => $source->id,
            'parent_id' => $parent->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Przewodnik',
            'unit_price' => 40,
            'include_in_program' => true,
            'active' => true,
        ]);

        if (Schema::hasTable('event_qties')) {
            EventQty::query()->create([
                'event_id' => $source->id,
                'qty' => 20,
                'gratis' => 2,
                'staff' => 1,
                'driver' => 1,
            ]);
        }

        if (Schema::hasTable('event_hotel_stays')) {
            $stay = EventHotelStay::query()->create([
                'event_id' => $source->id,
                'day' => 1,
                'event_program_point_id' => $parent->id,
                'pricing_mode' => 'lines',
                'notes' => 'Hotel centrum',
            ]);

            if (Schema::hasTable('event_hotel_room_lines')) {
                $lineAttrs = [
                    'role' => 'qty',
                    'quantity' => 10,
                    'people_count' => 2,
                    'unit_price' => 200,
                    'convert_to_pln' => true,
                    'order' => 0,
                ];
                if (Schema::hasColumn('event_hotel_room_lines', 'price_layer')) {
                    $lineAttrs['price_layer'] = 'negotiated';
                }
                $stay->allRoomLines()->create($lineAttrs);
            }
        }

        if (Schema::hasTable('event_day_insurance')) {
            $insurance = Insurance::query()->create([
                'name' => 'NNW test',
                'coverage_type' => Insurance::COVERAGE_NNW,
                'price_per_person' => 5,
                'active' => true,
                'insurance_per_day' => true,
                'insurance_per_person' => true,
                'insurance_enabled' => true,
            ]);

            EventDayInsurance::query()->create([
                'event_id' => $source->id,
                'day' => 1,
                'insurance_id' => $insurance->id,
                'is_done' => true,
            ]);
        }

        $sourceCode = $source->code;
        $clone = app(CloneEventAction::class)($source);

        $this->assertNotSame($source->id, $clone->id);
        $this->assertSame('Impreza do klonu (Kopia)', $clone->name);
        $this->assertNotSame($sourceCode, $clone->code);
        $this->assertNotEmpty($clone->code);
        $this->assertSame(Event::STATUS_INQUIRY, $clone->status);
        $this->assertSame('2026-10-01', $clone->start_date?->toDateString());
        $this->assertSame('2026-10-03', $clone->end_date?->toDateString());
        $this->assertSame($contractor->id, $clone->contractor_id);
        $this->assertNull($clone->insurance_policy_number);
        $this->assertFalse((bool) ($clone->shared_with_pilot ?? false));

        if (Schema::hasTable('event_contractor')) {
            $this->assertStringContainsString('Klient Clone', (string) $clone->client_name);
        } else {
            $this->assertSame('Klient Clone', $clone->client_name);
        }

        $clonedParent = $clone->programPoints()->whereNull('parent_id')->first();
        $this->assertNotNull($clonedParent);
        $this->assertSame('Zwiedzanie', $clonedParent->name);
        $this->assertEqualsWithDelta(120.0, (float) $clonedParent->planned_price, 0.01);
        $this->assertNull($clonedParent->paid_price);
        $this->assertSame(1, $clone->programPoints()->whereNotNull('parent_id')->count());
        $this->assertSame('Przewodnik', $clone->programPoints()->whereNotNull('parent_id')->first()?->name);

        if (Schema::hasTable('event_qties')) {
            $this->assertSame(1, $clone->qtyVariants()->count());
            $this->assertSame(20, (int) $clone->qtyVariants()->first()?->qty);
            $this->assertSame(2, (int) $clone->qtyVariants()->first()?->gratis);
        }

        if (Schema::hasTable('event_hotel_stays')) {
            $clonedStay = $clone->hotelStays()->first();
            $this->assertNotNull($clonedStay);
            $this->assertSame(1, (int) $clonedStay->day);
            $this->assertSame('Hotel centrum', $clonedStay->notes);
            $this->assertSame($clonedParent->id, $clonedStay->event_program_point_id);
            $this->assertNull($clonedStay->reservation_id);

            if (Schema::hasTable('event_hotel_room_lines')) {
                $this->assertSame(1, $clonedStay->allRoomLines()->count());
                $this->assertEqualsWithDelta(200.0, (float) $clonedStay->allRoomLines()->first()?->unit_price, 0.01);
            }
        }

        if (Schema::hasTable('event_day_insurance')) {
            $clonedInsurance = $clone->dayInsurances()->first();
            $this->assertNotNull($clonedInsurance);
            $this->assertSame(1, (int) $clonedInsurance->day);
            $this->assertFalse((bool) $clonedInsurance->is_done);
        }

        if (Schema::hasTable('event_contractor')) {
            $this->assertTrue(
                $clone->orderingContractors()->where('contractors.id', $contractor->id)->exists()
            );
        }

        // Źródło bez zmian liczbowych w programie.
        $this->assertSame(2, $source->programPoints()->count());
    }

    public function test_clone_ignores_filament_with_count_attributes_on_source(): void
    {
        $this->actingAs($this->admin);

        $source = Event::factory()->create([
            'name' => 'Impreza withCount',
            'created_by' => $this->admin->id,
        ]);

        // Symulacja rekordu z listy Filament (withCount / withSum).
        $source->setRawAttributes(array_merge($source->getAttributes(), [
            'agreements_count' => 0,
            'agreements_paid_count' => 0,
            'day_insurances_count' => 1,
            'paid_participants_count' => 0,
            'agreements_amount_paid_total' => 0,
        ]), sync: true);

        $clone = app(CloneEventAction::class)($source);

        $this->assertNotSame($source->id, $clone->id);
        $this->assertSame('Impreza withCount (Kopia)', $clone->name);
        $this->assertSame(Event::STATUS_INQUIRY, $clone->status);
        $this->assertArrayNotHasKey('agreements_count', $clone->getAttributes());
    }

    public function test_edit_event_header_clone_action_redirects_to_clone(): void
    {
        $this->actingAs($this->admin);

        $source = Event::factory()->create([
            'name' => 'Impreza UI clone',
            'status' => Event::STATUS_OFFER,
            'created_by' => $this->admin->id,
        ]);

        EventProgramPoint::factory()->create([
            'event_id' => $source->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Punkt UI',
            'include_in_program' => true,
            'active' => true,
        ]);

        Livewire::test(EditEvent::class, ['record' => $source->getKey()])
            ->callAction('clone')
            ->assertRedirect();

        $clone = Event::query()
            ->where('name', 'Impreza UI clone (Kopia)')
            ->where('id', '!=', $source->id)
            ->first();

        $this->assertNotNull($clone);
        $this->assertSame(Event::STATUS_INQUIRY, $clone->status);
        $this->assertNotSame($source->code, $clone->code);
        $this->assertSame(1, $clone->programPoints()->count());

        $this->assertStringContainsString(
            (string) $clone->id,
            EventResource::getUrl('edit', ['record' => $clone]),
        );
    }
}
