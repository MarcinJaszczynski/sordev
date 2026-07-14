<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Event;
use App\Models\EventAgreement;
use App\Models\EventParticipant;
use App\Models\EventSettlementParticipantPayment;
use App\Support\EventAgreementParticipant;
use App\Support\EventHotelPlanFormatting;
use App\Support\ParticipantNameMatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class EventParticipantVerificationService
{
    /**
     * @return array{
     *     summary: array<string, int>,
     *     rows: array<int, array<string, mixed>>
     * }
     */
    public function verify(Event $event): array
    {
        $roster = $event->participants()->get();
        $agreements = $this->agreementEntries($event);
        $hotelNames = $this->hotelOccupantEntries($event);
        $payments = $this->paymentEntries($event);

        $rows = [];
        $matchedKeys = [];

        foreach ($roster as $participant) {
            $name = $participant->fullName();
            $agreementMatch = $this->findAgreementMatch($agreements, $participant);
            $hotelMatch = $this->findNameMatch($hotelNames, $name);
            $paymentMatch = $this->findPaymentMatch($payments, $participant);

            $sources = [
                'roster' => true,
                'agreement' => $agreementMatch !== null,
                'hotel' => $hotelMatch !== null,
                'payment' => $paymentMatch !== null,
            ];

            $status = $this->resolveStatus($sources, $agreementMatch, $participant);
            $key = 'roster:'.$participant->id;
            $matchedKeys[] = $key;

            if ($agreementMatch) {
                $matchedKeys[] = $agreementMatch['key'];
            }
            if ($hotelMatch) {
                $matchedKeys[] = $hotelMatch['key'];
            }
            if ($paymentMatch) {
                $matchedKeys[] = $paymentMatch['key'];
            }

            $rows[] = [
                'key' => $key,
                'kind' => 'roster',
                'participant_id' => $participant->id,
                'name' => $name,
                'birth_date' => $participant->birth_date?->format('d.m.Y'),
                'sources' => $sources,
                'status' => $status,
                'details' => $this->buildDetails($sources, $agreementMatch, $hotelMatch, $paymentMatch),
            ];
        }

        foreach ($agreements as $entry) {
            if (in_array($entry['key'], $matchedKeys, true)) {
                continue;
            }

            $rows[] = [
                'key' => $entry['key'],
                'kind' => 'orphan',
                'participant_id' => null,
                'name' => $entry['name'],
                'birth_date' => $entry['birth_date'],
                'sources' => [
                    'roster' => false,
                    'agreement' => true,
                    'hotel' => $this->findNameMatch($hotelNames, $entry['name']) !== null,
                    'payment' => $this->findNameMatch($payments, $entry['name']) !== null,
                ],
                'status' => 'orphan',
                'details' => 'Osoba w umowie, brak na liście uczestników.',
            ];
        }

        foreach ($hotelNames as $entry) {
            if (in_array($entry['key'], $matchedKeys, true)) {
                continue;
            }

            $rows[] = [
                'key' => $entry['key'],
                'kind' => 'orphan',
                'participant_id' => null,
                'name' => $entry['name'],
                'birth_date' => null,
                'sources' => [
                    'roster' => false,
                    'agreement' => $this->findNameMatch($agreements, $entry['name']) !== null,
                    'hotel' => true,
                    'payment' => $this->findNameMatch($payments, $entry['name']) !== null,
                ],
                'status' => 'orphan',
                'details' => 'Osoba w planie noclegów, brak na liście uczestników.',
            ];
        }

        foreach ($payments as $entry) {
            if (in_array($entry['key'], $matchedKeys, true)) {
                continue;
            }

            $rows[] = [
                'key' => $entry['key'],
                'kind' => 'orphan',
                'participant_id' => null,
                'name' => $entry['name'],
                'birth_date' => null,
                'sources' => [
                    'roster' => false,
                    'agreement' => $this->findNameMatch($agreements, $entry['name']) !== null,
                    'hotel' => $this->findNameMatch($hotelNames, $entry['name']) !== null,
                    'payment' => true,
                ],
                'status' => 'orphan',
                'details' => 'Wpłata uczestnika bez pozycji na liście.',
            ];
        }

        usort($rows, fn (array $a, array $b) => strnatcasecmp($a['name'], $b['name']));

        $fullyMatched = collect($rows)
            ->filter(fn (array $row) => $row['kind'] === 'roster' && $row['status'] === 'matched')
            ->count();

        return [
            'summary' => [
                'roster_count' => $roster->count(),
                'agreements_count' => $agreements->count(),
                'hotel_count' => $hotelNames->count(),
                'payments_count' => $payments->count(),
                'fully_matched' => $fullyMatched,
                'issues' => collect($rows)->where('status', '!=', 'matched')->count(),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @return Collection<int, array{key: string, name: string, birth_date: ?string, birth_carbon: ?\Carbon\CarbonInterface, agreement: Contract|EventAgreement}>
     */
    private function agreementEntries(Event $event): Collection
    {
        return $event->agreements()
            ->get()
            ->filter(function (Contract|EventAgreement $agreement): bool {
                return EventAgreementParticipant::isIndividual($agreement)
                    && ! in_array($agreement->status, ['template', 'cancelled'], true);
            })
            ->map(function (Contract|EventAgreement $agreement): array {
                $prefix = $agreement instanceof Contract ? 'contract' : 'agreement';

                return [
                    'key' => $prefix.':'.$agreement->id,
                    'name' => EventAgreementParticipant::participantName($agreement),
                    'birth_date' => $agreement->participant_birth_date?->format('d.m.Y'),
                    'birth_carbon' => $agreement->participant_birth_date,
                    'agreement' => $agreement,
                ];
            })
            ->filter(fn (array $entry) => $entry['name'] !== '')
            ->values();
    }

    /**
     * @return Collection<int, array{key: string, name: string}>
     */
    private function hotelOccupantEntries(Event $event): Collection
    {
        if (! Schema::hasTable('event_hotel_stays')) {
            return collect();
        }

        $event->loadMissing(['hotelStays.roomLines.hotelRoom', 'hotelStays.roomLines.occupants']);
        $entries = collect();
        $seen = [];

        foreach ($event->hotelStays as $stay) {
            $payload = [
                'day' => $stay->day,
                'room_lines' => $stay->roomLines->map(function ($line) {
                    return [
                        'hotel_room_id' => $line->hotel_room_id,
                        'label' => $line->label,
                        'quantity' => $line->quantity,
                        'people_count' => $line->people_count,
                        'occupants' => $line->occupants->map(fn ($o) => [
                            'name' => $o->name,
                            'unit_index' => $o->unit_index,
                            'bed_index' => $o->bed_index,
                        ])->all(),
                    ];
                })->all(),
            ];

            $roomsById = $stay->roomLines->pluck('hotelRoom')->filter()->keyBy('id');

            foreach (EventHotelPlanFormatting::expandedPersonSlots($payload, $roomsById) as $slot) {
                $name = trim((string) ($slot['occupant']['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $normalized = ParticipantNameMatcher::normalizeKey($name);
                if (isset($seen[$normalized])) {
                    continue;
                }

                $seen[$normalized] = true;
                $entries->push([
                    'key' => 'hotel:'.$normalized,
                    'name' => $name,
                ]);
            }
        }

        return $entries;
    }

    /**
     * @return Collection<int, array{key: string, name: string, payment: EventSettlementParticipantPayment}>
     */
    private function paymentEntries(Event $event): Collection
    {
        $settlementIds = $event->settlements()->pluck('id');

        if ($settlementIds->isEmpty()) {
            return collect();
        }

        return EventSettlementParticipantPayment::query()
            ->whereIn('settlement_id', $settlementIds)
            ->get()
            ->map(fn (EventSettlementParticipantPayment $payment) => [
                'key' => 'payment:'.$payment->id,
                'name' => trim((string) $payment->participant_name),
                'payment' => $payment,
            ])
            ->filter(fn (array $entry) => $entry['name'] !== '')
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $entries
     */
    private function findNameMatch(Collection $entries, string $name): ?array
    {
        return $entries->first(fn (array $entry) => ParticipantNameMatcher::namesMatch((string) $entry['name'], $name));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $agreements
     */
    private function findAgreementMatch(Collection $agreements, EventParticipant $participant): ?array
    {
        $match = $this->findNameMatch($agreements, $participant->fullName());
        if (! $match) {
            return null;
        }

        if ($participant->birth_date && $match['birth_carbon']) {
            $birthStatus = ParticipantNameMatcher::birthDatesMatch($participant->birth_date, $match['birth_carbon']);
            if ($birthStatus === 'mismatch') {
                $match['birth_mismatch'] = true;
            }
        }

        return $match;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $payments
     */
    private function findPaymentMatch(Collection $payments, EventParticipant $participant): ?array
    {
        $byName = $this->findNameMatch($payments, $participant->fullName());
        if ($byName) {
            return $byName;
        }

        if ($participant->participant_payment_id) {
            return $payments->first(fn (array $entry) => (int) $entry['payment']->id === (int) $participant->participant_payment_id);
        }

        return null;
    }

    /**
     * @param  array<string, bool>  $sources
     */
    private function resolveStatus(array $sources, ?array $agreementMatch, EventParticipant $participant): string
    {
        if (($agreementMatch['birth_mismatch'] ?? false) === true) {
            return 'birth_mismatch';
        }

        if ($sources['agreement'] && $sources['hotel'] && $sources['payment']) {
            return 'matched';
        }

        if ($sources['agreement'] || $sources['hotel'] || $sources['payment']) {
            return 'partial';
        }

        return 'missing';
    }

    /**
     * @param  array<string, bool>  $sources
     */
    private function buildDetails(
        array $sources,
        ?array $agreementMatch,
        ?array $hotelMatch,
        ?array $paymentMatch,
    ): string {
        if (($agreementMatch['birth_mismatch'] ?? false) === true) {
            return 'Rozbieżna data urodzenia między listą a umową.';
        }

        $missing = [];
        if (! $sources['agreement']) {
            $missing[] = 'umowa';
        }
        if (! $sources['hotel']) {
            $missing[] = 'nocleg';
        }
        if (! $sources['payment']) {
            $missing[] = 'wpłata';
        }

        if ($missing === []) {
            return 'Dane spójne we wszystkich źródłach.';
        }

        return 'Brak w: '.implode(', ', $missing).'.';
    }
}
