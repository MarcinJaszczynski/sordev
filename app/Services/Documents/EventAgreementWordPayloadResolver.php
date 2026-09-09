<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\Contract;
use App\Models\Event;
use App\Services\ContractGroupPricingService;
use App\Services\EventOrderingPartyService;
use App\Services\EventPaymentInstallmentTemplateService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Mapuje Event (+ opcjonalnie Contract) na pola dynamiczne umowy Word.
 *
 * Preferencja: istniejąca umowa grupowa → dane finansowe i numer;
 * reszta z imprezy (miejsca, hotel, zamawiający, ubezpieczenie).
 */
final class EventAgreementWordPayloadResolver
{
    public function __construct(
        private readonly WordAgreementContent $content,
        private readonly WordOfferContent $offerContent,
        private readonly EventOrderingPartyService $orderingParties,
        private readonly ContractGroupPricingService $pricing,
        private readonly EventPaymentInstallmentTemplateService $installments,
    ) {}

    /**
     * @return array{
     *   contract_number: string,
     *   agreement_date: string,
     *   ordering_party_lines: list<string>,
     *   organizer_lines: list<string>,
     *   event_type: string,
     *   event_name: string,
     *   trip_dates: string,
     *   transport: string,
     *   participant_total: string,
     *   guardians_count: string,
     *   departure_line: string,
     *   return_line: string,
     *   hotel: string,
     *   meals: string,
     *   insurance: string,
     *   additional_info: string,
     *   pickup_place: string,
     *   pickup_time: string,
     *   unit_price_label: string,
     *   paid_count: int,
     *   total_price_label: string,
     *   gross_price_line: string,
     *   amount_in_words: string,
     *   payment_method: string,
     *   price_includes: string,
     *   payment_schedule_lines: list<string>,
     *   bank_account_line: string,
     *   transfer_description: string,
     * }
     */
    public function resolve(Event $event): array
    {
        $contract = $this->preferredContract($event);

        $paidCount = max(1, (int) (
            $contract?->participant_count
            ?? $event->participant_count
            ?? 1
        ));
        $guardians = $event->resolveGratisCountForParticipantCount($paidCount);
        $totalParticipants = $paidCount + $guardians;

        $unitPrice = $contract
            ? $this->pricing->resolvedUnitPrice($contract, $event)
            : (float) $event->resolvedPricePerPerson($paidCount);
        $totalPrice = round($unitPrice * $paidCount, 2);

        $unitLabel = $unitPrice > 0
            ? $this->content->formatMoneyPln($unitPrice)
            : '—';
        $totalLabel = $totalPrice > 0
            ? $this->content->formatMoneyPln($totalPrice)
            : '—';

        $grossLine = ($unitPrice > 0 && $paidCount > 0)
            ? sprintf(
                '%s x %d osób = %s brutto',
                $unitLabel,
                $paidCount,
                $totalLabel
            )
            : '—';

        $contractNumber = $this->resolveContractNumber($event, $contract);
        $agreementDate = $contract?->contract_date
            ? Carbon::parse($contract->contract_date)->format('Y-m-d')
            : now()->format('Y-m-d');

        return [
            'contract_number' => $contractNumber,
            'agreement_date' => $agreementDate,
            'ordering_party_lines' => $this->orderingPartyLines($event),
            'organizer_lines' => $this->content->organizerBlockLines(),
            'event_type' => $this->eventTypeLabel($event),
            'event_name' => trim((string) ($event->name ?? '')) ?: '—',
            'trip_dates' => $this->tripDatesLabel($event),
            'transport' => WordAgreementContent::DEFAULT_TRANSPORT,
            'participant_total' => (string) $totalParticipants,
            'guardians_count' => (string) $guardians,
            'departure_line' => $this->departureLine($event),
            'return_line' => $this->returnLine($event),
            'hotel' => $this->hotelLabel($event),
            'meals' => WordAgreementContent::DEFAULT_MEALS,
            'insurance' => $this->insuranceLabel($event),
            'additional_info' => WordAgreementContent::DEFAULT_ADDITIONAL_INFO,
            'pickup_place' => $this->pickupPlace($event),
            'pickup_time' => $this->pickupTimeLabel($event),
            'unit_price_label' => $unitLabel,
            'paid_count' => $paidCount,
            'total_price_label' => $totalLabel,
            'gross_price_line' => $grossLine,
            'amount_in_words' => $totalPrice > 0
                ? $this->content->amountInWordsPln($totalPrice)
                : '—',
            'payment_method' => 'przelew',
            'price_includes' => $this->priceIncludes($event),
            'payment_schedule_lines' => $this->paymentScheduleLines($event, $contract, $totalPrice),
            'bank_account_line' => $this->content->bankAccountLine(),
            'transfer_description' => sprintf(
                'W opisie przelewu należy podać: %s, rezerwacja nr %s',
                trim((string) ($event->name ?? '')) ?: 'impreza',
                $contractNumber
            ),
        ];
    }

