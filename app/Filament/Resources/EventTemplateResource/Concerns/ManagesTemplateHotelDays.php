<?php

namespace App\Filament\Resources\EventTemplateResource\Concerns;

use Illuminate\Support\Facades\Log;

trait ManagesTemplateHotelDays
{
    public array $hotel_days = [];

    public function bootTemplateHotelDays(): void
    {
        if ($this->record->hotelDays->count() > 0) {
            $this->loadHotelDaysFromDatabase();
        } else {
            $this->refreshHotelDays();
        }
    }

    private function loadHotelDaysFromDatabase(): void
    {
        $this->hotel_days = $this->record->hotelDays()
            ->orderBy('day')
            ->get()
            ->map(fn ($day) => [
                'day' => $day->day,
                'hotel_room_ids_qty' => $day->hotel_room_ids_qty ?? [],
                'hotel_room_ids_gratis' => $day->hotel_room_ids_gratis ?? [],
                'hotel_room_ids_staff' => $day->hotel_room_ids_staff ?? [],
                'hotel_room_ids_driver' => $day->hotel_room_ids_driver ?? [],
                'notes' => $day->notes,
            ])->toArray();
    }

    public function addDay(): void
    {
        $this->hotel_days[] = [
            'day' => count($this->hotel_days) + 1,
            'hotel_room_ids_qty' => [],
            'hotel_room_ids_gratis' => [],
            'hotel_room_ids_staff' => [],
            'hotel_room_ids_driver' => [],
            'notes' => null,
        ];
    }

    public function addRoom($role, $dayIndex): void
    {
        if (! isset($this->hotel_days[$dayIndex]["hotel_room_ids_{$role}"])) {
            $this->hotel_days[$dayIndex]["hotel_room_ids_{$role}"] = [];
        }
        $this->hotel_days[$dayIndex]["hotel_room_ids_{$role}"][] = null;
    }

    public function copyToNextDay($dayIndex): void
    {
        if (! isset($this->hotel_days[$dayIndex + 1])) {
            return;
        }
        foreach (['qty', 'gratis', 'staff', 'driver'] as $role) {
            $this->hotel_days[$dayIndex + 1]["hotel_room_ids_{$role}"] =
                $this->hotel_days[$dayIndex]["hotel_room_ids_{$role}"] ?? [];
        }
        $this->hotel_days[$dayIndex + 1]['notes'] = $this->hotel_days[$dayIndex]['notes'] ?? null;
    }

    public function copyToAllDays(int $sourceIndex = 0): void
    {
        if (! isset($this->hotel_days[$sourceIndex])) {
            return;
        }

        foreach ($this->hotel_days as $index => $day) {
            if ($index === $sourceIndex) {
                continue;
            }
            foreach (['qty', 'gratis', 'staff', 'driver'] as $role) {
                $this->hotel_days[$index]["hotel_room_ids_{$role}"] =
                    $this->hotel_days[$sourceIndex]["hotel_room_ids_{$role}"] ?? [];
            }
            $this->hotel_days[$index]['notes'] = $this->hotel_days[$sourceIndex]['notes'] ?? null;
        }
    }

    public function copyFromDayToTargets(int $sourceIndex, array $targetDayNumbers): void
    {
        if (! isset($this->hotel_days[$sourceIndex])) {
            return;
        }

        $sourceDay = (int) ($this->hotel_days[$sourceIndex]['day'] ?? ($sourceIndex + 1));

        foreach ($this->hotel_days as $index => $day) {
            $dayNumber = (int) ($day['day'] ?? ($index + 1));
            if ($dayNumber === $sourceDay || ! in_array($dayNumber, array_map('intval', $targetDayNumbers), true)) {
                continue;
            }

            foreach (['qty', 'gratis', 'staff', 'driver'] as $role) {
                $this->hotel_days[$index]["hotel_room_ids_{$role}"] =
                    $this->hotel_days[$sourceIndex]["hotel_room_ids_{$role}"] ?? [];
            }
            $this->hotel_days[$index]['notes'] = $this->hotel_days[$sourceIndex]['notes'] ?? null;
        }
    }

    public function removeRoomFromDay($dayIndex, $role, $roomId): void
    {
        if (! isset($this->hotel_days[$dayIndex]["hotel_room_ids_{$role}"])) {
            return;
        }

        $rooms = $this->hotel_days[$dayIndex]["hotel_room_ids_{$role}"];
        $key = array_search($roomId, $rooms);

        if ($key !== false) {
            unset($rooms[$key]);
            $this->hotel_days[$dayIndex]["hotel_room_ids_{$role}"] = array_values($rooms);
        }
    }

    public function forceRefreshHotelDays(): void
    {
        $this->refreshHotelDays();
        $this->dispatch('$refresh');
    }

    public function saveHotelDays(): void
    {
        try {
            $this->saveHotelDaysToDatabase();
            \Filament\Notifications\Notification::make()
                ->title('Noclegi zostały zapisane')
                ->success()
                ->send();
        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Błąd podczas zapisywania noclegów')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function saveHotelDaysToDatabase(): void
    {
        $existingDays = $this->record->hotelDays()->get()->keyBy('day');

        foreach ($this->hotel_days as $dayData) {
            $day = $dayData['day'];

            $data = [
                'hotel_room_ids_qty' => $dayData['hotel_room_ids_qty'] ?? [],
                'hotel_room_ids_gratis' => $dayData['hotel_room_ids_gratis'] ?? [],
                'hotel_room_ids_staff' => $dayData['hotel_room_ids_staff'] ?? [],
                'hotel_room_ids_driver' => $dayData['hotel_room_ids_driver'] ?? [],
                'notes' => $dayData['notes'] ?? null,
            ];

            if ($existingDays->has($day)) {
                $existingDays[$day]->update($data);
            } else {
                $this->record->hotelDays()->create(array_merge($data, ['day' => $day]));
            }
        }

        $currentDays = collect($this->hotel_days)->pluck('day');
        $existingDays->whereNotIn('day', $currentDays)->each->delete();
    }

    public function refreshHotelDays(): void
    {
        $days = $this->record->duration_days ?? 1;
        $nights = max(0, (int) $days - 1);

        $existingData = $this->hotel_days;
        $this->hotel_days = [];

        for ($i = 1; $i <= $nights; $i++) {
            if (isset($existingData[$i - 1])) {
                $this->hotel_days[] = $existingData[$i - 1];
            } else {
                $this->hotel_days[] = [
                    'day' => $i,
                    'hotel_room_ids_qty' => [],
                    'hotel_room_ids_gratis' => [],
                    'hotel_room_ids_staff' => [],
                    'hotel_room_ids_driver' => [],
                    'notes' => null,
                ];
            }
        }

        Log::info('RefreshHotelDays result: '.count($this->hotel_days).' nights for template '.$this->record->id);
    }
}
