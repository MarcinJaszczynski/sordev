<?php

namespace App\Filament\Resources\EventTemplateResource\Concerns;

use App\Models\HotelRoom;
use App\Services\EventHotelPlanService;
use App\Services\UnifiedPriceCalculator;
use App\Support\EventParticipantGroupLabels;
use Illuminate\Support\Facades\Log;

trait ManagesTemplateHotelDays
{
    public array $hotel_days = [];

    public string $hotelRoomSearch = '';

    public int $activeHotelNightIndex = 0;

    /** Wariant osób do podglądu automatu struktury (qty uczestników). */
    public ?int $previewQty = null;

    /** @var array<int, array{name: string, people_count: int, price: float, currency: string}>|null */
    protected ?array $hotelRoomsCatalogCache = null;

    public function bootTemplateHotelDays(): void
    {
        if ($this->record->hotelDays->count() > 0) {
            $this->loadHotelDaysFromDatabase();
        } else {
            $this->refreshHotelDays();
        }

        $this->activeHotelNightIndex = 0;
        $this->hotelRoomSearch = '';
        $this->previewQty = $this->defaultPreviewQty();
    }

    protected function defaultPreviewQty(): ?int
    {
        $qty = $this->record->qtyVariants()
            ->orderBy('qty')
            ->value('qty');

        return $qty !== null ? (int) $qty : null;
    }

    private function loadHotelDaysFromDatabase(): void
    {
        $this->hotel_days = $this->record->hotelDays()
            ->orderBy('day')
            ->get()
            ->map(fn ($day) => [
                'day' => (int) $day->day,
                // Stringi: Livewire porównuje wartości jako string.
                'hotel_room_ids_qty' => $this->normalizeRoomIdsForForm($day->hotel_room_ids_qty ?? []),
                'hotel_room_ids_gratis' => $this->normalizeRoomIdsForForm($day->hotel_room_ids_gratis ?? []),
                'hotel_room_ids_staff' => $this->normalizeRoomIdsForForm($day->hotel_room_ids_staff ?? []),
                'hotel_room_ids_driver' => $this->normalizeRoomIdsForForm($day->hotel_room_ids_driver ?? []),
                'notes' => $day->notes,
            ])->toArray();
    }

    /**
     * @return array<int, array{name: string, people_count: int, price: float, currency: string}>
     */
    public function hotelRoomsCatalogDetailed(): array
    {
        if ($this->hotelRoomsCatalogCache === null) {
            $this->hotelRoomsCatalogCache = HotelRoom::query()
                ->orderBy('name')
                ->get(['id', 'name', 'people_count', 'price', 'currency'])
                ->mapWithKeys(fn (HotelRoom $room): array => [
                    (int) $room->id => [
                        'name' => (string) $room->name,
                        'people_count' => max(1, (int) $room->people_count),
                        'price' => (float) $room->price,
                        'currency' => (string) ($room->currency ?: 'PLN'),
                    ],
                ])
                ->all();
        }

        return $this->hotelRoomsCatalogCache;
    }

    /**
     * @return array<int|string, string>
     */
    public function hotelRoomsCatalog(): array
    {
        return collect($this->hotelRoomsCatalogDetailed())
            ->mapWithKeys(fn (array $room, int $id): array => [$id => $room['name']])
            ->all();
    }

    public function hotelRoomChipLabel(int|string $roomId): string
    {
        $room = $this->hotelRoomsCatalogDetailed()[(int) $roomId] ?? null;
        if (! $room) {
            return '#'.$roomId;
        }

        return $room['name'].' ('.$room['people_count'].' os.)';
    }

    /**
     * @return array<int, string> qty => label
     */
    public function previewQtyOptions(): array
    {
        return $this->record->qtyVariants()
            ->orderBy('qty')
            ->get()
            ->mapWithKeys(function ($variant): array {
                $qty = (int) $variant->qty;
                $gratis = (int) ($variant->gratis ?? 0);
                $staff = (int) ($variant->staff ?? 0);
                $driver = (int) ($variant->driver ?? 0);
                $total = $qty + $gratis + $staff + $driver;

                return [
                    $qty => "{$qty} ucz. (+{$gratis} op., {$staff} obsł., {$driver} kier.) = {$total} os.",
                ];
            })
            ->all();
    }