    protected function preferredContract(Event $event): ?Contract
    {
        if (! Schema::hasTable('contracts')) {
            return null;
        }

        $contracts = Contract::query()
            ->where('event_id', $event->id)
            ->whereNotIn('status', ['cancelled', 'template'])
            ->orderByDesc('contract_date')
            ->orderByDesc('id')
            ->get();

        if ($contracts->isEmpty()) {
            return null;
        }

        return $contracts->first(fn (Contract $c): bool => $c->isGroup())
            ?? $contracts->first();
    }

    protected function resolveContractNumber(Event $event, ?Contract $contract): string
    {
        if ($contract) {
            $number = trim((string) ($contract->operational_number ?: $contract->contract_number ?: ''));
            if ($number !== '') {
                return $number;
            }
        }

        $code = trim((string) ($event->code ?? ''));
        if ($code !== '') {
            return $code;
        }

        return 'IMP/'.$event->id.'/'.now()->format('Y');
    }

    /**
     * @return list<string>
     */
    protected function orderingPartyLines(Event $event): array
    {
        $parties = $this->orderingParties->partiesForWordDocument($event);
        if ($parties === []) {
            return ['—'];
        }

        $lines = [];
        foreach ($parties as $party) {
            foreach (['person', 'institution', 'department'] as $key) {
                $value = trim((string) ($party[$key] ?? ''));
                if ($value !== '') {
                    $lines[] = $value;
                }
            }
        }

        return $lines !== [] ? array_values(array_unique($lines)) : ['—'];
    }

    protected function eventTypeLabel(Event $event): string
    {
        $event->loadMissing('eventTemplate.eventTypes');
        $types = $event->eventTemplate?->eventTypes;
        if ($types && $types->isNotEmpty()) {
            $names = $types->pluck('name')->filter()->implode(', ');
            if ($names !== '') {
                return $names;
            }
        }

        return 'Wycieczka';
    }

    protected function tripDatesLabel(Event $event): string
    {
        $start = $event->start_date?->format('Y.m.d');
        $end = $event->end_date?->format('Y.m.d') ?? $start;

        if (! $start) {
            return '—';
        }

        return $start === $end ? $start : $start.' - '.$end;
    }

    protected function placeLabel(Event $event): string
    {
        $details = trim((string) ($event->pickup_place_details ?? ''));
        if ($details !== '') {
            return $details;
        }

        $event->loadMissing('startPlace');
        $name = trim((string) ($event->startPlace?->name ?? ''));

        return $name !== '' ? $name : '—';
    }

    protected function formatTime(?string $time): string
    {
        $time = trim((string) $time);
        if ($time === '') {
            return '';
        }

        if (preg_match('/^\d{1,2}:\d{2}/', $time, $m)) {
            return $m[0];
        }

        try {
            return Carbon::parse($time)->format('H:i');
        } catch (\Throwable) {
            return $time;
        }
    }

    protected function departureLine(Event $event): string
    {
        $date = $event->start_date?->format('Y.m.d') ?? '—';
        $time = $this->formatTime((string) ($event->departure_time ?? $event->substitution_time ?? ''));
        $place = $this->placeLabel($event);
        $timePart = $time !== '' ? ' godz. '.$time : '';

        return trim($date.$timePart.' - '.$place, ' -');
    }

    protected function returnLine(Event $event): string
    {
        $date = ($event->end_date ?? $event->start_date)?->format('Y.m.d') ?? '—';
        $time = $this->formatTime((string) ($event->return_time ?? ''));
        $place = $this->placeLabel($event);
        $timePart = $time !== '' ? ' godz. '.$time : '';

        return trim($date.$timePart.' - '.$place, ' -');
    }

    protected function pickupPlace(Event $event): string
    {
        return $this->placeLabel($event);
    }

    protected function pickupTimeLabel(Event $event): string
    {
        $date = $event->start_date?->format('Y-m-d') ?? '—';
        $time = $this->formatTime((string) ($event->substitution_time ?? $event->departure_time ?? ''));
        if ($time === '') {
            return $date;
        }

        return $date.' godz. '.$time;
    }

