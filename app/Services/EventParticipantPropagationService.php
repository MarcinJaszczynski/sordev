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
     * @return array{assigned: int, skipped: int, warnings: array<int, string>}
     */
    public function propagateToHotelPlan(Event $event): array
    {
        if (! Schema::hasTable('event_hotel_stays')) {
            return ['assigned' => 0, 'skipped' => 0, 'warnings' => ['Brak modułu planu noclegów.']];
        }

        $this->hotelPlanService->ensureStaysForEvent($event);
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

        $assignedNames = $this->collectAssignedHotelNames($stayPayloads);

        foreach ($participants as $participant) {
            $name = $participant->fullName();
            $normalized = ParticipantNameMatcher::normalizeKey($name);

            if (isset($assignedNames[$normalized])) {
                $skipped++;

                continue;
            }

            $slot = $this->findFirstEmptySlot($stayPayloads);
            if ($slot === null) {
                $warnings[] = "Brak wolnych miejsc dla „{$name}”.";
                $skipped++;

                continue;
            }

            $occupantPayload = [
                'id' => null,
                'name' => $name,
                'source' => $participant->contract_id || $participant->event_agreement_id ? 'agreement' : 'manual',
                'unit_index' => $slot['unit_index'],
                'bed_index' => $slot['bed_index'],
                'event_agreement_id' => $participant->event_agreement_id,
                'contract_id' => $participant->contract_id,
                'reservation_id' => null,
                'participant_key' => $participant->contract_id
                    ? 'contract:'.$participant->contract_id
                    : ($participant->event_agreement_id ? 'agreement:'.$participant->event_agreement_id : null),
            ];

            $stayPayloads[$slot['stay_index']]['room_lines'][$slot['line_index']]['occupants'][] = $occupantPayload;
            $assignedNames[$normalized] = true;
            $assigned++;
        }

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
     * @param  array<int, array<string, mixed>>  $stayPayloads
     * @return array<string, true>
     */
    private function collectAssignedHotelNames(array $stayPayloads): array
    {
        $names = [];

        foreach ($stayPayloads as $stay) {
            foreach ($stay['room_lines'] ?? [] as $line) {
                foreach ($line['occupants'] ?? [] as $occupant) {
                    $name = trim((string) ($occupant['name'] ?? ''));
                    if ($name !== '') {
                        $names[ParticipantNameMatcher::normalizeKey($name)] = true;
                    }
                }
            }
        }

        return $names;
    }

    /**
     * @param  array<int, array<string, mixed>>  $stayPayloads
     * @return array{stay_index: int, line_index: int, unit_index: int, bed_index: int}|null
     */
    private function findFirstEmptySlot(array $stayPayloads): ?array
    {
        foreach ($stayPayloads as $stayIndex => $stay) {
            $hotelRoomsById = collect($stay['room_lines'] ?? [])
                ->pluck('hotel_room_id')
                ->filter()
                ->mapWithKeys(fn ($id) => [$id => \App\Models\HotelRoom::find($id)])
                ->filter();

            foreach (EventHotelPlanFormatting::expandedPersonSlots($stay, $hotelRoomsById) as $slot) {
                $occupantName = trim((string) ($slot['occupant']['name'] ?? ''));
                if ($occupantName === '') {
                    return [
                        'stay_index' => (int) $stayIndex,
                        'line_index' => (int) $slot['line_index'],
                        'unit_index' => (int) $slot['unit_index'],
                        'bed_index' => (int) $slot['bed_index'],
                    ];
                }
            }
        }

        return null;
    }
}
