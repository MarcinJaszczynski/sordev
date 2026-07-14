<?php

namespace App\Services\BankPayments;

use App\Models\Contract;
use App\Models\Event;
use App\Models\EventSettlementParticipantPayment;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class BankPaymentMatcher
{
    /**
     * @param  array{
     *     operation_date: ?string,
     *     title: string,
     *     counterparty: ?string,
     *     account_number: ?string,
     *     amount_pln: float
     * }  $transaction
     * @return array{
     *     match_status: string,
     *     match_reason: ?string,
     *     contract_id: ?int,
     *     participant_payment_id: ?int,
     *     event_id: ?int
     * }
     */
    public function match(array $transaction): array
    {
        $haystack = $this->buildHaystack($transaction);
        $references = $this->extractReferences($haystack);

        foreach ($references as $reference) {
            $contract = $this->findContractByReference($reference);

            if ($contract) {
                return [
                    'match_status' => 'matched_contract',
                    'match_reason' => 'Numer umowy / rezerwacji: '.$reference,
                    'contract_id' => $contract->id,
                    'participant_payment_id' => $contract->participant_payment_id,
                    'event_id' => $contract->event_id,
                ];
            }

            $payment = $this->findParticipantPaymentByReference($reference);

            if ($payment) {
                return [
                    'match_status' => 'matched_payment',
                    'match_reason' => 'Nr rezerwacji / dokumentu: '.$reference,
                    'contract_id' => $payment->contracts()->value('id'),
                    'participant_payment_id' => $payment->id,
                    'event_id' => $payment->settlement?->event_id,
                ];
            }
        }

        if ($this->containsPilotAdvanceKeyword($haystack)) {
            $event = $this->findEventByCode($haystack);

            if ($event) {
                return [
                    'match_status' => 'matched_pilot_advance',
                    'match_reason' => 'Zaliczka pilota — impreza '.$event->code,
                    'contract_id' => null,
                    'participant_payment_id' => null,
                    'event_id' => $event->id,
                ];
            }
        }

        $event = $this->findEventByCode($haystack);

        if ($event) {
            $payment = $this->findParticipantPaymentByName($event->id, $haystack, (float) $transaction['amount_pln']);

            if ($payment) {
                return [
                    'match_status' => 'matched_payment',
                    'match_reason' => 'Impreza '.$event->code.' + nazwisko',
                    'contract_id' => $payment->contracts()->value('id'),
                    'participant_payment_id' => $payment->id,
                    'event_id' => $event->id,
                ];
            }
        }

        $payment = $this->findParticipantPaymentByName(null, $haystack, (float) $transaction['amount_pln']);

        if ($payment) {
            return [
                'match_status' => 'matched_payment',
                'match_reason' => 'Dopasowanie po nazwisku i kwocie',
                'contract_id' => $payment->contracts()->value('id'),
                'participant_payment_id' => $payment->id,
                'event_id' => $payment->settlement?->event_id,
            ];
        }

        return [
            'match_status' => 'unmatched',
            'match_reason' => null,
            'contract_id' => null,
            'participant_payment_id' => null,
            'event_id' => null,
        ];
    }

    /**
     * @param  array{
     *     operation_date: ?string,
     *     title: string,
     *     counterparty: ?string,
     *     account_number: ?string,
     *     amount_pln: float
     * }  $transaction
     */
    private function buildHaystack(array $transaction): string
    {
        return mb_strtolower(implode(' ', array_filter([
            $transaction['title'] ?? '',
            $transaction['counterparty'] ?? '',
        ])));
    }

    /**
     * @return array<int, string>
     */
    private function extractReferences(string $haystack): array
    {
        $references = [];

        if (preg_match_all('/\b(?:umowa|um\.?|rez(?:erwacja)?|nr)\s*[:#-]?\s*([a-z0-9][a-z0-9\-\/]{2,})\b/ui', $haystack, $matches)) {
            foreach ($matches[1] as $match) {
                $references[] = strtoupper(trim($match));
            }
        }

        if (preg_match_all('/\b([a-z]{1,4}-\d{2,})\b/i', $haystack, $matches)) {
            foreach ($matches[1] as $match) {
                $references[] = strtoupper(trim($match));
            }
        }

        if (preg_match_all('/\b(\d{4,})\b/', $haystack, $matches)) {
            foreach ($matches[1] as $match) {
                $references[] = trim($match);
            }
        }

        return array_values(array_unique(array_filter($references)));
    }

    private function containsPilotAdvanceKeyword(string $haystack): bool
    {
        $normalized = Str::ascii($haystack);

        return str_contains($normalized, 'zaliczka')
            || str_contains($normalized, 'zaliczki')
            || str_contains($normalized, 'zaliczke');
    }

    private function findContractByReference(string $reference): ?Contract
    {
        return Contract::query()
            ->where(function ($query) use ($reference) {
                $query->where('operational_number', $reference)
                    ->orWhere('contract_number', $reference)
                    ->orWhere('reservation_number', $reference)
                    ->orWhere('operational_number', 'like', '%'.$reference.'%')
                    ->orWhere('contract_number', 'like', '%'.$reference.'%')
                    ->orWhere('reservation_number', 'like', '%'.$reference.'%');
            })
            ->whereNotIn('status', ['cancelled', 'template'])
            ->latest('id')
            ->first();
    }

    private function findParticipantPaymentByReference(string $reference): ?EventSettlementParticipantPayment
    {
        return EventSettlementParticipantPayment::query()
            ->where(function ($query) use ($reference) {
                $query->where('booking_reference', $reference)
                    ->orWhere('document_number', $reference)
                    ->orWhere('booking_reference', 'like', '%'.$reference.'%')
                    ->orWhere('document_number', 'like', '%'.$reference.'%');
            })
            ->latest('id')
            ->first();
    }

    private function findEventByCode(string $haystack): ?Event
    {
        $codes = Event::query()
            ->whereNotNull('code')
            ->pluck('code')
            ->filter()
            ->sortByDesc(fn ($code) => strlen((string) $code))
            ->values();

        foreach ($codes as $code) {
            if ($code !== '' && str_contains($haystack, mb_strtolower((string) $code))) {
                return Event::query()->where('code', $code)->first();
            }
        }

        return null;
    }

    private function findParticipantPaymentByName(?int $eventId, string $haystack, float $amount): ?EventSettlementParticipantPayment
    {
        $query = EventSettlementParticipantPayment::query()->with('settlement');

        if ($eventId) {
            $query->whereHas('settlement', fn ($q) => $q->where('event_id', $eventId));
        }

        $candidates = $query
            ->whereIn('payment_status', ['pending', 'partial', 'paid', 'overpaid'])
            ->get();

        $best = null;
        $bestScore = 0;

        foreach ($candidates as $candidate) {
            $score = $this->scoreCandidate($candidate, $haystack, $amount);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        return $bestScore >= 3 ? $best : null;
    }

    private function scoreCandidate(EventSettlementParticipantPayment $payment, string $haystack, float $amount): int
    {
        $score = 0;
        $name = mb_strtolower((string) $payment->participant_name);

        foreach ($this->nameTokens($name) as $token) {
            if (strlen($token) >= 3 && str_contains($haystack, $token)) {
                $score += 2;
            }
        }

        $due = (float) $payment->due_amount_pln;
        $remaining = max(0, $due - (float) $payment->paid_amount_pln);

        if ($remaining > 0 && abs($remaining - $amount) < 0.02) {
            $score += 3;
        } elseif ($due > 0 && abs($due - $amount) < 0.02) {
            $score += 2;
        }

        return $score;
    }

    /**
     * @return Collection<int, string>
     */
    private function nameTokens(string $name): Collection
    {
        return collect(preg_split('/\s+/', $name) ?: [])
            ->map(fn ($part) => Str::ascii(mb_strtolower(trim((string) $part))))
            ->filter(fn ($part) => strlen((string) $part) >= 3)
            ->values();
    }
}