    protected function hotelLabel(Event $event): string
    {
        $event->loadMissing('hotelStays.contractor');
        $parts = $event->hotelStays
            ->map(function ($stay): string {
                $contractor = $stay->contractor;
                if (! $contractor) {
                    return '';
                }
                $name = trim((string) ($contractor->name ?? ''));
                $address = collect([
                    trim(implode(' ', array_filter([
                        (string) ($contractor->street ?? ''),
                        (string) ($contractor->house_number ?? ''),
                    ]))),
                    trim(implode(' ', array_filter([
                        (string) ($contractor->postal_code ?? ''),
                        (string) ($contractor->city ?? ''),
                    ]))),
                ])->filter()->implode(', ');

                return collect([$name, $address])->filter()->implode(', ');
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($parts === []) {
            return 'zgodnie z programem';
        }

        return implode('; ', $parts);
    }

    protected function insuranceLabel(Event $event): string
    {
        $terms = trim((string) ($event->insurance_terms ?? ''));
        if ($terms !== '') {
            return $terms;
        }

        $isForeign = (bool) ($event->eventTemplate?->isForeignTrip() ?? false);

        return $isForeign
            ? WordAgreementContent::DEFAULT_INSURANCE_FOREIGN
            : WordAgreementContent::DEFAULT_INSURANCE_DOMESTIC;
    }

    protected function priceIncludes(Event $event): string
    {
        $event->loadMissing('eventTemplate.eventPriceDescription');
        $html = trim((string) optional($event->eventTemplate?->eventPriceDescription?->first())->description);
        if ($html !== '') {
            $parsed = $this->offerContent->parsePriceDescriptionHtml($html);
            if ($parsed['includes'] !== []) {
                return implode(', ', $parsed['includes']);
            }
        }

        return WordAgreementContent::DEFAULT_PRICE_INCLUDES;
    }

    /**
     * @return list<string>
     */
    protected function paymentScheduleLines(Event $event, ?Contract $contract, float $totalPrice): array
    {
        if ($contract) {
            $contract->loadMissing('paymentSchedules');
            if ($contract->paymentSchedules->isNotEmpty()) {
                return $contract->paymentSchedules
                    ->sortBy('sort_order')
                    ->values()
                    ->map(function ($schedule): string {
                        $label = trim((string) ($schedule->label ?? ''));
                        $label = $label !== '' ? $label : 'Rata';
                        $amount = (float) ($schedule->amount ?? 0);
                        $foreign = (float) ($schedule->amount_foreign ?? 0);
                        $due = optional($schedule->due_date)->format('Y-m-d') ?? '—';

                        if ($foreign > 0.009 && $amount <= 0.009) {
                            $code = strtoupper((string) ($schedule->currency_code ?: 'EUR'));

                            return sprintf(
                                '%s w kwocie: %s %s płatna do dnia: %s',
                                $label,
                                number_format($foreign, 2, ',', ' '),
                                $code,
                                $due
                            );
                        }

                        return sprintf(
                            '%s w kwocie: %s złotych brutto płatna do dnia: %s',
                            $label,
                            number_format($amount, 0, ',', ' '),
                            $due
                        );
                    })
                    ->all();
            }
        }

        if ($totalPrice <= 0) {
            return ['Harmonogram wpłat zostanie uzupełniony po ustaleniu ceny.'];
        }

        $rows = $this->installments->materializeForBase($event, $totalPrice);
        if ($rows === []) {
            // Fallback jak w PDF: zaliczka / dopłata z domyślnego szablonu (bez zapisu).
            $start = $event->start_date ? Carbon::parse($event->start_date) : null;
            $advanceDue = $start?->copy()->subDays(30)->format('Y-m-d') ?? '—';
            $restDue = $start?->copy()->subDays(14)->format('Y-m-d') ?? '—';
            $advance = round($totalPrice * 0.10, 0);
            $rest = round($totalPrice - $advance, 0);

            return [
                sprintf('Zaliczka w kwocie: %s złotych brutto płatna do dnia: %s', number_format($advance, 0, ',', ' '), $advanceDue),
                sprintf('Dopłata do całości w kwocie: %s złotych brutto płatna do dnia: %s', number_format($rest, 0, ',', ' '), $restDue),
            ];
        }

        return collect($rows)
            ->filter(fn (array $row): bool => (float) ($row['amount'] ?? 0) > 0.009
                || (float) ($row['amount_foreign'] ?? 0) > 0.009)
            ->map(function (array $row): string {
                $label = trim((string) ($row['label'] ?? '')) ?: 'Rata';
                $due = filled($row['due_date'] ?? null)
                    ? Carbon::parse($row['due_date'])->format('Y-m-d')
                    : '—';
                $amount = (float) ($row['amount'] ?? 0);
                $foreign = (float) ($row['amount_foreign'] ?? 0);

                if ($foreign > 0.009 && $amount <= 0.009) {
                    $code = strtoupper((string) ($row['currency_code'] ?: 'EUR'));

                    return sprintf(
                        '%s w kwocie: %s %s płatna do dnia: %s',
                        $label,
                        number_format($foreign, 2, ',', ' '),
                        $code,
                        $due
                    );
                }

                return sprintf(
                    '%s w kwocie: %s złotych brutto płatna do dnia: %s',
                    $label,
                    number_format($amount, 0, ',', ' '),
                    $due
                );
            })
            ->values()
            ->all();
    }
}
