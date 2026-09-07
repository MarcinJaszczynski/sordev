<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventHotelStay;
use App\Models\EventTemplate;
use App\Models\EventTemplateHotelDay;
use App\Models\HotelRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventParticipantCountRoomStructureConfirmTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TaskStatusSeeder::class);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'biuro', 'guard_name' => 'web']);
    }

    public function test_changing_participant_count_without_room_structure_saves_directly(): void
    {
        [$admin, $contractor, $event] = $this->makeEditableEvent(participantCount: 20);

        $this->actingAs($admin);

        Livewire::test(EditEvent::class, ['record' => $event->getKey()])
            ->fillForm($this->formPayload($contractor, participantCount: 24))
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertActionNotMounted('confirmParticipantCountRoomRefresh');

        $this->assertSame(24, (int) $event->fresh()->participant_count);
    }

    public function test_changing_participant_count_with_room_structure_asks_for_confirmation(): void
    {
        [$admin, $contractor, $event] = $this->makeEditableEvent(participantCount: 20);
        $this->attachCustomRoomStructure($event, label: 'Ręczny apartament');

        $this->actingAs($admin);

        Livewire::test(EditEvent::class, ['record' => $event->getKey()])
            ->fillForm($this->formPayload($contractor, participantCount: 21))
            ->call('save')
            ->assertActionMounted('confirmParticipantCountRoomRefresh');

        $this->assertSame(20, (int) $event->fresh()->participant_count);
        $this->assertSame(
            'Ręczny apartament',
            $event->fresh()->hotelStays()->first()?->roomLines()->first()?->label,
        );
    }

    public function test_confirm_rebuild_refreshes_room_structure_from_template(): void
    {
        [$admin, $contractor, $event] = $this->makeEditableEventWithTemplateHotel(participantCount: 10);
        $this->attachCustomRoomStructure($event, label: 'Ręczny apartament');

        $this->actingAs($admin);

        Livewire::test(EditEvent::class, ['record' => $event->getKey()])
            ->fillForm($this->formPayload($contractor, participantCount: 20))
            ->call('save')
            ->assertActionMounted('confirmParticipantCountRoomRefresh')
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $event->refresh();
        $this->assertSame(20, (int) $event->participant_count);

        $line = $event->hotelStays()->first()?->roomLines()->first();
        $this->assertNotNull($line);
        $this->assertNotSame('Ręczny apartament', $line->label);
    }

    public function test_confirm_save_without_rebuild_keeps_room_structure(): void
    {
        [$admin, $contractor, $event] = $this->makeEditableEvent(participantCount: 20);
        $this->attachCustomRoomStructure($event, label: 'Ręczny apartament', quantity: 7);

        $this->actingAs($admin);

        Livewire::test(EditEvent::class, ['record' => $event->getKey()])
            ->fillForm($this->formPayload($contractor, participantCount: 21))
            ->call('save')
            ->assertActionMounted('confirmParticipantCountRoomRefresh')
            ->call('mountAction', 'saveWithoutRoomRefresh')
            ->call('callMountedAction')
            ->assertHasNoFormErrors();

        $event->refresh();
        $this->assertSame(21, (int) $event->participant_count);

        $line = $event->hotelStays()->first()?->roomLines()->first();
        $this->assertNotNull($line);
        $this->assertSame('Ręczny apartament', $line->label);
        $this->assertSame(7, (int) $line->quantity);
    }

    /**
     * @return array{0: User, 1: Contractor, 2: Event}
     */
    private function makeEditableEvent(int $participantCount): array
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $contractor = Contractor::create([
            'name' => 'Klient testowy',
            'email' => 'klient@example.com',
            'status' => 'active',
        ]);

        $event = Event::factory()->create([
            'participant_count' => $participantCount,
            'client_name' => 'Klient testowy',
            'contractor_id' => $contractor->id,
            'status' => Event::STATUS_CONFIRMED,
            'assigned_to' => $admin->id,
        ]);

        return [$admin, $contractor, $event];
    }

    /**
     * @return array{0: User, 1: Contractor, 2: Event}
     */
    private function makeEditableEventWithTemplateHotel(int $participantCount): array
    {
        [$admin, $contractor, $event] = $this->makeEditableEvent($participantCount);

        $template = EventTemplate::factory()->create(['duration_days' => 2]);
        $room = HotelRoom::create([
            'name' => 'Dwuosobowy',
            'people_count' => 2,
            'price' => 200,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);

        EventTemplateHotelDay::create([
            'event_template_id' => $template->id,
            'day' => 1,
            'hotel_room_ids_qty' => [$room->id],
            'hotel_room_ids_gratis' => [],
            'hotel_room_ids_staff' => [],
            'hotel_room_ids_driver' => [],
        ]);

        $event->update(['event_template_id' => $template->id]);

        return [$admin, $contractor, $event->fresh()];
    }

    private function attachCustomRoomStructure(Event $event, string $label, int $quantity = 3): void
    {
        $stay = EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 1,
        ]);

        $stay->roomLines()->create([
            'label' => $label,
            'role' => 'qty',
            'quantity' => $quantity,
            'people_count' => 2,
            'unit_price' => 100,
            'convert_to_pln' => true,
            'order' => 0,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formPayload(Contractor $contractor, int $participantCount): array
    {
        return [
            'participant_count' => $participantCount,
            'ordering_parties' => [
                [
                    'contact_id' => null,
                    'contractor_id' => (string) $contractor->id,
                    'department_label' => null,
                ],
            ],
        ];
    }
}