    /**
     * Podgląd automatu: ile × jaki pokój dla aktywnej nocy i wybranego wariantu osób.
     * Liczy z bieżącego stanu formularza (nawet przed zapisem).
     *
     * @return array{
     *     variant: array{qty: int, gratis: int, staff: int, driver: int}|null,
     *     roles: array<string, array{people: int, lines: list<array{room_id: int, name: string, quantity: int, people_count: int, unit_price: float, currency: string}>, warning: ?string}>
     * }
     */
    public function structurePreviewForActiveNight(): array
    {
        $empty = ['variant' => null, 'roles' => []];
        $day = $this->hotel_days[$this->activeHotelNightIndex] ?? null;
        if (! $day) {
            return $empty;
        }

        $variant = $this->record->qtyVariants()
            ->when($this->previewQty, fn ($q) => $q->where('qty', $this->previewQty))
            ->orderBy('qty')
            ->first();

        if (! $variant) {
            return $empty;
        }

        $groups = [
            'qty' => (int) $variant->qty,
            'gratis' => (int) ($variant->gratis ?? 0),
            'staff' => (int) ($variant->staff ?? 0),
            'driver' => (int) ($variant->driver ?? 0),
        ];

        $allocator = app(EventHotelPlanService::class);
        $roles = [];

        foreach (EventParticipantGroupLabels::hotelRoleLabels() as $role => $label) {
            $people = $groups[$role] ?? 0;
            $roomIds = $this->normalizeRoomIdsForStorage($day["hotel_room_ids_{$role}"] ?? []);

            if ($people <= 0) {
                $roles[$role] = [
                    'label' => $label,
                    'people' => 0,
                    'lines' => [],
                    'warning' => null,
                ];

                continue;
            }

            if ($roomIds === []) {
                $roles[$role] = [
                    'label' => $label,
                    'people' => $people,
                    'lines' => [],
                    'warning' => 'Brak dozwolonych typów pokoi — wybierz typy poniżej.',
                ];

                continue;
            }

            $lines = $allocator->allocateRoomLines($people, $roomIds, $role);
            if ($lines === []) {
                $roles[$role] = [
                    'label' => $label,
                    'people' => $people,
                    'lines' => [],
                    'warning' => 'Automat nie znalazł kombinacji pokoi dla tej grupy.',
                ];

                continue;
            }

            $roles[$role] = [
                'label' => $label,
                'people' => $people,
                'lines' => collect($lines)->map(function (array $line): array {
                    $roomId = (int) ($line['hotel_room_id'] ?? 0);
                    $meta = $this->hotelRoomsCatalogDetailed()[$roomId] ?? null;

                    return [
                        'room_id' => $roomId,
                        'name' => $meta['name'] ?? ('#'.$roomId),
                        'quantity' => (int) ($line['quantity'] ?? 0),
                        'people_count' => $meta['people_count'] ?? 0,
                        'unit_price' => (float) ($line['unit_price'] ?? 0),
                        'currency' => $meta['currency'] ?? 'PLN',
                    ];
                })->values()->all(),
                'warning' => null,
            ];
        }

        return [
            'variant' => $groups,
            'roles' => $roles,
        ];
    }

    /**
     * Wyniki wyszukiwania pokoi do dodania (bez już wybranych dla roli).
     *
     * @return array<int|string, string>
     */
    public function hotelRoomSearchResults(int $dayIndex, string $role, int $limit = 25): array
    {
        $term = mb_strtolower(trim($this->hotelRoomSearch));
        if (mb_strlen($term) < 1) {
            return [];
        }

        $selected = $this->normalizeRoomIdsForForm(
            $this->hotel_days[$dayIndex]["hotel_room_ids_{$role}"] ?? []
        );
        $selectedLookup = array_fill_keys($selected, true);

        $matches = [];
        foreach ($this->hotelRoomsCatalogDetailed() as $id => $room) {
            if (isset($selectedLookup[(string) $id])) {
                continue;
            }
            $label = $room['name'].' '.$room['people_count'].' os.';
            if (! str_contains(mb_strtolower($label), $term)) {
                continue;
            }
            $matches[$id] = $room['name'].' ('.$room['people_count'].' os., '
                .number_format($room['price'], 0, ',', ' ').' '.$room['currency'].')';
            if (count($matches) >= $limit) {
                break;
            }
        }

        return $matches;
    }

    public function selectHotelNight(int $index): void
    {
        if (! isset($this->hotel_days[$index])) {
            return;
        }

        $this->activeHotelNightIndex = $index;
        $this->hotelRoomSearch = '';
    }

    public function updatedPreviewQty(): void
    {
        // Livewire re-render — podgląd czyta previewQty w structurePreviewForActiveNight().
    }

    public function addHotelRoomToDay(int $dayIndex, string $role, int|string $roomId): void
    {
        if (method_exists($this, 'canMutateEventTemplateNow') && ! $this->canMutateEventTemplateNow()) {
            return;
        }

        if (! in_array($role, ['qty', 'gratis', 'staff', 'driver'], true)) {
            return;
        }

        if (! isset($this->hotel_days[$dayIndex])) {
            return;
        }

        $key = "hotel_room_ids_{$role}";
        $ids = $this->normalizeRoomIdsForForm($this->hotel_days[$dayIndex][$key] ?? []);
        $roomId = (string) (int) $roomId;

        if ($roomId === '0' || in_array($roomId, $ids, true)) {
            return;
        }

        $ids[] = $roomId;
        $this->hotel_days[$dayIndex][$key] = $ids;
        $this->hotelRoomSearch = '';
    }

