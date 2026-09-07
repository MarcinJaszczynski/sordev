<?php

namespace Tests\Feature;

use App\Filament\Resources\EventTemplateResource\Pages\EventTemplateHotelPlanning;
use App\Models\EventTemplate;
use App\Models\HotelRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventTemplateHotelPlanningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['view event_template', 'edit event_template'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'biuro', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_admin_can_save_hotel_room_plan_and_reload_shows_selection(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $template = EventTemplate::factory()->create([
            'name' => 'Szablon z własnymi cenami',
            'duration_days' => 3,
        ]);

        $double = HotelRoom::create([
            'name' => 'Dwójka',
            'people_count' => 2,
            'price' => 250,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);
        $triple = HotelRoom::create([
            'name' => 'Trójka',
            'people_count' => 3,
            'price' => 320,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(EventTemplateHotelPlanning::class, ['record' => $template->getKey()])
            ->assertOk()
            ->assertSet('templateEditingUnlocked', true)
            ->set('hotel_days.0.hotel_room_ids_qty', [(string) $double->id, (string) $triple->id])
            ->set('hotel_days.0.hotel_room_ids_staff', [(string) $double->id])
            ->set('hotel_days.1.hotel_room_ids_qty', [(string) $triple->id])
            ->call('saveHotelDays')
            ->assertHasNoErrors()
            ->assertSet('hotel_days.0.hotel_room_ids_qty', [(string) $double->id, (string) $triple->id])
            ->assertSet('hotel_days.0.hotel_room_ids_staff', [(string) $double->id])
            ->assertSet('hotel_days.1.hotel_room_ids_qty', [(string) $triple->id]);

        $this->assertDatabaseHas('event_template_hotel_days', [
            'event_template_id' => $template->id,
            'day' => 1,
        ]);

        $day1 = $template->hotelDays()->where('day', 1)->first();
        $this->assertNotNull($day1);
        $this->assertSame([$double->id, $triple->id], $day1->hotel_room_ids_qty);
        $this->assertSame([$double->id], $day1->hotel_room_ids_staff);

        $day2 = $template->hotelDays()->where('day', 2)->first();
        $this->assertNotNull($day2);
        $this->assertSame([$triple->id], $day2->hotel_room_ids_qty);
    }

    public function test_room_search_and_add_picker_works(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $template = EventTemplate::factory()->create([
            'duration_days' => 2,
        ]);

        $twin = HotelRoom::create([
            'name' => 'Berlin Twin Szablon',
            'people_count' => 2,
            'price' => 200,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);
        HotelRoom::create([
            'name' => 'Ateny Sgl',
            'people_count' => 1,
            'price' => 150,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);

        $this->actingAs($user);

        $component = Livewire::test(EventTemplateHotelPlanning::class, ['record' => $template->getKey()])
            ->assertOk()
            ->set('hotelRoomSearch', 'Berlin')
            ->assertSee('Berlin Twin Szablon')
            ->call('addHotelRoomToDay', 0, 'qty', $twin->id)
            ->assertSet('hotel_days.0.hotel_room_ids_qty', [(string) $twin->id])
            ->assertSet('hotelRoomSearch', '')
            ->call('saveHotelDays');

        $component->assertHasNoErrors();

        $day = $template->hotelDays()->where('day', 1)->first();
        $this->assertNotNull($day);
        $this->assertSame([$twin->id], $day->hotel_room_ids_qty);
    }

    public function test_structure_preview_shows_auto_quantities_for_qty_variant(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $template = EventTemplate::factory()->create([
            'duration_days' => 2,
        ]);

        $twin = HotelRoom::create([
            'name' => 'Twin Preview',
            'people_count' => 2,
            'price' => 360,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);
        $triple = HotelRoom::create([
            'name' => 'Triple Preview',
            'people_count' => 3,
            'price' => 540,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);

        $qty = \App\Models\EventTemplateQty::create([
            'qty' => 20,
            'gratis' => 2,
            'staff' => 1,
            'driver' => 1,
        ]);

        $currency = \App\Models\Currency::query()->firstOrCreate(
            ['name' => 'PLN'],
            ['symbol' => 'PLN']
        );

        \App\Models\EventTemplatePricePerPerson::create([
            'event_template_id' => $template->id,
            'event_template_qty_id' => $qty->id,
            'currency_id' => $currency->id,
            'price_per_person' => 100,
        ]);

        $this->actingAs($user);

        $component = Livewire::test(EventTemplateHotelPlanning::class, ['record' => $template->getKey()])
            ->assertOk()
            ->assertSet('previewQty', 20)
            ->call('addHotelRoomToDay', 0, 'qty', $twin->id)
            ->call('addHotelRoomToDay', 0, 'qty', $triple->id);

        $preview = $component->instance()->structurePreviewForActiveNight();

        $this->assertSame(20, $preview['variant']['qty'] ?? null);
        $this->assertNotEmpty($preview['roles']['qty']['lines'] ?? []);

        $qtyLines = collect($preview['roles']['qty']['lines']);
        $this->assertSame(20, $qtyLines->sum(fn (array $line) => $line['quantity'] * $line['people_count']));
        $this->assertTrue($qtyLines->every(fn (array $line) => $line['quantity'] > 0));
        $this->assertTrue(
            $qtyLines->contains(fn (array $line) => in_array($line['room_id'], [$twin->id, $triple->id], true))
        );

        $component->assertSee('Automat struktury pokoi')
            ->assertSee('Triple Preview');
    }

    public function test_biuro_cannot_save_hotel_plan_while_locked(): void
    {
        $user = User::factory()->create();
        $user->assignRole('biuro');
        $user->syncPermissions(['view event_template', 'edit event_template']);

        $template = EventTemplate::factory()->create([
            'duration_days' => 2,
        ]);

        $room = HotelRoom::create([
            'name' => 'Jedynka',
            'people_count' => 1,
            'price' => 180,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(EventTemplateHotelPlanning::class, ['record' => $template->getKey()])
            ->assertOk()
            ->assertSet('templateEditingUnlocked', false)
            ->set('hotel_days.0.hotel_room_ids_qty', [(string) $room->id])
            ->call('saveHotelDays')
            ->assertSet('templateEditingUnlocked', false);

        $this->assertDatabaseMissing('event_template_hotel_days', [
            'event_template_id' => $template->id,
        ]);
    }

    public function test_biuro_can_save_hotel_plan_after_unlock(): void
    {
        $user = User::factory()->create();
        $user->assignRole('biuro');
        $user->syncPermissions(['view event_template', 'edit event_template']);

        $template = EventTemplate::factory()->create([
            'duration_days' => 2,
        ]);

        $room = HotelRoom::create([
            'name' => 'Dwójka biuro',
            'people_count' => 2,
            'price' => 200,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(EventTemplateHotelPlanning::class, ['record' => $template->getKey()])
            ->assertOk()
            ->call('unlockTemplateEditing')
            ->assertSet('templateEditingUnlocked', true)
            ->set('hotel_days.0.hotel_room_ids_qty', [(string) $room->id])
            ->call('saveHotelDays')
            ->assertHasNoErrors();

        $day = $template->hotelDays()->where('day', 1)->first();
        $this->assertNotNull($day);
        $this->assertSame([$room->id], $day->hotel_room_ids_qty);
    }
}
