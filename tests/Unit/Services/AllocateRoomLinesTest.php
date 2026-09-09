<?php

namespace Tests\Unit\Services;

use App\Models\HotelRoom;
use App\Services\EventHotelPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllocateRoomLinesTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_only_selected_room_types(): void
    {
        $twin = HotelRoom::create([
            'name' => 'Twin Selected',
            'people_count' => 2,
            'price' => 360,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);
        $other = HotelRoom::create([
            'name' => 'Single Not Selected',
            'people_count' => 1,
            'price' => 1,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);

        $lines = app(EventHotelPlanService::class)->allocateRoomLines(4, [$twin->id], 'qty');

        $this->assertNotEmpty($lines);
        $this->assertTrue(collect($lines)->every(fn (array $line) => (int) $line['hotel_room_id'] === $twin->id));
        $this->assertFalse(collect($lines)->contains(fn (array $line) => (int) $line['hotel_room_id'] === $other->id));
    }

    public function test_on_equal_price_prefers_fewer_larger_rooms_regardless_of_id_order(): void
    {
        // Twin ma niższe ID — stary DP brał 10× twin przy remisie kosztu.
        $twin = HotelRoom::create([
            'name' => 'Twin Equal',
            'people_count' => 2,
            'price' => 380,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);
        $triple = HotelRoom::create([
            'name' => 'Triple Equal',
            'people_count' => 3,
            'price' => 570,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);

        $service = app(EventHotelPlanService::class);

        foreach ([[$twin->id, $triple->id], [$triple->id, $twin->id]] as $ids) {
            $lines = collect($service->allocateRoomLines(20, $ids, 'qty'));

            $this->assertSame(
                20,
                $lines->sum(fn (array $line) => $line['quantity'] * $this->peopleCountFor($line['hotel_room_id']))
            );
            $this->assertSame(7, $lines->sum(fn (array $line) => $line['quantity']));
            $this->assertSame(6, (int) $lines->firstWhere('hotel_room_id', $triple->id)['quantity']);
            $this->assertSame(1, (int) $lines->firstWhere('hotel_room_id', $twin->id)['quantity']);
        }
    }

    public function test_cheaper_combination_wins_even_with_more_rooms(): void
    {
        $single = HotelRoom::create([
            'name' => 'Cheap Single',
            'people_count' => 1,
            'price' => 100,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);
        $twin = HotelRoom::create([
            'name' => 'Expensive Twin',
            'people_count' => 2,
            'price' => 250,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);

        $lines = collect(app(EventHotelPlanService::class)->allocateRoomLines(2, [$single->id, $twin->id], 'qty'));

        $this->assertSame(2, $lines->sum(fn (array $line) => $line['quantity']));
        $this->assertSame(2, (int) $lines->firstWhere('hotel_room_id', $single->id)['quantity']);
        $this->assertNull($lines->firstWhere('hotel_room_id', $twin->id));
    }

    public function test_separate_roles_do_not_share_room_pools(): void
    {
        $participantTwin = HotelRoom::create([
            'name' => 'Participant Twin',
            'people_count' => 2,
            'price' => 200,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);
        $staffSingle = HotelRoom::create([
            'name' => 'Staff Single',
            'people_count' => 1,
            'price' => 150,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);

        $service = app(EventHotelPlanService::class);
        $qtyLines = $service->allocateRoomLines(4, [$participantTwin->id], 'qty');
        $staffLines = $service->allocateRoomLines(1, [$staffSingle->id], 'staff');

        $this->assertTrue(collect($qtyLines)->every(fn (array $line) => (int) $line['hotel_room_id'] === $participantTwin->id));
        $this->assertTrue(collect($staffLines)->every(fn (array $line) => (int) $line['hotel_room_id'] === $staffSingle->id));
        $this->assertSame('qty', $qtyLines[0]['role']);
        $this->assertSame('staff', $staffLines[0]['role']);
    }

    private function peopleCountFor(int $roomId): int
    {
        return max(1, (int) HotelRoom::query()->whereKey($roomId)->value('people_count'));
    }
}
