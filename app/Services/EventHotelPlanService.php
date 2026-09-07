<?php

namespace App\Services;

use App\Actions\Events\RecalculateEventTotalsAction;
use App\Data\RecalculateEventTotalsData;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventAgreement;
use App\Models\EventHotelRoomLine;
use App\Models\EventHotelRoomOccupant;
use App\Models\EventHotelRoomUnit;
use App\Models\EventHotelStay;
use App\Models\EventProgramPoint;
use App\Models\EventTemplateHotelDay;
use App\Models\HotelRoom;
use App\Support\ContractorContactDetails;
use App\Support\EventHotelPlanFormatting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class EventHotelPlanService
{
    /**
     * @return array<int, array{key: string, label: string, source: string, agreement_id?: int, contract_id?: int, reservation_id?: int}>
     */
    public function availableParticipants(Event $event): array
    {
        $participants = [];

        if (Schema::hasTable('contracts')) {
            foreach ($event->agreements()->get() as $contract) {
                if (! $contract instanceof Contract) {
                    continue;
                }
                $name = trim((string) ($contract->participant_name ?? $contract->client_name ?? ''));
                if ($name === '') {
                    continue;
                }
                $participants[] = [
                    'key' => 'contract:'.$contract->id,
                    'label' => $name,
                    'source' => 'agreement',
                    'contract_id' => $contract->id,
                ];
            }
        } else {
            foreach ($event->agreements()->get() as $agreement) {
                if (! $agreement instanceof EventAgreement) {
                    continue;
                }
                $name = trim((string) ($agreement->participant_name ?? ''));
                if ($name === '') {
                    continue;
                }
                $participants[] = [
                    'key' => 'agreement:'.$agreement->id,
                    'label' => $name,
                    'source' => 'agreement',
                    'agreement_id' => $agreement->id,
                ];
            }
        }

        foreach ($event->reservations()->with('contractor')->get() as $reservation) {
            $label = trim((string) ($reservation->booking_reference ?: $reservation->contractor?->name ?: 'Rezerwacja #'.$reservation->id));
            $participants[] = [
                'key' => 'reservation:'.$reservation->id,
                'label' => $label.' ('.$reservation->participant_count.' os.)',
                'source' => 'reservation',
                'reservation_id' => $reservation->id,
            ];
        }

        if (Schema::hasTable('event_participants')) {
            foreach ($event->activeParticipants()->get() as $participant) {
                $name = trim($participant->fullName());
                if ($name === '') {
                    continue;
                }

                $key = 'participant:'.$participant->id;
                if (collect($participants)->contains(fn (array $row): bool => $row['key'] === $key)) {
                    continue;
                }

                $participants[] = [
                    'key' => $key,
                    'label' => $name,
                    'source' => 'participant',
                    'event_participant_id' => $participant->id,
                    'contract_id' => $participant->contract_id,
                    'agreement_id' => $participant->event_agreement_id,
                ];
            }
        }

        usort($participants, fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return $participants;
    }

    public function snapshotFromTemplate(Event $event, bool $replaceExisting = false): void
    {
        if (! Schema::hasTable('event_hotel_stays')) {
            return;
        }

        $template = $event->eventTemplate;
        if (! $template) {
            return;
        }

        if ($replaceExisting) {
            $event->hotelStays()->each(function (EventHotelStay $stay) {
                $stay->roomLines()->each(fn (EventHotelRoomLine $line) => $line->occupants()->delete());
                $stay->roomLines()->delete();
            });
            $event->hotelStays()->delete();
        } elseif ($event->hotelStays()->exists()) {
            return;
        }

        $groupCounts = $this->resolveAllocationGroupCounts($event);
        $hotelDays = $template->hotelDays()->orderBy('day')->get();
        $hotelPointsByDay = $event->hotelProgramPoints()->get()->keyBy('day');

        if ($hotelDays->isEmpty()) {
            $nights = max(0, (int) ($event->duration_days ?? 1) - 1);
            for ($day = 1; $day <= $nights; $day++) {
                $this->createStayFromDay($event, $day, null, $groupCounts, $hotelPointsByDay->get($day));
            }

            return;
        }

        foreach ($hotelDays as $hotelDay) {
            $this->createStayFromDay($event, (int) $hotelDay->day, $hotelDay, $groupCounts, $hotelPointsByDay->get((int) $hotelDay->day));
        }
    }

    /**
     * @param  array{qty: int, gratis: int, staff: int, driver: int}  $groupCounts
     */
    private function createStayFromDay(
        Event $event,
        int $day,
        ?EventTemplateHotelDay $hotelDay,
        array $groupCounts,
        ?EventProgramPoint $programPoint = null,
    ): EventHotelStay {
        $stay = $event->hotelStays()->create([
            'day' => $day,
            'contractor_id' => $programPoint?->contractor_id,
            'event_program_point_id' => $programPoint?->id,
            'notes' => $hotelDay?->notes,
        ]);

        if ($hotelDay) {
            $order = 0;
            foreach (['qty', 'gratis', 'staff', 'driver'] as $role) {
                $roomIds = $hotelDay->{"hotel_room_ids_{$role}"} ?? [];
                $peopleCount = $groupCounts[$role] ?? 0;
                if ($peopleCount <= 0 || empty($roomIds)) {
                    continue;
                }

                foreach ($this->allocateRoomLines($peopleCount, $roomIds, $role) as $lineData) {
                    $stay->roomLines()->create(array_merge($lineData, ['order' => $order++]));
                }
            }
        }

        return $stay;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allocateRoomLines(int $peopleCount, array $roomIds, string $role): array
    {
        if ($peopleCount <= 0 || $roomIds === []) {
            return [];
        }

        $rooms = HotelRoom::query()->whereIn('id', $roomIds)->get();
        if ($rooms->isEmpty()) {
            return [];
        }

        $maxCapacity = $rooms->sum('people_count') * max(1, $peopleCount);
        $dp = array_fill(0, $maxCapacity + 1, INF);
        $dp[0] = 0;
        $choice = array_fill(0, $maxCapacity + 1, null);

        foreach ($rooms as $room) {
            $capacity = max(1, (int) $room->people_count);
            for ($i = $capacity; $i <= $maxCapacity; $i++) {
                if ($dp[$i] > $dp[$i - $capacity] + (float) $room->price) {
                    $dp[$i] = $dp[$i - $capacity] + (float) $room->price;
                    $choice[$i] = $room->id;
                }
            }
        }

        $minCost = INF;
        $bestI = null;
        for ($i = $peopleCount; $i <= $maxCapacity; $i++) {
            if ($dp[$i] < $minCost) {
                $minCost = $dp[$i];
                $bestI = $i;
            }
        }

        if ($minCost === INF || $bestI === null) {
            return [];
        }

        $roomCounts = [];
        $i = $bestI;
        while ($i > 0 && $choice[$i] !== null) {
            $roomId = $choice[$i];
            $room = $rooms->firstWhere('id', $roomId);
            if (! $room) {
                break;
            }
            $roomCounts[$roomId] = ($roomCounts[$roomId] ?? 0) + 1;
            $i -= max(1, (int) $room->people_count);
        }

        $lines = [];
        foreach ($roomCounts as $roomId => $quantity) {
            $room = $rooms->firstWhere('id', $roomId);
            if (! $room) {
                continue;
            }
            $currencyId = Currency::query()->where('symbol', $room->currency)->value('id');

            $lines[] = [
                'hotel_room_id' => $room->id,
                'label' => null,
                'role' => $role,
                'quantity' => $quantity,
                'unit_price' => (float) $room->price,
                'price_basis' => EventHotelRoomLine::PRICE_BASIS_PER_ROOM,
                'currency_id' => $currencyId,
                'convert_to_pln' => (bool) ($room->convert_to_pln ?? true),
            ];
        }

        return $lines;
    }

    /**
     * @return array{qty: int, gratis: int, staff: int, driver: int}
     */
    public function resolveGroupCounts(Event $event): array
    {
        $participantCount = max(1, (int) ($event->participant_count ?? 1));
        $qtyVariant = $event->qtyVariants()
            ->get()
            ->sortBy(fn ($v) => abs(((int) ($v->qty ?? 0)) - $participantCount))
            ->first();

        return [
            'qty' => $participantCount,
            'gratis' => (int) ($qtyVariant->gratis ?? 0),
            'staff' => (int) ($qtyVariant->staff ?? 0),
            'driver' => (int) ($qtyVariant->driver ?? 0),
        ];
    }

    /**
     * Liczebność grup do algorytmu DP pokoi — wyłącznie z wariantu qty (staff/driver),
     * niezależnie od przypisania pilota/kierowcy do imprezy.
     *
     * @return array{qty: int, gratis: int, staff: int, driver: int}
     */
    public function resolveAllocationGroupCounts(Event $event): array
    {
        return $this->resolveGroupCounts($event);
    }

    /**
     * Czy impreza ma już strukturę pokoi, którą warto chronić przed automatycznym resetem
     * (np. przy zmianie liczby uczestników).
     */
    public function eventHasRoomStructureWorthProtecting(Event $event): bool
    {
        if (! Schema::hasTable('event_hotel_stays') || ! Schema::hasTable('event_hotel_room_lines')) {
            return false;
        }

        return $event->hotelStays()->whereHas('roomLines')->exists();
    }

    /**
     * Uzupełnia strukturę pokoi (linie, bez uczestników) wg szablonu i aktualnych liczności grup.
     * Używane przy tworzeniu imprezy, zmianie liczby osób oraz gdy nocleg istnieje bez linii pokoi.
     * Przy onlyEmptyStays: nie rusza nocy, które już mają jakąkolwiek ręczną strukturę.
     *
     * @return bool true gdy coś zapisano
     */
    public function refreshRoomStructureFromTemplate(Event $event, bool $onlyEmptyStays = false): bool
    {
        if (! Schema::hasTable('event_hotel_stays')) {
            return false;
        }

        $template = $event->eventTemplate;
        if (! $template) {
            return false;
        }

        $hotelDays = $template->hotelDays()->orderBy('day')->get()->keyBy('day');
        if ($hotelDays->isEmpty()) {
            return false;
        }

        $event->load(['hotelStays.roomLines']);

        $allocationCounts = $this->resolveAllocationGroupCounts($event);
        $hotelPointsByDay = $event->hotelProgramPoints()->get()->keyBy('day');
        $changed = false;

        foreach ($event->hotelStays as $stay) {
            $hotelDay = $hotelDays->get((int) $stay->day);
            if (! $hotelDay) {
                continue;
            }

            if ($onlyEmptyStays && $stay->roomLines()->exists()) {
                continue;
            }

            $this->replaceStayRoomLines($stay, $hotelDay, $allocationCounts);
            $changed = true;
        }

        if ($changed) {
            $this->syncAllRoomUnitsForEvent($event->fresh());
            app(EventParticipantPropagationService::class)->assignOperationalOccupants($event->fresh());
            $this->linkStaysToProgramPoints($event->fresh());
        }

        return $changed;
    }

    /**
     * @param  array{qty: int, gratis: int, staff: int, driver: int}  $allocationCounts
     */
    private function replaceStayRoomLines(EventHotelStay $stay, EventTemplateHotelDay $hotelDay, array $allocationCounts): void
    {
        $stay->roomLines()->each(function (EventHotelRoomLine $line) {
            $line->occupants()->delete();
            $line->units()->delete();
        });
        $stay->roomLines()->delete();

        $order = 0;
        foreach (['qty', 'gratis', 'staff', 'driver'] as $role) {
            $roomIds = $hotelDay->{"hotel_room_ids_{$role}"} ?? [];
            $peopleCount = $allocationCounts[$role] ?? 0;
            if ($peopleCount <= 0 || empty($roomIds)) {
                continue;
            }

            foreach ($this->allocateRoomLines($peopleCount, $roomIds, $role) as $lineData) {
                $stay->roomLines()->create(array_merge($lineData, ['order' => $order++]));
            }
        }
    }

    private function templateHotelDaysByDay(Event $event): Collection
    {
        $template = $event->eventTemplate;
        if (! $template) {
            return collect();
        }

        return $template->hotelDays()->orderBy('day')->get()->keyBy('day');
    }

    /**
     * Kopiuje ułożenie pokoi (linie + obsada).
     * Hotel / rezerwacja nocy docelowej zmieniają się tylko gdy $copyHotel = true
     * (przycisk „Ten sam hotel na wszystkie noce”).
     */
    public function copyStructureToStay(EventHotelStay $source, EventHotelStay $target, bool $copyHotel = true): void
    {
        DB::transaction(function () use ($source, $target, $copyHotel) {
            $target->roomLines()->each(function (EventHotelRoomLine $line) {
                $line->units()->delete();
                $line->occupants()->delete();
            });
            $target->roomLines()->delete();

            $update = [
                'offer_notes' => $source->offer_notes,
                'pricing_mode' => $source->pricing_mode ?? 'lines',
                'flat_amount' => $source->flat_amount,
                'flat_currency_id' => $source->flat_currency_id,
            ];

            if ($copyHotel) {
                $update['contractor_id'] = $source->contractor_id;
                $update['event_program_point_id'] = $source->event_program_point_id;
                $update['same_as_day'] = $source->day;

                if (Schema::hasColumn('event_hotel_stays', 'contractor_location_id')) {
                    $update['contractor_location_id'] = $source->contractor_location_id;
                }
            }

            $target->update($update);

            foreach ($source->roomLines()->with(['occupants', 'units'])->orderBy('order')->get() as $line) {
                $newLine = $target->roomLines()->create($line->only([
                    'hotel_room_id', 'label', 'role', 'quantity', 'people_count', 'unit_price', 'price_basis', 'currency_id', 'convert_to_pln', 'order',
                ]));

                $this->syncRoomUnits($newLine);

                foreach ($line->occupants as $occupant) {
                    $newLine->occupants()->create($occupant->only([
                        'name', 'source', 'unit_index', 'bed_index',
                        'event_agreement_id', 'contract_id', 'reservation_id', 'order',
                    ]));
                }
            }
        });
    }

    public function copyStructureToAllStays(Event $event, int $sourceDay): void
    {
        $source = $event->hotelStays()->where('day', $sourceDay)->with('roomLines.occupants')->first();
        if (! $source) {
            return;
        }

        foreach ($event->hotelStays()->where('day', '!=', $sourceDay)->get() as $target) {
            $this->copyStructureToStay($source, $target, copyHotel: false);
        }

        $source->update(['same_as_day' => null]);
        $this->syncEventFinanceAfterHotelChange($event->fresh());
    }

    public function copyStructureToDays(Event $event, int $sourceDay, array $targetDays): void
    {
        $source = $event->hotelStays()->where('day', $sourceDay)->with('roomLines.occupants')->first();
        if (! $source) {
            return;
        }

        foreach ($targetDays as $day) {
            $target = $event->hotelStays()->where('day', (int) $day)->first();
            if ($target && (int) $day !== $sourceDay) {
                $this->copyStructureToStay($source, $target, copyHotel: false);
            }
        }

        $this->syncEventFinanceAfterHotelChange($event->fresh());
    }

    public function copyOccupantsToAllStays(Event $event, int $sourceDay): void
    {
        $payloads = collect($this->staysToPayload($event))->keyBy('day');
        $source = $payloads->get($sourceDay);
        if (! $source) {
            return;
        }

        $updated = $payloads->all();
        foreach ($updated as $day => $stayPayload) {
            if ((int) $day === $sourceDay) {
                continue;
            }

            if (! $this->stayStructuresMatch($source, $stayPayload)) {
                continue;
            }

            $this->syncOccupantsOntoStayPayload($source, $updated[$day]);
        }

        $this->savePlan($event, array_values($updated));
    }

    /**
     * @param  array<int, array<string, mixed>>  $stays
     */
    public function syncPayloadCollectionFromStayIndex(array &$stays, int $sourceIndex): void
    {
        $source = $stays[$sourceIndex] ?? null;
        if (! $source) {
            return;
        }

        foreach ($stays as $index => &$stay) {
            if ($index === $sourceIndex) {
                continue;
            }

            $this->syncStayPayloadOntoTarget($source, $stay);
        }
        unset($stay);
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $target
     */
    public function syncStayPayloadOntoTarget(array $source, array &$target): void
    {
        $target['contractor_id'] = $source['contractor_id'] ?? null;
        $target['contractor_location_id'] = $source['contractor_location_id'] ?? null;
        $target['offer_notes'] = $source['offer_notes'] ?? null;
        $target['notes'] = $source['notes'] ?? null;
        $target['pricing_mode'] = $source['pricing_mode'] ?? 'lines';
        $target['flat_amount'] = $source['flat_amount'] ?? null;
        $target['flat_currency_id'] = $source['flat_currency_id'] ?? null;
        $target['same_as_day'] = $source['day'] ?? null;

        $targetLines = [];
        foreach ($source['room_lines'] ?? [] as $lineIndex => $sourceLine) {
            $targetLine = $target['room_lines'][$lineIndex] ?? [];
            $mergedLine = [
                'id' => $targetLine['id'] ?? null,
                'hotel_room_id' => $sourceLine['hotel_room_id'] ?? null,
                'label' => $sourceLine['label'] ?? null,
                'role' => $sourceLine['role'] ?? 'qty',
                'quantity' => $sourceLine['quantity'] ?? 1,
                'people_count' => $sourceLine['people_count'] ?? null,
                'unit_price' => $sourceLine['unit_price'] ?? 0,
                'price_basis' => $sourceLine['price_basis'] ?? EventHotelRoomLine::PRICE_BASIS_PER_ROOM,
                'currency_id' => $sourceLine['currency_id'] ?? null,
                'convert_to_pln' => $sourceLine['convert_to_pln'] ?? true,
                'units' => $targetLine['units'] ?? ($sourceLine['units'] ?? []),
                'occupants' => [],
            ];

            $existingBySlot = collect($targetLine['occupants'] ?? [])->keyBy(
                fn (array $o) => ((int) ($o['unit_index'] ?? 1)).':'.((int) ($o['bed_index'] ?? 1))
            );

            foreach ($sourceLine['occupants'] ?? [] as $sourceOccupant) {
                $slotKey = ((int) ($sourceOccupant['unit_index'] ?? 1)).':'.((int) ($sourceOccupant['bed_index'] ?? 1));
                $existing = $existingBySlot->get($slotKey, []);

                $mergedLine['occupants'][] = [
                    'id' => $existing['id'] ?? null,
                    'name' => $sourceOccupant['name'] ?? '',
                    'source' => $sourceOccupant['source'] ?? 'manual',
                    'unit_index' => (int) ($sourceOccupant['unit_index'] ?? 1),
                    'bed_index' => (int) ($sourceOccupant['bed_index'] ?? 1),
                    'event_agreement_id' => $sourceOccupant['event_agreement_id'] ?? null,
                    'contract_id' => $sourceOccupant['contract_id'] ?? null,
                    'reservation_id' => $sourceOccupant['reservation_id'] ?? null,
                    'participant_key' => $sourceOccupant['participant_key'] ?? null,
                ];
            }

            $targetLines[] = $mergedLine;
        }

        $target['room_lines'] = $targetLines;
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $target
     */
    public function syncOccupantsOntoStayPayload(array $source, array &$target): void
    {
        foreach ($source['room_lines'] ?? [] as $lineIndex => $sourceLine) {
            if (! isset($target['room_lines'][$lineIndex])) {
                continue;
            }

            $targetLine = &$target['room_lines'][$lineIndex];
            $existingBySlot = collect($targetLine['occupants'] ?? [])->keyBy(
                fn (array $o) => ((int) ($o['unit_index'] ?? 1)).':'.((int) ($o['bed_index'] ?? 1))
            );

            $occupants = [];
            foreach ($sourceLine['occupants'] ?? [] as $sourceOccupant) {
                $slotKey = ((int) ($sourceOccupant['unit_index'] ?? 1)).':'.((int) ($sourceOccupant['bed_index'] ?? 1));
                $existing = $existingBySlot->get($slotKey, []);

                $occupants[] = [
                    'id' => $existing['id'] ?? null,
                    'name' => $sourceOccupant['name'] ?? '',
                    'source' => $sourceOccupant['source'] ?? 'manual',
                    'unit_index' => (int) ($sourceOccupant['unit_index'] ?? 1),
                    'bed_index' => (int) ($sourceOccupant['bed_index'] ?? 1),
                    'event_agreement_id' => $sourceOccupant['event_agreement_id'] ?? null,
                    'contract_id' => $sourceOccupant['contract_id'] ?? null,
                    'reservation_id' => $sourceOccupant['reservation_id'] ?? null,
                    'participant_key' => $sourceOccupant['participant_key'] ?? null,
                ];
            }

            $targetLine['occupants'] = $occupants;
            unset($targetLine);
        }
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    public function stayStructuresMatch(array $a, array $b): bool
    {
        $linesA = $a['room_lines'] ?? [];
        $linesB = $b['room_lines'] ?? [];

        if (count($linesA) !== count($linesB)) {
            return false;
        }

        foreach ($linesA as $index => $lineA) {
            $lineB = $linesB[$index] ?? null;
            if (! $lineB) {
                return false;
            }

            if ((int) ($lineA['hotel_room_id'] ?? 0) !== (int) ($lineB['hotel_room_id'] ?? 0)) {
                return false;
            }

            if (trim((string) ($lineA['label'] ?? '')) !== trim((string) ($lineB['label'] ?? ''))) {
                return false;
            }

            if ((int) ($lineA['quantity'] ?? 0) !== (int) ($lineB['quantity'] ?? 0)) {
                return false;
            }

            if ((int) ($lineA['people_count'] ?? 0) !== (int) ($lineB['people_count'] ?? 0)) {
                return false;
            }
        }

        return true;
    }

    public function applySameHotelAllNights(Event $event, int $contractorId, ?int $sourceDay = 1): void
    {
        $source = $event->hotelStays()->where('day', $sourceDay)->with('roomLines.occupants')->first();
        if (! $source) {
            return;
        }

        foreach ($event->hotelStays as $stay) {
            if ((int) $stay->day === $sourceDay) {
                $stay->update(['same_as_day' => null]);

                continue;
            }

            $this->copyStructureToStay($source, $stay);
            $stay->update(['same_as_day' => $sourceDay]);
        }

        $this->syncEventFinanceAfterHotelChange($event->fresh());
    }

    public function ensureStaysForEvent(Event $event): void
    {
        if (! Schema::hasTable('event_hotel_stays')) {
            return;
        }

        $nights = max(0, (int) ($event->duration_days ?? 1) - 1);
        if ($nights === 0) {
            return;
        }

        if (! $event->hotelStays()->exists()) {
            $this->snapshotFromTemplate($event);
        }

        $groupCounts = $this->resolveAllocationGroupCounts($event);
        $hotelPointsByDay = $event->hotelProgramPoints()->get()->keyBy('day');
        $hotelDaysByDay = $this->templateHotelDaysByDay($event);
        $existingDays = $event->hotelStays()->pluck('day')->all();

        for ($day = 1; $day <= $nights; $day++) {
            if (! in_array($day, $existingDays, true)) {
                $this->createStayFromDay(
                    $event,
                    $day,
                    $hotelDaysByDay->get($day),
                    $groupCounts,
                    $hotelPointsByDay->get($day),
                );
            }
        }

        $this->refreshRoomStructureFromTemplate($event, onlyEmptyStays: true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function staysToPayload(Event $event): array
    {
        $event->loadMissing([
            'hotelStays.roomLines.occupants',
            'hotelStays.roomLines.units',
            'hotelStays.roomLines.hotelRoom',
            'hotelStays.contractor',
            'hotelStays.contractorLocation',
        ]);

        return $event->hotelStays->map(function (EventHotelStay $stay) {
            return [
                'id' => $stay->id,
                'day' => $stay->day,
                'contractor_id' => $stay->contractor_id,
                'contractor_location_id' => Schema::hasColumn('event_hotel_stays', 'contractor_location_id')
                    ? $stay->contractor_location_id
                    : null,
                'event_program_point_id' => $stay->event_program_point_id,
                'reservation_id' => Schema::hasColumn('event_hotel_stays', 'reservation_id')
                    ? $stay->reservation_id
                    : null,
                'offer_notes' => $stay->offer_notes,
                'notes' => $stay->notes,
                'same_as_day' => $stay->same_as_day,
                'pricing_mode' => $stay->pricing_mode ?? 'lines',
                'flat_amount' => $stay->flat_amount,
                'flat_currency_id' => $stay->flat_currency_id,
                'flat_convert_to_pln' => (bool) ($stay->flat_convert_to_pln ?? true),
                'room_lines' => $stay->roomLines->map(function (EventHotelRoomLine $line) {
                    $occupants = $line->occupants->map(fn (EventHotelRoomOccupant $occupant) => [
                        'id' => $occupant->id,
                        'name' => $occupant->name,
                        'source' => $occupant->source,
                        'unit_index' => $occupant->unit_index,
                        'bed_index' => $occupant->bed_index,
                        'event_agreement_id' => $occupant->event_agreement_id,
                        'contract_id' => $occupant->contract_id,
                        'event_participant_id' => $occupant->event_participant_id,
                        'reservation_id' => $occupant->reservation_id,
                        'participant_key' => $occupant->participantKey(),
                    ])->values()->all();

                    return [
                        'id' => $line->id,
                        'hotel_room_id' => $line->hotel_room_id,
                        'label' => $line->label,
                        'role' => $line->role,
                        'quantity' => $line->quantity,
                        'people_count' => $line->people_count,
                        'unit_price' => $line->unit_price,
                        'price_basis' => $line->resolvedPriceBasis(),
                        'currency_id' => $line->currency_id,
                        'convert_to_pln' => $line->convert_to_pln,
                        'units' => $line->units->map(fn (EventHotelRoomUnit $unit) => [
                            'id' => $unit->id,
                            'unit_index' => $unit->unit_index,
                            'room_number' => $unit->room_number,
                        ])->values()->all(),
                        'occupants' => $this->normalizeOccupantsToSlots([
                            'quantity' => $line->quantity,
                            'people_count' => $line->people_count,
                            'hotel_room_id' => $line->hotel_room_id,
                            'occupants' => $occupants,
                        ]),
                    ];
                })->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<int, array<string, mixed>>
     */
    public function normalizeOccupantsToSlots(array $line): array
    {
        $occupants = $line['occupants'] ?? [];
        if ($occupants === []) {
            return [];
        }

        $hasExplicitSlots = collect($occupants)->contains(
            fn (array $o) => ((int) ($o['unit_index'] ?? 1)) > 1 || ((int) ($o['bed_index'] ?? 1)) > 1
        );

        $qty = max(1, (int) ($line['quantity'] ?? 1));
        $beds = max(1, (int) ($line['people_count'] ?? 1));

        if ($hasExplicitSlots) {
            return $occupants;
        }

        if (count($occupants) <= 1) {
            return $occupants;
        }

        $normalized = [];
        $index = 0;

        for ($unit = 1; $unit <= $qty; $unit++) {
            for ($bed = 1; $bed <= $beds; $bed++) {
                if (! isset($occupants[$index])) {
                    break 2;
                }

                $normalized[] = array_merge($occupants[$index], [
                    'unit_index' => $unit,
                    'bed_index' => $bed,
                ]);
                $index++;
            }
        }

        return $normalized;
    }

    public function saveEventHotelPricing(Event $event, array $pricing): void
    {
        if (! Schema::hasColumn('events', 'hotel_pricing_mode')) {
            return;
        }

        $event->update([
            'hotel_pricing_mode' => $pricing['hotel_pricing_mode'] ?? 'lines',
            'hotel_flat_stay_amount' => ($pricing['hotel_flat_stay_amount'] ?? '') !== ''
                ? (float) $pricing['hotel_flat_stay_amount']
                : null,
            'hotel_flat_stay_currency_id' => $pricing['hotel_flat_stay_currency_id'] ?? null,
            'hotel_flat_stay_convert_to_pln' => (bool) ($pricing['hotel_flat_stay_convert_to_pln'] ?? true),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $stayPayloads
     */
    public function savePlan(Event $event, array $stayPayloads): void
    {
        $this->validateNoDuplicateOccupants($stayPayloads);

        DB::transaction(function () use ($event, $stayPayloads) {
            foreach ($stayPayloads as $payload) {
                $stayId = $payload['id'] ?? null;
                $stay = $stayId
                    ? $event->hotelStays()->findOrFail($stayId)
                    : $event->hotelStays()->create(['day' => (int) $payload['day']]);

                $stay->update([
                    'contractor_id' => filled($payload['contractor_id'] ?? null) ? (int) $payload['contractor_id'] : null,
                    'contractor_location_id' => Schema::hasColumn('event_hotel_stays', 'contractor_location_id')
                        ? (filled($payload['contractor_location_id'] ?? null) ? (int) $payload['contractor_location_id'] : null)
                        : null,
                    'event_program_point_id' => ! empty($payload['event_program_point_id']) ? $payload['event_program_point_id'] : null,
                    'offer_notes' => $payload['offer_notes'] ?? null,
                    'notes' => $payload['notes'] ?? null,
                    'same_as_day' => ! empty($payload['same_as_day']) ? $payload['same_as_day'] : null,
                    'pricing_mode' => $payload['pricing_mode'] ?? 'lines',
                    'flat_amount' => ($payload['flat_amount'] ?? '') !== '' ? (float) $payload['flat_amount'] : null,
                    'flat_currency_id' => ! empty($payload['flat_currency_id']) ? $payload['flat_currency_id'] : null,
                    'flat_convert_to_pln' => (bool) ($payload['flat_convert_to_pln'] ?? true),
                ]);

                $existingLineIds = [];
                foreach ($payload['room_lines'] ?? [] as $order => $linePayload) {
                    $lineId = $linePayload['id'] ?? null;
                    $line = $lineId
                        ? $stay->roomLines()->findOrFail($lineId)
                        : $stay->roomLines()->make();

                    $line->fill([
                        'hotel_room_id' => ! empty($linePayload['hotel_room_id']) ? $linePayload['hotel_room_id'] : null,
                        'label' => $linePayload['label'] ?? null,
                        'role' => $linePayload['role'] ?? 'qty',
                        'quantity' => max(1, (int) ($linePayload['quantity'] ?? 1)),
                        'people_count' => isset($linePayload['people_count']) && $linePayload['people_count'] !== ''
                            ? max(1, (int) $linePayload['people_count'])
                            : null,
                        'unit_price' => (float) ($linePayload['unit_price'] ?? 0),
                        'price_basis' => EventHotelRoomLine::normalizePriceBasis($linePayload['price_basis'] ?? null),
                        'currency_id' => ! empty($linePayload['currency_id']) ? $linePayload['currency_id'] : null,
                        'convert_to_pln' => (bool) ($linePayload['convert_to_pln'] ?? true),
                        'order' => $order,
                    ]);
                    $line->event_hotel_stay_id = $stay->id;
                    $line->save();
                    $existingLineIds[] = $line->id;

                    $this->syncRoomUnits($line);

                    $existingOccupantIds = [];
                    foreach ($linePayload['occupants'] ?? [] as $occOrder => $occPayload) {
                        $occId = $occPayload['id'] ?? null;
                        $occupant = $occId
                            ? $line->occupants()->findOrFail($occId)
                            : $line->occupants()->make();

                        $occupant->fill([
                            'name' => trim((string) ($occPayload['name'] ?? '')),
                            'source' => $occPayload['source'] ?? 'manual',
                            'unit_index' => max(1, (int) ($occPayload['unit_index'] ?? 1)),
                            'bed_index' => max(1, (int) ($occPayload['bed_index'] ?? 1)),
                            'event_agreement_id' => $occPayload['event_agreement_id'] ?? null,
                            'contract_id' => $occPayload['contract_id'] ?? null,
                            'event_participant_id' => $occPayload['event_participant_id'] ?? null,
                            'reservation_id' => $occPayload['reservation_id'] ?? null,
                            'order' => $occOrder,
                        ]);
                        if (! empty($occPayload['event_participant_id']) && $occupant->name === '') {
                            $participant = \App\Models\EventParticipant::query()->find($occPayload['event_participant_id']);
                            if ($participant) {
                                $occupant->name = $participant->fullName();
                            }
                        }
                        $occupant->event_hotel_room_line_id = $line->id;
                        $occupant->save();
                        $existingOccupantIds[] = $occupant->id;
                    }

                    $line->occupants()->whereNotIn('id', $existingOccupantIds)->delete();
                }

                $stay->roomLines()->whereNotIn('id', $existingLineIds)->each(function (EventHotelRoomLine $line) {
                    $line->units()->delete();
                    $line->occupants()->delete();
                    $line->delete();
                });
            }
        });

        // Struktura/wycena pokoi → baza kalkulacji + koszt planowany (accommodation) w rozliczeniu.
        // Wzorzec jak po zapisie transportu / usług hotelowych.
        $this->syncEventFinanceAfterHotelChange($event);
    }

    /**
     * Przelicza total_cost imprezy i odświeża aktywne rozliczenie po zmianie planu hotelowego.
     * Cena/os. z kalkulacji (do umowy/aneksu / bez ręcznej blokady) bierze się z EventCostCalculator.
     */
    public function syncEventFinanceAfterHotelChange(Event $event): void
    {
        EventCostCalculator::clearRequestCache();

        $fresh = $event->fresh([
            'bus',
            'programPoints',
            'qtyVariants',
            'hotelStays.roomLines.currency',
            'eventTemplate.markup',
            'eventTemplate.taxes',
            'markup',
        ]) ?? $event;

        try {
            app(RecalculateEventTotalsAction::class)(new RecalculateEventTotalsData(
                event: $fresh,
                participantCount: max(1, (int) ($fresh->participant_count ?? 1)),
                startPlaceId: $fresh->start_place_id ? (int) $fresh->start_place_id : null,
                persist: true,
            ));
        } catch (\Throwable $e) {
            Log::warning('EventHotelPlanService: recalculate totals after hotel plan failed', [
                'event_id' => $fresh->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $fresh->refreshActiveSettlementCosts();
        } catch (\Throwable $e) {
            Log::warning('EventHotelPlanService: refresh settlement after hotel plan failed', [
                'event_id' => $fresh->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            app(HotelStaySettlementSync::class)->syncForEvent($fresh);
        } catch (\Throwable $e) {
            Log::warning('EventHotelPlanService: hotel settlement sync failed', [
                'event_id' => $fresh->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function syncAllRoomUnitsForEvent(Event $event): void
    {
        if (! Schema::hasTable('event_hotel_room_units')) {
            return;
        }

        $event->loadMissing('hotelStays.roomLines');
        foreach ($event->hotelStays as $stay) {
            foreach ($stay->roomLines as $line) {
                $this->syncRoomUnits($line);
            }
        }
    }

    public function syncRoomUnits(EventHotelRoomLine $line): void
    {
        if (! Schema::hasTable('event_hotel_room_units')) {
            return;
        }

        $quantity = max(1, (int) $line->quantity);
        $existing = $line->units()->orderBy('unit_index')->get()->keyBy('unit_index');

        for ($index = 1; $index <= $quantity; $index++) {
            if (! $existing->has($index)) {
                $line->units()->create(['unit_index' => $index]);
            }
        }

        $line->units()->where('unit_index', '>', $quantity)->delete();
    }

    /**
     * @param  array<int, string|null>  $roomNumbers  unit_id => room_number
     */
    public function updateUnitRoomNumbers(Event $event, array $roomNumbers): void
    {
        if (! Schema::hasTable('event_hotel_room_units')) {
            return;
        }

        $event->loadMissing('hotelStays.roomLines.units');
        $allowedUnitIds = $event->hotelStays
            ->flatMap(fn (EventHotelStay $stay) => $stay->roomLines)
            ->flatMap(fn (EventHotelRoomLine $line) => $line->units)
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($roomNumbers, $allowedUnitIds): void {
            foreach ($roomNumbers as $unitId => $roomNumber) {
                $unitId = (int) $unitId;
                if (! in_array($unitId, $allowedUnitIds, true)) {
                    continue;
                }

                $unit = EventHotelRoomUnit::query()->find($unitId);
                if (! $unit) {
                    continue;
                }

                $unit->update([
                    'room_number' => ($roomNumber = trim((string) $roomNumber)) !== '' ? $roomNumber : null,
                ]);
            }
        });
    }

    /**
     * Aktualizacja ręcznie wpisanych nazwisk w wolnych slotach (portal pilota).
     *
     * @param  array<string, array<int, string|null>>  $occupantNames  "{unit_id}.{bed_index}" => name
     */
    public function updateUnitOccupantNames(Event $event, array $occupantNames): void
    {
        if (! Schema::hasTable('event_hotel_room_occupants') || ! Schema::hasTable('event_hotel_room_units')) {
            return;
        }

        $event->loadMissing('hotelStays.roomLines.units', 'hotelStays.roomLines.occupants');
        $allowedUnitIds = $event->hotelStays
            ->flatMap(fn (EventHotelStay $stay) => $stay->roomLines)
            ->flatMap(fn (EventHotelRoomLine $line) => $line->units)
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($occupantNames, $allowedUnitIds): void {
            foreach ($occupantNames as $slotKey => $name) {
                if (! is_string($slotKey) || ! str_contains($slotKey, '.')) {
                    continue;
                }

                [$unitIdRaw, $bedIndexRaw] = explode('.', $slotKey, 2);
                $unitId = (int) $unitIdRaw;
                $bedIndex = max(1, (int) $bedIndexRaw);

                if (! in_array($unitId, $allowedUnitIds, true)) {
                    continue;
                }

                $unit = EventHotelRoomUnit::query()->with('line.occupants')->find($unitId);
                $line = $unit?->line;
                if (! $unit || ! $line) {
                    continue;
                }

                $existing = $line->occupants->first(
                    fn ($occupant) => (int) $occupant->unit_index === (int) $unit->unit_index
                        && (int) $occupant->bed_index === $bedIndex
                );

                if ($existing && $existing->source !== 'manual' && filled($existing->name)) {
                    continue;
                }

                $name = trim((string) $name);
                if ($name === '') {
                    if ($existing && $existing->source === 'manual') {
                        $existing->delete();
                    }

                    continue;
                }

                if ($existing) {
                    $existing->update([
                        'name' => $name,
                        'source' => 'manual',
                    ]);

                    continue;
                }

                $line->occupants()->create([
                    'name' => $name,
                    'source' => 'manual',
                    'unit_index' => $unit->unit_index,
                    'bed_index' => $bedIndex,
                    'order' => $bedIndex,
                ]);
            }
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $stayPayloads
     */
    public function validateNoDuplicateOccupants(array $stayPayloads): void
    {
        foreach ($stayPayloads as $stayPayload) {
            $keys = [];
            foreach ($stayPayload['room_lines'] ?? [] as $linePayload) {
                foreach ($linePayload['occupants'] ?? [] as $occPayload) {
                    $key = null;
                    if (! empty($occPayload['event_agreement_id'])) {
                        $key = 'agreement:'.$occPayload['event_agreement_id'];
                    } elseif (! empty($occPayload['contract_id'])) {
                        $key = 'contract:'.$occPayload['contract_id'];
                    } elseif (! empty($occPayload['reservation_id'])) {
                        $key = 'reservation:'.$occPayload['reservation_id'];
                    }

                    if ($key && isset($keys[$key])) {
                        throw ValidationException::withMessages([
                            'occupants' => 'Uczestnik nie może być przypisany do dwóch pokoi w tej samej nocy.',
                        ]);
                    }

                    if ($key) {
                        $keys[$key] = true;
                    }
                }
            }
        }
    }

    public function totalPlnForEvent(Event $event): float
    {
        $totals = $this->totalsByCurrencyForEvent($event);

        return round((float) ($totals['PLN'] ?? 0), 2);
    }

    /**
     * Sumy noclegów per waluta (PLN + obce bez konwersji).
     *
     * @return array<string, float>
     */
    public function totalsByCurrencyForEvent(Event $event): array
    {
        $event->loadMissing(['hotelStays.roomLines.currency']);
        $peoplePerNight = (int) app(EventHotelOccupancyService::class)->forEvent($event)['required_beds_per_night'];
        $currencies = Currency::query()->pluck('symbol', 'id');

        $staysPayload = $event->hotelStays->map(function (EventHotelStay $stay): array {
            return [
                'pricing_mode' => $stay->pricing_mode ?? 'lines',
                'flat_amount' => $stay->flat_amount,
                'flat_currency_id' => $stay->flat_currency_id,
                'flat_convert_to_pln' => $stay->flat_convert_to_pln,
                'room_lines' => $stay->roomLines->map(static fn (EventHotelRoomLine $line): array => [
                    'hotel_room_id' => $line->hotel_room_id,
                    'label' => $line->label,
                    'quantity' => $line->quantity,
                    'people_count' => $line->people_count,
                    'unit_price' => $line->unit_price,
                    'price_basis' => $line->price_basis,
                    'currency_id' => $line->currency_id,
                    'convert_to_pln' => $line->convert_to_pln,
                ])->all(),
            ];
        })->all();

        return EventHotelPlanFormatting::eventTotalsByCurrency(
            $staysPayload,
            $event->hotel_pricing_mode ?? 'lines',
            $event->hotel_flat_stay_amount !== null ? (float) $event->hotel_flat_stay_amount : null,
            $event->hotel_flat_stay_currency_id,
            (bool) ($event->hotel_flat_stay_convert_to_pln ?? true),
            $currencies,
            $peoplePerNight,
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function buildHotelStructureForCalculation(Event $event): Collection
    {
        $event->loadMissing(['hotelStays.roomLines.hotelRoom', 'hotelStays.roomLines.currency', 'hotelStays.roomLines.occupants']);

        if ($event->hotelStays->isEmpty()) {
            return collect();
        }

        $peoplePerNight = (int) app(EventHotelOccupancyService::class)->forEvent($event)['required_beds_per_night'];

        return $event->hotelStays->map(function (EventHotelStay $stay) use ($event, $peoplePerNight) {
            $eventMode = $event->hotel_pricing_mode ?? 'lines';
            $dayForeignTotals = [];
            $rooms = [];

            foreach ($stay->roomLines as $line) {
                // Własne pokoje (bez powiązania do katalogu `hotel_rooms`) muszą też działać w kalkulacji.
                // Widok tabeli używa `->name` i `->people_count`, więc zapewniamy obiekt z tymi polami.
                // Kontrakt UI: cost = cena jednostkowa, room_count = liczba pokoi, line_total = gotowa suma.
                // Blade nie może mnożyć cost × room_count gdy cost to już suma linii.
                $room = $line->hotelRoom ?: (object) [
                    'name' => $line->displayLabel(),
                    'people_count' => $line->effectivePeopleCount(),
                ];
                $roomQty = max(1, (int) $line->quantity);
                $rooms[] = [
                    'room' => $room,
                    'label' => $line->displayLabel(),
                    'alloc' => [$line->role => $roomQty],
                    'total_people' => $roomQty * (int) ($room->people_count ?? 1),
                    'cost' => EventHotelPlanFormatting::isEventFlatPricing($eventMode)
                        || EventHotelPlanFormatting::isStayFlatPricing($stay->pricing_mode)
                        ? 0.0
                        : (float) $line->unit_price,
                    'line_total' => EventHotelPlanFormatting::isEventFlatPricing($eventMode)
                        || EventHotelPlanFormatting::isStayFlatPricing($stay->pricing_mode)
                        ? 0.0
                        : $line->lineTotal(),
                    'currency' => $line->currency?->symbol ?? 'PLN',
                    'group_type' => $line->role,
                    'room_count' => $roomQty,
                    'occupants' => $line->occupants->pluck('name')->all(),
                ];
            }

            $stayTotals = EventHotelPlanFormatting::isEventFlatPricing($eventMode)
                ? []
                : EventHotelPlanFormatting::eventTotalsByCurrency(
                    [[
                        'pricing_mode' => $stay->pricing_mode ?? 'lines',
                        'flat_amount' => $stay->flat_amount,
                        'flat_currency_id' => $stay->flat_currency_id,
                        'flat_convert_to_pln' => $stay->flat_convert_to_pln,
                        'room_lines' => $stay->roomLines->map(static fn (EventHotelRoomLine $line): array => [
                            'hotel_room_id' => $line->hotel_room_id,
                            'label' => $line->label,
                            'quantity' => $line->quantity,
                            'people_count' => $line->people_count,
                            'unit_price' => $line->unit_price,
                            'price_basis' => $line->price_basis,
                            'currency_id' => $line->currency_id,
                            'convert_to_pln' => $line->convert_to_pln,
                        ])->all(),
                    ]],
                    'lines',
                    null,
                    null,
                    true,
                    Currency::query()->pluck('symbol', 'id'),
                    $peoplePerNight,
                );

            // day_total zawiera PLN + wszystkie waluty obce (dla prawidłowego doliczania do sekcji walutowych)
            $dayTotal = $stayTotals !== []
                ? array_map(fn ($v) => round((float) $v, 2), $stayTotals)
                : ['PLN' => 0.0];

            return [
                'day' => $stay->day,
                'contractor' => $stay->contractor?->name,
                'offer_notes' => $stay->offer_notes,
                'rooms' => $rooms,
                'day_total' => $dayTotal,
            ];
        });
    }

    public function syncHotelFlagsOnProgramPoints(Event $event): void
    {
        $stays = $event->hotelStays()->with('programPoint')->get();
        foreach ($stays as $stay) {
            $programPoint = $stay->programPoint;
            if (! $stay->contractor_id || ! $programPoint) {
                continue;
            }

            if (Event::isHotelTransferProgramPoint($programPoint)) {
                continue;
            }

            $programPoint->update([
                'is_hotel' => true,
                'contractor_id' => $stay->contractor_id,
            ]);
        }
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function buildHotelPlanForPdf(Event $event): Collection
    {
        $event->loadMissing([
            'hotelStays.contractor',
            'hotelStays.contractorLocation',
            'hotelStays.programPoint.contractor',
            'hotelStays.programPoint.contractorLocation',
            'hotelStays.roomLines.hotelRoom',
            'hotelStays.roomLines.occupants',
            'hotelStays.roomLines.units',
            'hotelStays.roomLines.currency',
        ]);

        if ($event->hotelStays->isEmpty()) {
            return collect();
        }

        $peoplePerNight = (int) app(EventHotelOccupancyService::class)->forEvent($event)['required_beds_per_night'];

        return $event->hotelStays->map(function (EventHotelStay $stay) use ($event, $peoplePerNight) {
            $eventMode = $event->hotel_pricing_mode ?? 'lines';
            $linesByRole = [
                'qty' => collect(),
                'gratis' => collect(),
                'staff' => collect(),
                'driver' => collect(),
            ];

            foreach ($stay->roomLines as $line) {
                $role = in_array($line->role, ['qty', 'gratis', 'staff', 'driver'], true) ? $line->role : 'qty';
                $bedsPerRoom = $line->effectivePeopleCount();
                $occupantsByUnit = $line->occupants->groupBy('unit_index');
                $buildSlots = function (Collection $unitOccupants) use ($bedsPerRoom): array {
                    $slots = [];
                    for ($bed = 1; $bed <= $bedsPerRoom; $bed++) {
                        $occupant = $unitOccupants->firstWhere('bed_index', $bed);
                        $slots[] = [
                            'bed_index' => $bed,
                            'name' => $occupant?->name ?? '',
                            'source' => $occupant?->source ?? null,
                        ];
                    }

                    return $slots;
                };

                $units = $line->units->isNotEmpty()
                    ? $line->units->map(function (EventHotelRoomUnit $unit) use ($line, $occupantsByUnit, $buildSlots) {
                        $unitOccupants = $occupantsByUnit->get($unit->unit_index, collect());

                        return [
                            'id' => $unit->id,
                            'unit_index' => $unit->unit_index,
                            'room_number' => $unit->room_number,
                            'label' => $line->displayLabel().($line->quantity > 1 ? " ({$unit->unit_index}/{$line->quantity})" : ''),
                            'people_count' => $line->effectivePeopleCount(),
                            'occupants' => $unitOccupants->sortBy('bed_index')->pluck('name')->filter()->values()->all(),
                            'occupant_slots' => $buildSlots($unitOccupants),
                        ];
                    })->values()->all()
                    : [[
                        'id' => null,
                        'unit_index' => 1,
                        'room_number' => null,
                        'label' => $line->displayLabel(),
                        'people_count' => $line->effectivePeopleCount(),
                        'occupants' => $line->occupants->sortBy('bed_index')->pluck('name')->filter()->values()->all(),
                        'occupant_slots' => $buildSlots($line->occupants),
                    ]];

                $occupantNames = $line->occupants->pluck('name')->filter()->values()->all();

                $linesByRole[$role]->push([
                    'name' => $line->displayLabel(),
                    'quantity' => (int) $line->quantity,
                    'unit_price' => (float) $line->unit_price,
                    'price_basis' => $line->resolvedPriceBasis(),
                    'people_count' => $line->effectivePeopleCount(),
                    'occupants' => $occupantNames,
                    'units' => $units,
                    'line_total_pln' => $line->lineTotalPln(),
                ]);
            }

            $contractor = $stay->contractor ?? $stay->programPoint?->contractor;
            $location = $stay->contractorLocation ?? $stay->programPoint?->contractorLocation;
            $hotelMeta = ContractorContactDetails::operationalMeta($contractor, $location);

            return [
                'day' => $stay->day,
                'hotel_name' => $contractor?->name,
                'hotel_branch' => $hotelMeta['branch_name'],
                'hotel_address' => $hotelMeta['address'],
                'hotel_phone' => $hotelMeta['phone'],
                'hotel_email' => $hotelMeta['email'],
                'offer_notes' => $stay->offer_notes,
                'notes' => $stay->notes,
                'qty' => $linesByRole['qty']->values(),
                'gratis' => $linesByRole['gratis']->values(),
                'staff' => $linesByRole['staff']->values(),
                'driver' => $linesByRole['driver']->values(),
                'day_total_pln' => $stay->totalPln($eventMode, $peoplePerNight),
                'uses_event_plan' => true,
            ];
        });
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $detailedCalculations
     */
    public function applyEventHotelStructureToCalculations(array &$detailedCalculations, Event $event): void
    {
        $structure = $this->buildHotelStructureForCalculation($event);
        if ($structure->isEmpty()) {
            return;
        }

        // Zbierz sumy noclegów per waluta z pełnej struktury (PLN + waluty obce)
        $hotelByCurrency = [];
        foreach ($structure as $dayStruct) {
            foreach ($dayStruct['day_total'] as $code => $val) {
                if ($val > 0) {
                    $hotelByCurrency[$code] = ($hotelByCurrency[$code] ?? 0.0) + $val;
                }
            }
        }

        // Dla trybu flat_stay / flat_stay_per_person, waluta może być na poziomie eventu (nie w strukturze dni)
        if (EventHotelPlanFormatting::isEventFlatPricing($event->hotel_pricing_mode ?? 'lines') && empty($hotelByCurrency)) {
            $flatAmount = EventHotelPlanFormatting::resolveFlatNativeAmount(
                (float) ($event->hotel_flat_stay_amount ?? 0),
                $event->hotel_pricing_mode ?? 'lines',
                (int) app(EventHotelOccupancyService::class)->forEvent($event)['required_beds_per_night'],
            );
            if ($flatAmount > 0) {
                $flatCurrency = $event->hotel_flat_stay_currency_id
                    ? Currency::query()->find($event->hotel_flat_stay_currency_id)
                    : null;
                $flatCode = strtoupper($flatCurrency?->symbol ?? 'PLN');
                if ($flatCode === 'PLN' || (bool) ($event->hotel_flat_stay_convert_to_pln ?? true)) {
                    $hotelByCurrency['PLN'] = $this->totalPlnForEvent($event);
                } else {
                    $hotelByCurrency[$flatCode] = $flatAmount;
                }
            }
        }

        $eventHotelPln = $hotelByCurrency['PLN'] ?? 0.0;

        foreach ($detailedCalculations as $qty => &$currencies) {
            // --- PLN bucket ---
            $pln = $currencies['PLN'] ?? null;
            if (is_array($pln)) {
                $oldHotelPln = 0;
                $points = collect($pln['points'] ?? []);
                $filtered = $points->reject(function ($point) use (&$oldHotelPln) {
                    $name = (string) ($point['name'] ?? '');
                    if (str_starts_with($name, 'Hotel - dzień') || str_starts_with($name, 'Noclegi - dzień')) {
                        $oldHotelPln += (float) ($point['cost'] ?? 0);

                        return true;
                    }

                    return false;
                })->values()->all();

                if ($eventHotelPln > 0) {
                    foreach ($structure as $dayStruct) {
                        $dayPln = (float) ($dayStruct['day_total']['PLN'] ?? 0);
                        if ($dayPln <= 0) {
                            continue;
                        }
                        $filtered[] = [
                            'name' => 'Hotel - dzień '.($dayStruct['day'] ?? '?'),
                            'unit_price' => null,
                            'group_size' => null,
                            'cost' => $dayPln,
                            'is_child' => false,
                            'currency_symbol' => 'PLN',
                        ];
                    }
                }

                $pln['points'] = $filtered;
                $pln['total'] = round((float) ($pln['total'] ?? 0) - $oldHotelPln + $eventHotelPln, 2);
                $currencies['PLN'] = $pln;
            }

            // --- Buckety walut obcych ---
            foreach ($hotelByCurrency as $curCode => $curTotal) {
                if ($curCode === 'PLN' || $curTotal <= 0) {
                    continue;
                }

                // Upewnij się, że bucket istnieje
                if (! isset($currencies[$curCode]) || ! is_array($currencies[$curCode])) {
                    $currencies[$curCode] = ['total' => 0.0, 'points' => []];
                }

                // Usuń stare wpisy hotelowe z tego bucketu (aktualizacja)
                $oldForeignHotelCost = 0.0;
                $foreignPoints = collect($currencies[$curCode]['points'] ?? []);
                $filteredForeign = $foreignPoints->reject(function ($pt) use (&$oldForeignHotelCost) {
                    $name = (string) ($pt['name'] ?? '');
                    if (str_starts_with($name, 'Hotel - dzień') || str_starts_with($name, 'Noclegi - dzień')) {
                        $oldForeignHotelCost += (float) ($pt['cost'] ?? 0);

                        return true;
                    }

                    return false;
                })->values()->all();

                // Dodaj nowe wpisy hotelowe dla tej waluty
                foreach ($structure as $dayStruct) {
                    $dayCurAmount = (float) ($dayStruct['day_total'][$curCode] ?? 0);
                    if ($dayCurAmount <= 0) {
                        continue;
                    }
                    $filteredForeign[] = [
                        'name' => 'Hotel - dzień '.($dayStruct['day'] ?? '?'),
                        'unit_price' => null,
                        'group_size' => null,
                        'cost' => $dayCurAmount,
                        'is_child' => false,
                        'currency_symbol' => $curCode,
                    ];
                }

                $currencies[$curCode]['points'] = $filteredForeign;
                $currencies[$curCode]['total'] = round(
                    (float) ($currencies[$curCode]['total'] ?? 0.0) - $oldForeignHotelCost + $curTotal,
                    2
                );
            }

            $currencies['hotel_structure'] = $structure->values()->all();
        }
        unset($currencies);
    }

    public function linkStaysToProgramPoints(Event $event): void
    {
        $event->loadMissing('hotelStays');

        $hotelFlagged = $event->programPoints()
            ->where('is_hotel', true)
            ->orderBy('order')
            ->get();

        $transferPoints = $hotelFlagged->filter(
            fn (EventProgramPoint $point): bool => Event::isHotelTransferProgramPoint($point)
        );

        // Najpierw odłącz noce od „Przejazd do hotelu” — inaczej sync rezerwacji przywraca kontrahenta.
        foreach ($transferPoints as $point) {
            foreach ($event->hotelStays as $stay) {
                if ((int) $stay->event_program_point_id === (int) $point->id) {
                    $stay->update(['event_program_point_id' => null]);
                }
            }
        }

        // Self-heal: „Przejazd do hotelu” itd. nie są noclegiem — odznacz i zdejmij hotel z klocka.
        EventProgramPoint::runWithoutSideEffects(function () use ($transferPoints, $event): void {
            foreach ($transferPoints as $point) {
                $pointUpdate = ['is_hotel' => false];

                $contractorFromHotelPlan = filled($point->contractor_id)
                    && $event->hotelStays->contains(
                        fn (EventHotelStay $stay): bool => (int) $stay->contractor_id === (int) $point->contractor_id
                    );

                if ($contractorFromHotelPlan) {
                    $pointUpdate['contractor_id'] = null;
                    if (Schema::hasColumn('event_program_points', 'contractor_location_id')) {
                        $pointUpdate['contractor_location_id'] = null;
                    }
                }

                $point->update($pointUpdate);
            }
        });

        $pointsByDay = $hotelFlagged
            ->reject(fn (EventProgramPoint $point): bool => Event::isHotelTransferProgramPoint($point))
            ->filter(fn (EventProgramPoint $point): bool => (bool) $point->fresh()->is_hotel)
            ->groupBy('day');

        foreach ($event->hotelStays as $stay) {
            $dayPoints = $pointsByDay->get($stay->day);
            if (! $dayPoints || $dayPoints->isEmpty()) {
                continue;
            }

            $point = $dayPoints->first();
            $stay->update([
                'event_program_point_id' => $point->id,
                'contractor_id' => $stay->contractor_id ?: $point->contractor_id,
            ]);

            if (Schema::hasColumn('event_hotel_stays', 'contractor_location_id')
                && Schema::hasColumn('event_program_points', 'contractor_location_id')) {
                $stay->update([
                    'contractor_location_id' => $stay->contractor_location_id ?: $point->contractor_location_id,
                ]);
            }

            if ($stay->contractor_id) {
                $pointUpdate = ['is_hotel' => true, 'contractor_id' => $stay->contractor_id];

                if (Schema::hasColumn('event_program_points', 'contractor_location_id')
                    && Schema::hasColumn('event_hotel_stays', 'contractor_location_id')) {
                    $pointUpdate['contractor_location_id'] = $stay->contractor_location_id;
                }

                $point->update($pointUpdate);
            }
        }
    }
}
