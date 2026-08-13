<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Event;
use App\Models\EventAgreement;
use App\Models\EventParticipant;
use App\Models\EventSettlement;
use App\Models\EventSettlementParticipantPayment;
use App\Support\EventAgreementParticipant;
use App\Support\EventHotelPlanFormatting;
use App\Support\EventParticipantGroupLabels;
use App\Support\ParticipantNameMatcher;
use Illuminate\Support\Facades\Schema;

class EventParticipantPropagationService
{
    public function __construct(
        private readonly EventHotelPlanService $hotelPlanService,
    ) {}

    /**
     * @return array{created: int, linked: int, skipped: int}
     */
    public function propagateToPayments(Event $event): array
    {
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $participants = $event->activeParticipants()->get();

        $created = 0;
        $linked = 0;
        $skipped = 0;

        foreach ($participants as $participant) {
            if ($participant->participant_payment_id) {
                $skipped++;

                continue;
            }

            $existing = EventSettlementParticipantPayment::query()
                ->where('settlement_id', $settlement->id)
                ->get()
                ->first(fn (EventSettlementParticipantPayment $payment) => ParticipantNameMatcher::namesMatch(
                    (string) $payment->participant_name,
                    $participant->fullName(),
                ));

            if ($existing) {
                $participant->forceFill(['participant_payment_id' => $existing->id])->saveQuietly();
                $linked++;

                continue;
            }

            $due = 0.0;
            $paid = 0.0;
            $booking = $participant->booking_reference;

            if ($participant->contract_id) {
                $contract = Contract::find($participant->contract_id);
                if ($contract) {
                    $due = (float) $contract->amount_due;
                    $paid = (float) $contract->amount_paid;
                    $booking = $booking ?: $contract->contract_number;
                }
            } elseif ($participant->event_agreement_id) {
                $agreement = EventAgreement::find($participant->event_agreement_id);
                if ($agreement) {
                    $due = (float) $agreement->amount_due;
                    $paid = (float) $agreement->amount_paid;
                    $booking = $booking ?: $agreement->agreement_number;
                }
            }

            $payment = EventSettlementParticipantPayment::create([
                'settlement_id' => $settlement->id,
                'participant_name' => $participant->fullName(),
                'booking_reference' => $booking,
                'due_amount_pln' => $due,
                'paid_amount_pln' => $paid,
                'payment_status' => $paid >= $due && $due > 0 ? 'paid' : 'pending',
                'attended' => true,
                'notes' => 'Utworzono z listy uczestników #'.$participant->id,
            ]);

            $participant->forceFill(['participant_payment_id' => $payment->id])->saveQuietly();
            $created++;
        }

        $settlement->recalculateTotals();

        $this->syncGroupIndividualContracts($event);

        return compact('created', 'linked', 'skipped');
    }

    protected function syncGroupIndividualContracts(Event $event): void
    {
        if (! Schema::hasTable('contracts')) {
            return;
        }

        $event->loadMissing('agreements');

        foreach ($event->agreements as $contract) {
            if (! $contract instanceof Contract || ! $contract->usesIndividualParticipantPayments()) {
                continue;
            }

            app(ContractPaymentSyncService::class)->sync($contract->fresh());
        }
    }

    /**
     * Przypisuje pilot/kierowcę/obsługę/opiekunów do slotów operacyjnych — bez listy uczestników.
     *
     * @return array{assigned: int, warnings: array<int, string>}
     */
    public function assignOperationalOccupants(Event $event): array
    {
        if (! Schema::hasTable('event_hotel_stays')) {
            return ['assigned' => 0, 'warnings' => []];
        }

        $this->hotelPlanService->ensureStaysForEvent($event);
        $this->hotelPlanService->syncAllRoomUnitsForEvent($event);
        $event->load(['hotelStays.roomLines.hotelRoom', 'hotelStays.roomLines.occupants', 'assignedUser', 'driverContractor']);

        $stayPayloads = $this->hotelPlanService->staysToPayload($event);
        if ($stayPayloads === []) {
            return ['assigned' => 0, 'warnings' => ['Brak noclegów w planie imprezy.']];
        }

        $operationalPeople = $this->resolveOperationalOccupants($event);
        $assigned = 0;
        $warnings = [];

        foreach ($stayPayloads as &$stay) {
            $assignedInStay = $this->collectAssignedHotelNamesForStay($stay);

            foreach ($operationalPeople as $person) {
                $name = $person['name'];
                $normalized = ParticipantNameMatcher::normalizeKey($name);

                if (isset($assignedInStay[$normalized])) {
                    continue;
                }

                $slot = $this->findFirstEmptySlotForStay($stay, $person['role']);
                if ($slot === null) {
                    $day = $stay['day'] ?? '?';
                    $roleLabel = EventParticipantGroupLabels::hotelRoleLabels()[$person['role']] ?? $person['role'];
                    $warnings[] = "Noc {$day}: brak wolnego miejsca ({$roleLabel}) dla „{$name}”.";

                    continue;
                }

                $stay['room_lines'][$slot['line_index']]['occupants'][] = $this->buildOccupantPayload(
                    $name,
                    $slot,
                    $person['source'] ?? 'manual',
                );

                $assignedInStay[$normalized] = true;
                $assigned++;
            }
        }
        unset($stay);

        if ($assigned > 0) {
            $this->hotelPlanService->savePlan($event, $stayPayloads);
        }

        return compact('assigned', 'warnings');
    }