    /**
     * @return list<string>
     */
    private function normalizeRoomIdsForForm(mixed $ids): array
    {
        if (! is_array($ids)) {
            return [];
        }

        return collect($ids)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (string) (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    private function normalizeRoomIdsForStorage(mixed $ids): array
    {
        if (! is_array($ids)) {
            return [];
        }

        return collect($ids)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
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
        if (method_exists($this, 'canMutateEventTemplateNow') && ! $this->canMutateEventTemplateNow()) {
            return;
        }

        if (! isset($this->hotel_days[$dayIndex]["hotel_room_ids_{$role}"])) {
            return;
        }

        $rooms = $this->hotel_days[$dayIndex]["hotel_room_ids_{$role}"];
        $key = array_search((string) $roomId, array_map('strval', $rooms), true);

        if ($key !== false) {
            unset($rooms[$key]);
            $this->hotel_days[$dayIndex]["hotel_room_ids_{$role}"] = array_values($rooms);
        }
    }

    public function forceRefreshHotelDays(): void
    {
        $this->refreshHotelDays();
        $this->activeHotelNightIndex = 0;
        $this->hotelRoomSearch = '';
        $this->dispatch('$refresh');
    }

    public function saveHotelDays(): void
    {
        if (method_exists($this, 'canMutateEventTemplateNow') && ! $this->canMutateEventTemplateNow()) {
            \Filament\Notifications\Notification::make()
                ->title('Edycja szablonu jest zablokowana')
                ->body('Kliknij „Edytuj szablon” i potwierdź, żeby zapisać plan noclegów.')
                ->warning()
                ->send();

            return;
        }

        try {
            $this->saveHotelDaysToDatabase();
            $this->recalculateTemplatePricesAfterHotelChange();
            $this->record->unsetRelation('hotelDays');
            $this->loadHotelDaysFromDatabase();

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

    protected function recalculateTemplatePricesAfterHotelChange(): void
    {
        try {
            (new UnifiedPriceCalculator)->recalculateForTemplate($this->record);
        } catch (\Throwable $e) {
            Log::warning('ManagesTemplateHotelDays: price recalc after hotel save failed', [
                'event_template_id' => $this->record->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function saveHotelDaysToDatabase(): void
    {
        $existingDays = $this->record->hotelDays()->get()->keyBy(fn ($day) => (int) $day->day);

        foreach ($this->hotel_days as $dayData) {
            $day = (int) ($dayData['day'] ?? 0);
            if ($day < 1) {
                continue;
            }

            $data = [
                'hotel_room_ids_qty' => $this->normalizeRoomIdsForStorage($dayData['hotel_room_ids_qty'] ?? []),
                'hotel_room_ids_gratis' => $this->normalizeRoomIdsForStorage($dayData['hotel_room_ids_gratis'] ?? []),
                'hotel_room_ids_staff' => $this->normalizeRoomIdsForStorage($dayData['hotel_room_ids_staff'] ?? []),
                'hotel_room_ids_driver' => $this->normalizeRoomIdsForStorage($dayData['hotel_room_ids_driver'] ?? []),
                'notes' => $dayData['notes'] ?? null,
            ];

            if ($existingDays->has($day)) {
                $existingDays[$day]->update($data);
            } else {
                $this->record->hotelDays()->create(array_merge($data, ['day' => $day]));
            }
        }

        $currentDays = collect($this->hotel_days)
            ->pluck('day')
            ->map(fn ($day) => (int) $day)
            ->filter(fn (int $day) => $day > 0)
            ->values();

        // Pusty stan UI przy duration ≥ 2 nie powinien kasować planu (np. utrata stanu Livewire).
        $expectedNights = max(0, (int) ($this->record->duration_days ?? 1) - 1);
        if ($currentDays->isEmpty() && $expectedNights > 0 && $existingDays->isNotEmpty()) {
            throw new \RuntimeException(
                'Brak danych noclegów do zapisu — odśwież stronę i spróbuj ponownie.'
            );
        }

        $existingDays->whereNotIn('day', $currentDays->all())->each->delete();
    }

    public function refreshHotelDays(): void
    {
        $days = $this->record->duration_days ?? 1;
        $nights = max(0, (int) $days - 1);

        $existingData = $this->hotel_days;
        $this->hotel_days = [];

        for ($i = 1; $i <= $nights; $i++) {
            if (isset($existingData[$i - 1])) {
                $row = $existingData[$i - 1];
                $row['day'] = $i;
                foreach (['qty', 'gratis', 'staff', 'driver'] as $role) {
                    $key = "hotel_room_ids_{$role}";
                    $row[$key] = $this->normalizeRoomIdsForForm($row[$key] ?? []);
                }
                $this->hotel_days[] = $row;
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

        if ($this->activeHotelNightIndex >= count($this->hotel_days)) {
            $this->activeHotelNightIndex = max(0, count($this->hotel_days) - 1);
        }

        Log::info('RefreshHotelDays result: '.count($this->hotel_days).' nights for template '.$this->record->id);
    }
}
