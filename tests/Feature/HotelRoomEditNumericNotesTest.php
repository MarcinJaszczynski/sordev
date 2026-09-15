<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\HotelRoomResource\Pages\EditHotelRoom;
use App\Models\HotelRoom;
use App\Models\User;
use App\Support\SafeTiptapConverter;
use FilamentTiptapEditor\TiptapConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HotelRoomEditNumericNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_hotel_room_with_numeric_notes_can_change_price(): void
    {
        Role::findOrCreate('admin');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $room = HotelRoom::create([
            'name' => 'Warszawa Trpl Szablon',
            'people_count' => 3,
            'price' => 570,
            'currency' => 'PLN',
            'convert_to_pln' => true,
            'notes' => '570',
        ]);

        Livewire::test(EditHotelRoom::class, ['record' => $room->getKey()])
            ->assertSuccessful()
            ->fillForm([
                'name' => 'Warszawa Trpl Szablon',
                'people_count' => 3,
                'price' => 620,
                'currency' => 'PLN',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals(620.0, (float) $room->fresh()->price);
    }

    public function test_tiptap_converter_accepts_numeric_notes_without_type_error(): void
    {
        $converter = app(TiptapConverter::class);

        $this->assertInstanceOf(SafeTiptapConverter::class, $converter);
        $this->assertStringContainsString('570', $converter->asHTML('570'));
        $this->assertSame('doc', $converter->asJSON('570', decoded: true)['type'] ?? null);
    }
}