    /**
     * @return array{assigned: int, skipped: int, warnings: array<int, string>}
     */
    public function propagateToHotelPlan(Event $event): array
    {
        if (! Schema::hasTable('event_hotel_stays')) {
            return ['assigned' => 0, 'skipped' => 0, 'warnings' => ['Brak modułu planu noclegów.']];
        }

        $this->hotelPlanService->ensureStaysForEvent($event);
        $this->assignOperationalOccupants($event);

        $this->hotelPlanService->syncAllRoomUnitsForEvent($event);
        $event->load(['hotelStays.roomLines.hotelRoom', 'hotelStays.roomLines.occupants']);

        $stayPayloads = $this->hotelPlanService->staysToPayload($event);
        if ($stayPayloads === []) {
            return ['assigned' => 0, 'skipped' => 0, 'warnings' => ['Brak noclegów w planie imprezy.']];
        }

        $participants = $event->activeParticipants()->get();
        $assigned = 0;
        $skipped = 0;
        $warnings = [];

        foreach ($stayPayloads as &$stay) {
            $assignedInStay = $this->collectAssignedHotelNamesForStay($stay);

            foreach ($participants as $participant) {
                $name = $participant->fullName();
                $normalized = ParticipantNameMatcher::normalizeKey($name);

                if (isset($assignedInStay[$normalized])) {
                    $skipped++;

                    continue;
                }

                $slot = $this->findFirstEmptySlotForStay($stay, 'qty');
                if ($slot === null) {
                    $day = $stay['day'] ?? '?';
                    $warnings[] = "Noc {$day}: brak wolnych miejsc uczestników dla „{$name}”.";
                    $skipped++;

                    continue;
                }

                $stay['room_lines'][$slot['line_index']]['occupants'][] = $this->buildOccupantPayload(
                    $name,
                    $slot,
                    $participant->contract_id || $participant->event_agreement_id ? 'agreement' : 'manual',
                    $participant->event_agreement_id,
                    $participant->contract_id,
                );

                $assignedInStay[$normalized] = true;
                $assigned++;
            }
        }
        unset($stay);

        if ($assigned > 0) {
            $this->hotelPlanService->savePlan($event, $stayPayloads);
        }

        return compact('assigned', 'skipped', 'warnings');
    }

    /**
     * @return array{imported: int, skipped: int}
     */
    public function syncFromAgreements(Event $event): array
    {
        $existing = $event->participants()->get();
        $imported = 0;
        $skipped = 0;

        foreach ($event->agreements()->get() as $agreement) {
            if (! EventAgreementParticipant::isIndividual($agreement)) {
                continue;
            }

            if (in_array($agreement->status, ['template', 'cancelled'], true)) {
                continue;
            }

            $name = EventAgreementParticipant::participantName($agreement);
            if ($name === '') {
                continue;
            }

            $parts = ParticipantNameMatcher::parseFullName($name);
            $birthDate = $agreement->participant_birth_date;

            $duplicate = $existing->contains(function (EventParticipant $participant) use ($name, $birthDate) {
                if (! ParticipantNameMatcher::namesMatch($participant->fullName(), $name)) {
                    return false;
                }

                if ($birthDate && $participant->birth_date) {
                    return $participant->birth_date->toDateString() === $birthDate->toDateString();
                }

                return true;
            });

            if ($duplicate) {
                $skipped++;

                continue;
            }

            $participant = $event->participants()->create([
                'first_name' => $parts['first_name'],
                'last_name' => $parts['last_name'],
                'birth_date' => $birthDate,
                'email' => $agreement->participant_email ?? $agreement->customer_email ?? null,
                'phone' => $agreement->participant_phone ?? $agreement->customer_phone ?? null,
                'booking_reference' => EventAgreementParticipant::referenceNumber($agreement),
                'source' => EventParticipant::SOURCE_AGREEMENT,
                'status' => EventParticipant::STATUS_ACTIVE,
                'contract_id' => $agreement instanceof Contract ? $agreement->id : null,
                'event_agreement_id' => $agreement instanceof EventAgreement ? $agreement->id : null,
                'participant_payment_id' => $agreement->participant_payment_id,
            ]);

            $existing->push($participant);
            $imported++;
        }

        return compact('imported', 'skipped');
    }

