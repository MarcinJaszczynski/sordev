<?php

namespace App\Livewire;

use App\Filament\Resources\ContractorResource;
use App\Filament\Resources\EventResource;
use App\Models\Contractor;
use App\Models\ContractorLocation;
use App\Models\ContractorType;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\HotelRoom;
use App\Services\EventHotelOccupantsImporter;
use App\Services\EventHotelPlanService;
use App\Services\ContractorLocationService;
use App\Services\ContractorLookupService;
use App\Support\ContractorContactDetails;
use App\Support\EventHotelPlanFormatting;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class EventHotelPlanEditor extends Component
{
    use WithFileUploads;

    public int $eventId;

    /** @var array<int, array<string, mixed>> */
    public array $stays = [];

    public int $activeStayIndex = 0;

    /** 1 = struktura pokoi (biuro), 2 = lista osób */
    public int $activeStep = 1;

    public ?int $copySourceDay = 1;

    /** @var array<int, int> */
    public array $copyTargetDays = [];

    /** @var TemporaryUploadedFile|null */
    public $importFile = null;

    public string $hotelPricingMode = 'lines';

    public ?string $hotelFlatStayAmount = null;

    public ?int $hotelFlatStayCurrencyId = null;

    public bool $hotelFlatStayConvertToPln = true;

    public string $hotelContractorSearch = '';

    public bool $hotelContractorSearchAll = false;

    
    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
        $this->loadPlan();
    }

    public function loadPlan(): void
    {
        $event = Event::query()->with([
            'hotelStays.roomLines.occupants',
            'hotelStays.contractor',
            'hotelProgramPoints',
        ])->findOrFail($this->eventId);

        app(EventHotelPlanService::class)->ensureStaysForEvent($event);
        app(EventHotelPlanService::class)->syncAllRoomUnitsForEvent($event);
        $event->refresh()->load(['hotelStays.roomLines.occupants', 'hotelStays.roomLines.units', 'hotelStays.roomLines.hotelRoom', 'hotelStays.contractor']);

        $this->hotelPricingMode = $event->hotel_pricing_mode ?? 'lines';
        $this->hotelFlatStayAmount = $event->hotel_flat_stay_amount !== null
            ? (string) $event->hotel_flat_stay_amount
            : null;
        $this->hotelFlatStayCurrencyId = $event->hotel_flat_stay_currency_id;
        $this->hotelFlatStayConvertToPln = (bool) ($event->hotel_flat_stay_convert_to_pln ?? true);

        $this->stays = app(EventHotelPlanService::class)->staysToPayload($event);

        if ($this->stays === []) {
            $this->stays = [];
        }

        $this->copySourceDay = (int) ($this->stays[0]['day'] ?? 1);
        $this->copyTargetDays = collect($this->stays)->pluck('day')->map(fn ($d) => (int) $d)->all();
    }

    private function afterStayMutation(): void
    {
        // No auto-sync
    }

    public function selectStay(int $index): void
    {
        $this->activeStayIndex = max(0, min($index, count($this->stays) - 1));
    }

    public function goToStep(int $step): void
    {
        $this->activeStep = max(1, min(2, $step));
    }

    public function saveAndContinue(): void
    {
        if (! $this->save() || ! EventHotelPlanFormatting::structureReady($this->stays)) {
            return;
        }

        $this->activeStep = 2;
    }

    public function addRoomLine(): void
    {
        if (! isset($this->stays[$this->activeStayIndex])) {
            return;
        }

        $this->stays[$this->activeStayIndex]['room_lines'][] = [
            'id' => null,
            'hotel_room_id' => null,
            'label' => null,
            'role' => 'qty',
            'quantity' => 1,
            'people_count' => null,
            'unit_price' => 0,
            'price_basis' => \App\Models\EventHotelRoomLine::PRICE_BASIS_PER_ROOM,
            'currency_id' => Currency::query()->where('symbol', 'PLN')->value('id'),
            'convert_to_pln' => true,
            'occupants' => [],
        ];

        $this->afterStayMutation();
    }

    public function removeRoomLine(int $lineIndex): void
    {
        unset($this->stays[$this->activeStayIndex]['room_lines'][$lineIndex]);
        $this->stays[$this->activeStayIndex]['room_lines'] = array_values($this->stays[$this->activeStayIndex]['room_lines']);
        $this->afterStayMutation();
    }

    public function updated($property, $value): void
    {
        if (preg_match('/^stays\.(\d+)\.contractor_id$/', (string) $property, $matches)) {
            $stayIndex = (int) $matches[1];
            $contractorId = filled($value) ? (int) $value : null;

            app(ContractorLocationService::class)->syncLocationOnContractorChange(
                fn (string $field, mixed $state) => data_set($this->stays[$stayIndex], $field, $state),
                $contractorId,
            );

            $this->persistStayContractor($stayIndex);
        }

        if (preg_match('/^stays\.(\d+)\.contractor_location_id$/', (string) $property, $matches)) {
            $this->persistStayContractor((int) $matches[1]);
        }
    }

    public function updatedStays($value, $name): void
    {
        if (preg_match('/stays\.(\d+)\.room_lines\.(\d+)\.hotel_room_id/', (string) $name, $m)) {
            $stayIndex = (int) $m[1];
            $lineIndex = (int) $m[2];
            $roomId = $this->stays[$stayIndex]['room_lines'][$lineIndex]['hotel_room_id'] ?? null;
            if ($roomId) {
                $room = HotelRoom::find($roomId);
                if ($room) {
                    $this->stays[$stayIndex]['room_lines'][$lineIndex]['unit_price'] = (float) $room->price;
                    $this->stays[$stayIndex]['room_lines'][$lineIndex]['people_count'] = (int) ($room->capacity ?? $room->people_count ?? 1);
                    $this->stays[$stayIndex]['room_lines'][$lineIndex]['currency_id'] = Currency::query()
                        ->where('symbol', $room->currency)->value('id');
                    $this->stays[$stayIndex]['room_lines'][$lineIndex]['convert_to_pln'] = (bool) ($room->convert_to_pln ?? true);
                    $this->stays[$stayIndex]['room_lines'][$lineIndex]['label'] = null;
                }
            }
        }
    }

    public function updateSlotOccupant(int $lineIndex, int $unitIndex, int $bedIndex, ?string $name): void
    {
        if (! isset($this->stays[$this->activeStayIndex]['room_lines'][$lineIndex])) {
            return;
        }

        $occupants = &$this->stays[$this->activeStayIndex]['room_lines'][$lineIndex]['occupants'];
        $occupants = array_values(array_filter(
            $occupants,
            fn (array $occupant) => (int) ($occupant['unit_index'] ?? 1) !== $unitIndex
                || (int) ($occupant['bed_index'] ?? 1) !== $bedIndex
        ));

        $name = trim((string) $name);
        if ($name === '') {
            return;
        }

        $occupants[] = [
            'id' => null,
            'name' => $name,
            'source' => 'manual',
            'unit_index' => $unitIndex,
            'bed_index' => $bedIndex,
            'event_agreement_id' => null,
            'contract_id' => null,
            'reservation_id' => null,
            'participant_key' => null,
        ];

        $this->afterStayMutation();
    }

    public function assignSlotParticipant(int $lineIndex, int $unitIndex, int $bedIndex, string $participantKey): void
    {
        if ($participantKey === '' || ! isset($this->stays[$this->activeStayIndex]['room_lines'][$lineIndex])) {
            return;
        }

        $event = Event::findOrFail($this->eventId);
        $allParticipants = app(EventHotelPlanService::class)->availableParticipants($event);
        $participant = collect($allParticipants)->firstWhere('key', $participantKey);

        if (! $participant) {
            return;
        }

        $available = $this->participantsAvailableForSlot(
            $allParticipants,
            $this->activeStayIndex,
            $lineIndex,
            $unitIndex,
            $bedIndex
        );

        if (! collect($available)->contains(fn (array $item) => $item['key'] === $participantKey)) {
            Notification::make()
                ->title('Ta osoba jest już przypisana w tej nocy')
                ->warning()
                ->send();

            return;
        }

        $occupants = &$this->stays[$this->activeStayIndex]['room_lines'][$lineIndex]['occupants'];
        $occupants = array_values(array_filter(
            $occupants,
            fn (array $occupant) => (int) ($occupant['unit_index'] ?? 1) !== $unitIndex
                || (int) ($occupant['bed_index'] ?? 1) !== $bedIndex
        ));

        $occupants[] = [
            'id' => null,
            'name' => $participant['label'],
            'source' => $participant['source'],
            'unit_index' => $unitIndex,
            'bed_index' => $bedIndex,
            'event_agreement_id' => $participant['agreement_id'] ?? null,
            'contract_id' => $participant['contract_id'] ?? null,
            'reservation_id' => $participant['reservation_id'] ?? null,
            'participant_key' => $participantKey,
        ];

        $this->afterStayMutation();
    }

    public function clearSlotOccupant(int $lineIndex, int $unitIndex, int $bedIndex): void
    {
        if (! isset($this->stays[$this->activeStayIndex]['room_lines'][$lineIndex])) {
            return;
        }

        $occupants = &$this->stays[$this->activeStayIndex]['room_lines'][$lineIndex]['occupants'];
        $occupants = array_values(array_filter(
            $occupants,
            fn (array $occupant) => (int) ($occupant['unit_index'] ?? 1) !== $unitIndex
                || (int) ($occupant['bed_index'] ?? 1) !== $bedIndex
        ));

        $this->afterStayMutation();
    }

    public function addManualOccupant(int $lineIndex, string $name = ''): void
    {
        $name = trim($name);
        if ($name === '') {
            return;
        }

        $this->stays[$this->activeStayIndex]['room_lines'][$lineIndex]['occupants'][] = [
            'id' => null,
            'name' => $name,
            'source' => 'manual',
            'event_agreement_id' => null,
            'contract_id' => null,
            'reservation_id' => null,
            'participant_key' => null,
        ];
    }

    public function addParticipantToRoom(int $lineIndex, string $participantKey): void
    {
        $event = Event::findOrFail($this->eventId);
        $participant = collect(app(EventHotelPlanService::class)->availableParticipants($event))
            ->firstWhere('key', $participantKey);

        if (! $participant) {
            return;
        }

        $this->stays[$this->activeStayIndex]['room_lines'][$lineIndex]['occupants'][] = [
            'id' => null,
            'name' => $participant['label'],
            'source' => $participant['source'],
            'event_agreement_id' => $participant['agreement_id'] ?? null,
            'contract_id' => $participant['contract_id'] ?? null,
            'reservation_id' => $participant['reservation_id'] ?? null,
            'participant_key' => $participantKey,
        ];
    }

    public function removeOccupant(int $lineIndex, int $occupantIndex): void
    {
        unset($this->stays[$this->activeStayIndex]['room_lines'][$lineIndex]['occupants'][$occupantIndex]);
        $this->stays[$this->activeStayIndex]['room_lines'][$lineIndex]['occupants'] = array_values(
            $this->stays[$this->activeStayIndex]['room_lines'][$lineIndex]['occupants']
        );
    }

    public function save(): bool
    {
        $event = Event::findOrFail($this->eventId);

        try {
            app(EventHotelPlanService::class)->saveEventHotelPricing($event, [
                'hotel_pricing_mode' => $this->hotelPricingMode,
                'hotel_flat_stay_amount' => $this->hotelFlatStayAmount,
                'hotel_flat_stay_currency_id' => $this->hotelFlatStayCurrencyId,
                'hotel_flat_stay_convert_to_pln' => $this->hotelFlatStayConvertToPln,
            ]);
            app(EventHotelPlanService::class)->savePlan($event, $this->stays);
            app(EventHotelPlanService::class)->linkStaysToProgramPoints($event->fresh());
            $this->loadPlan();
            $this->dispatch('event-price-table-refresh');
            Notification::make()->title('Plan hoteli zapisany')->success()->send();

            return true;
        } catch (\Illuminate\Validation\ValidationException $e) {
            Notification::make()
                ->title('Nie zapisano planu')
                ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                ->danger()
                ->send();

            return false;
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Nie zapisano planu')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return false;
        }
    }

    protected function persistStayContractor(int $stayIndex): void
    {
        $stayPayload = $this->stays[$stayIndex] ?? null;
        if (! is_array($stayPayload) || empty($stayPayload['id'])) {
            return;
        }

        $contractorId = $stayPayload['contractor_id'] ?? null;
        $contractorId = filled($contractorId) ? (int) $contractorId : null;
        $locationId = $stayPayload['contractor_location_id'] ?? null;
        $locationId = filled($locationId) ? (int) $locationId : null;

        try {
            $event = Event::findOrFail($this->eventId);
            $stay = $event->hotelStays()->find((int) $stayPayload['id']);

            if (! $stay) {
                return;
            }

            $update = ['contractor_id' => $contractorId];

            if (\Illuminate\Support\Facades\Schema::hasColumn('event_hotel_stays', 'contractor_location_id')) {
                $update['contractor_location_id'] = $locationId;
            }

            $stay->update($update);
            app(EventHotelPlanService::class)->linkStaysToProgramPoints($event->fresh(['hotelStays']));

            $freshStay = $event->fresh(['hotelStays'])->hotelStays->firstWhere('id', (int) $stayPayload['id']);

            $this->stays[$stayIndex]['contractor_id'] = $contractorId;
            $this->stays[$stayIndex]['contractor_location_id'] = $locationId;
            $this->stays[$stayIndex]['event_program_point_id'] = $freshStay?->event_program_point_id;

            Notification::make()
                ->title($contractorId ? 'Hotel zapisany' : 'Hotel usunięty z nocy')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Nie zapisano hotelu')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function copyOccupantsToAllNights(): void
    {
        $event = Event::findOrFail($this->eventId);
        $sourceDay = (int) ($this->stays[$this->activeStayIndex]['day'] ?? $this->copySourceDay);
        app(EventHotelPlanService::class)->copyOccupantsToAllStays($event, $sourceDay);
        $this->loadPlan();
        Notification::make()->title('Skopiowano listę osób na wszystkie noce o tej samej strukturze')->success()->send();
    }

    public function copyToAllNights(): void
    {
        $event = Event::findOrFail($this->eventId);
        $sourceDay = (int) ($this->stays[$this->activeStayIndex]['day'] ?? $this->copySourceDay);
        app(EventHotelPlanService::class)->copyStructureToAllStays($event, $sourceDay);
        $this->loadPlan();
        Notification::make()->title('Skopiowano strukturę na wszystkie noce')->success()->send();
    }

    public function copyToSelectedDays(): void
    {
        $event = Event::findOrFail($this->eventId);
        $sourceDay = (int) $this->copySourceDay;
        app(EventHotelPlanService::class)->copyStructureToDays($event, $sourceDay, $this->copyTargetDays);
        $this->loadPlan();
        Notification::make()->title('Skopiowano na wybrane noce')->success()->send();
    }

        public function initializeEmptyPlan(): void
    {
        $event = Event::findOrFail($this->eventId);
        app(EventHotelPlanService::class)->ensureStaysForEvent($event);
        $this->loadPlan();
        Notification::make()->title('Utworzono pusty plan hoteli')->success()->send();
    }

    public function restoreFromTemplate(): void
    {
        $event = Event::findOrFail($this->eventId);
        app(EventHotelPlanService::class)->snapshotFromTemplate($event, replaceExisting: true);
        app(EventHotelPlanService::class)->linkStaysToProgramPoints($event->fresh());
        $this->loadPlan();
        Notification::make()->title('Przywrócono plan z szablonu')->success()->send();
    }

    public function applySameHotelEverywhere(): void
    {
        $contractorId = $this->stays[$this->activeStayIndex]['contractor_id'] ?? null;
        if (! $contractorId) {
            Notification::make()->title('Wybierz hotel dla aktywnej nocy')->warning()->send();

            return;
        }

        $event = Event::findOrFail($this->eventId);
        $sourceDay = (int) ($this->stays[$this->activeStayIndex]['day'] ?? 1);
        app(EventHotelPlanService::class)->applySameHotelAllNights($event, (int) $contractorId, $sourceDay);
        $this->loadPlan();
        Notification::make()->title('Ten sam hotel ustawiony na wszystkie noce')->success()->send();
    }

    public function importOccupants(): void
    {
        $this->validate([
            'importFile' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ]);

        $path = $this->importFile?->getRealPath();
        if (! $path || ! is_file($path)) {
            Notification::make()->title('Nie udało się odczytać pliku')->danger()->send();

            return;
        }

        $event = Event::findOrFail($this->eventId);

        try {
            $result = app(EventHotelOccupantsImporter::class)->importFromPath($event, $path);
            $this->importFile = null;
            $this->loadPlan();

            $body = "Zaimportowano {$result['imported']} osób.";
            if ($result['skipped'] > 0) {
                $body .= " Pominięto {$result['skipped']} wierszy.";
            }

            Notification::make()
                ->title('Import zakończony')
                ->body($body)
                ->success()
                ->send();

            if ($result['warnings'] !== []) {
                Notification::make()
                    ->title('Uwagi z importu')
                    ->body(implode("\n", array_slice($result['warnings'], 0, 5)))
                    ->warning()
                    ->send();
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            Notification::make()
                ->title('Import nieudany')
                ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getTotalPlnProperty(): float
    {
        $currencies = Currency::query()->pluck('symbol', 'id');

        return EventHotelPlanFormatting::eventTotalPln(
            $this->stays,
            $this->hotelPricingMode,
            $this->hotelFlatStayAmount !== null && $this->hotelFlatStayAmount !== ''
                ? (float) $this->hotelFlatStayAmount
                : null,
            $this->hotelFlatStayCurrencyId,
            $this->hotelFlatStayConvertToPln,
            $currencies,
        );
    }

    public function getTotalDisplayProperty(): string
    {
        $currencies = Currency::query()->pluck('symbol', 'id');

        return EventHotelPlanFormatting::eventTotalDisplay(
            $this->stays,
            $this->hotelPricingMode,
            $this->hotelFlatStayAmount !== null && $this->hotelFlatStayAmount !== ''
                ? (float) $this->hotelFlatStayAmount
                : null,
            $this->hotelFlatStayCurrencyId,
            $this->hotelFlatStayConvertToPln,
            $currencies,
        );
    }

    public function getUsesLinePricingProperty(): bool
    {
        return EventHotelPlanFormatting::usesLinePricing($this->hotelPricingMode);
    }

    public function getActiveStayUsesLinePricingProperty(): bool
    {
        $stay = $this->stays[$this->activeStayIndex] ?? null;
        if (! $stay) {
            return true;
        }

        return EventHotelPlanFormatting::usesLinePricing(
            $this->hotelPricingMode,
            $stay['pricing_mode'] ?? 'lines'
        );
    }

    public function getStructureReadyProperty(): bool
    {
        return EventHotelPlanFormatting::structureReady($this->stays);
    }

    /**
     * @param  array<string, mixed>  $occupant
     */
    private function resolveOccupantParticipantKey(array $occupant): ?string
    {
        if (! empty($occupant['participant_key'])) {
            return (string) $occupant['participant_key'];
        }

        if (! empty($occupant['event_agreement_id'])) {
            return 'agreement:'.$occupant['event_agreement_id'];
        }

        if (! empty($occupant['contract_id'])) {
            return 'contract:'.$occupant['contract_id'];
        }

        if (! empty($occupant['reservation_id'])) {
            return 'reservation:'.$occupant['reservation_id'];
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function assignedParticipantKeysForStay(int $stayIndex): array
    {
        $keys = [];

        foreach ($this->stays[$stayIndex]['room_lines'] ?? [] as $line) {
            foreach ($line['occupants'] ?? [] as $occupant) {
                $key = $this->resolveOccupantParticipantKey($occupant);
                if ($key) {
                    $keys[] = $key;
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param  array<int, array<string, mixed>>  $participants
     * @return array<int, array<string, mixed>>
     */
    public function participantsAvailableForSlot(
        array $participants,
        int $stayIndex,
        int $lineIndex,
        int $unitIndex,
        int $bedIndex,
    ): array {
        $currentKey = null;

        foreach ($this->stays[$stayIndex]['room_lines'][$lineIndex]['occupants'] ?? [] as $occupant) {
            if ((int) ($occupant['unit_index'] ?? 1) === $unitIndex
                && (int) ($occupant['bed_index'] ?? 1) === $bedIndex) {
                $currentKey = $this->resolveOccupantParticipantKey($occupant);
                break;
            }
        }

        $assigned = $this->assignedParticipantKeysForStay($stayIndex);
        if ($currentKey) {
            $assigned = array_values(array_diff($assigned, [$currentKey]));
        }

        return collect($participants)
            ->reject(fn (array $participant) => in_array($participant['key'], $assigned, true))
            ->values()
            ->all();
    }

    public function render()
    {
        $event = Event::findOrFail($this->eventId);
        $hotelRooms = HotelRoom::query()->orderBy('name')->get();
        $activeContractorId = filled($this->stays[$this->activeStayIndex]['contractor_id'] ?? null)
            ? (int) $this->stays[$this->activeStayIndex]['contractor_id']
            : null;
        $activeLocationId = filled($this->stays[$this->activeStayIndex]['contractor_location_id'] ?? null)
            ? (int) $this->stays[$this->activeStayIndex]['contractor_location_id']
            : null;
        $lookup = app(ContractorLookupService::class);
        $locationService = app(ContractorLocationService::class);
        $hotels = $lookup->searchOptions(
            search: $this->hotelContractorSearch,
            typeNames: ContractorType::hotelTypeNames(),
            searchAll: $this->hotelContractorSearchAll,
            includeId: $activeContractorId,
        );
        $hotelLabels = $hotels;
        $locationOptions = $locationService->optionsForContractor(
            contractorId: $activeContractorId,
            includeId: $activeLocationId,
        );
        $showLocationSelect = $locationService->contractorRequiresLocationSelection($activeContractorId);

        $contractorIds = collect($this->stays)->pluck('contractor_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $locationIds = collect($this->stays)->pluck('contractor_location_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $contractorsById = Contractor::query()->whereIn('id', $contractorIds)->get()->keyBy('id');
        $locationsById = ContractorLocation::query()->whereIn('id', $locationIds)->get()->keyBy('id');

        foreach ($contractorIds as $contractorId) {
            if (isset($hotelLabels[$contractorId])) {
                continue;
            }

            $contractor = $contractorsById->get($contractorId);

            if ($contractor) {
                $hotelLabels[$contractorId] = $lookup->formatOptionLabel($contractor);
            }
        }

        $stayContactHints = [];
        foreach ($this->stays as $index => $stayPayload) {
            $contractor = filled($stayPayload['contractor_id'] ?? null)
                ? $contractorsById->get((int) $stayPayload['contractor_id'])
                : null;
            $location = filled($stayPayload['contractor_location_id'] ?? null)
                ? $locationsById->get((int) $stayPayload['contractor_location_id'])
                : null;
            $meta = ContractorContactDetails::operationalMeta($contractor, $location);
            $stayContactHints[$index] = [
                'phone' => $meta['phone'] ?? null,
                'city' => $location?->city ?: ($contractor?->city ?: null),
                'address' => filled($meta['address'] ?? null) ? Str::limit((string) $meta['address'], 48) : null,
            ];
        }

        $activeContractor = $activeContractorId ? $contractorsById->get($activeContractorId) : null;
        if ($activeContractorId && ! $activeContractor) {
            $activeContractor = Contractor::query()->find($activeContractorId);
        }
        $activeLocation = $activeLocationId ? $locationsById->get($activeLocationId) : null;
        if ($activeLocationId && ! $activeLocation) {
            $activeLocation = ContractorLocation::query()->find($activeLocationId);
        }

        $linkedPointId = filled($this->stays[$this->activeStayIndex]['event_program_point_id'] ?? null)
            ? (int) $this->stays[$this->activeStayIndex]['event_program_point_id']
            : null;
        $linkedProgramPoint = $linkedPointId
            ? EventProgramPoint::query()->with('templatePoint')->find($linkedPointId)
            : null;

        $contractorEditUrl = $activeContractor
            ? ContractorResource::getUrl('edit', ['record' => $activeContractor])
            : null;
        $programDay = (int) ($this->stays[$this->activeStayIndex]['day'] ?? 1);
        $programUrl = EventResource::getUrl('edit-program', ['record' => $event]).'?day='.$programDay;

        return view('livewire.event-hotel-plan-editor', [
            'event' => $event,
            'hotels' => $hotels,
            'hotelLabels' => $hotelLabels,
            'locationOptions' => $locationOptions,
            'showLocationSelect' => $showLocationSelect,
            'hotelRooms' => $hotelRooms,
            'hotelRoomsById' => $hotelRooms->keyBy('id'),
            'currencies' => Currency::query()->orderBy('symbol')->pluck('symbol', 'id'),
            'roles' => \App\Models\EventHotelRoomLine::ROLES,
            'priceBasisOptions' => \App\Models\EventHotelRoomLine::priceBasisOptions(),
            'participants' => app(EventHotelPlanService::class)->availableParticipants($event),
            'formatting' => EventHotelPlanFormatting::class,
            'activeContractor' => $activeContractor,
            'activeLocation' => $activeLocation,
            'linkedProgramPoint' => $linkedProgramPoint,
            'contractorEditUrl' => $contractorEditUrl,
            'programUrl' => $programUrl,
            'stayContactHints' => $stayContactHints,
        ]);
    }
}