    /**
     * Osoby operacyjne (pilot, kierowca, obsługa, opiekunowie) — przypisywane do linii wg roli pokoju.
     *
     * @return list<array{name: string, role: string, source?: string}>
     */
    private function resolveOperationalOccupants(Event $event): array
    {
        $groupCounts = $this->hotelPlanService->resolveGroupCounts($event);
        $people = [];

        $pilotName = trim((string) ($event->assignedUser?->name ?? ''));
        $staffSlotsUsed = 0;

        if ($pilotName !== '') {
            $people[] = ['name' => $pilotName, 'role' => 'staff', 'source' => 'manual'];
            $staffSlotsUsed++;
        }

        $driverName = trim((string) ($event->driver_name ?? ''));
        if ($driverName === '' && $event->relationLoaded('driverContractor') === false) {
            $event->loadMissing('driverContractor');
        }
        if ($driverName === '' && $event->driverContractor) {
            $driverName = trim((string) ($event->driverContractor->name ?? ''));
        }
        if ($driverName !== '') {
            $people[] = ['name' => $driverName, 'role' => 'driver', 'source' => 'manual'];
        }

        $staffCount = max(0, (int) ($groupCounts['staff'] ?? 0));
        for ($i = $staffSlotsUsed + 1; $i <= $staffCount; $i++) {
            $people[] = [
                'name' => $staffCount === 1 ? 'Obsługa' : "Obsługa {$i}",
                'role' => 'staff',
                'source' => 'manual',
            ];
        }

        $gratisCount = max(0, (int) ($groupCounts['gratis'] ?? 0));
        for ($i = 1; $i <= $gratisCount; $i++) {
            $label = EventParticipantGroupLabels::GRATIS;
            $people[] = [
                'name' => $gratisCount === 1 ? $label : "{$label} {$i}",
                'role' => 'gratis',
                'source' => 'manual',
            ];
        }

        return $people;
    }

    /**
     * @param  array<string, mixed>  $slot
     * @return array<string, mixed>
     */
    private function buildOccupantPayload(
        string $name,
        array $slot,
        string $source,
        ?int $eventAgreementId = null,
        ?int $contractId = null,
    ): array {
        return [
            'id' => null,
            'name' => $name,
            'source' => $source,
            'unit_index' => $slot['unit_index'],
            'bed_index' => $slot['bed_index'],
            'event_agreement_id' => $eventAgreementId,
            'contract_id' => $contractId,
            'reservation_id' => null,
            'participant_key' => $contractId
                ? 'contract:'.$contractId
                : ($eventAgreementId ? 'agreement:'.$eventAgreementId : null),
        ];
    }

    /**
     * @param  array<string, mixed>  $stay
     * @return array<string, true>
     */
    private function collectAssignedHotelNamesForStay(array $stay): array
    {
        $names = [];

        foreach ($stay['room_lines'] ?? [] as $line) {
            foreach ($line['occupants'] ?? [] as $occupant) {
                $name = trim((string) ($occupant['name'] ?? ''));
                if ($name !== '') {
                    $names[ParticipantNameMatcher::normalizeKey($name)] = true;
                }
            }
        }

        return $names;
    }

    /**
     * @param  array<string, mixed>  $stay
     * @return array{line_index: int, unit_index: int, bed_index: int}|null
     */
    private function findFirstEmptySlotForStay(array $stay, ?string $role = null): ?array
    {
        $hotelRoomsById = collect($stay['room_lines'] ?? [])
            ->pluck('hotel_room_id')
            ->filter()
            ->mapWithKeys(fn ($id) => [$id => \App\Models\HotelRoom::find($id)])
            ->filter();

        foreach ($stay['room_lines'] ?? [] as $lineIndex => $line) {
            if ($role !== null && ($line['role'] ?? 'qty') !== $role) {
                continue;
            }

            foreach (EventHotelPlanFormatting::expandedPersonSlots(
                ['room_lines' => [$line]],
                $hotelRoomsById,
            ) as $slot) {
                $occupantName = trim((string) ($slot['occupant']['name'] ?? ''));
                if ($occupantName === '') {
                    return [
                        'line_index' => (int) $lineIndex,
                        'unit_index' => (int) $slot['unit_index'],
                        'bed_index' => (int) $slot['bed_index'],
                    ];
                }
            }
        }

        return null;
    }
}
