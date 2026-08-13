<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\Event;
use App\Models\EventHotelStay;
use App\Models\EventProgramPoint;
use App\Support\ContractorContactDetails;
use App\Support\EventParticipantGroupLabels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Dane pod PDF „Agenda dla hotelu” — jeden hotel (contractor + lokalizacja) na dokument.
 */
final class HotelAgendaDataBuilder
{
    /**
     * Unikalne hotele z planu noclegów imprezy.
     *
     * @return Collection<int, array{
     *   key: string,
     *   contractor_id: int,
     *   contractor_location_id: int|null,
     *   name: string,
     *   branch: ?string,
     *   address: ?string,
     *   phone: ?string,
     *   email: ?string,
     *   stays: Collection<int, EventHotelStay>
     * }>
     */
    public function hotelsForEvent(Event $event): Collection
    {
        $event->loadMissing([
            'hotelStays.contractor',
            'hotelStays.contractorLocation',
            'hotelStays.programPoint',
        ]);

        return $event->hotelStays
            ->filter(fn (EventHotelStay $stay): bool => (int) ($stay->contractor_id ?? 0) > 0)
            ->groupBy(fn (EventHotelStay $stay): string => $this->hotelKey(
                (int) $stay->contractor_id,
                $stay->contractor_location_id !== null ? (int) $stay->contractor_location_id : null,
            ))
            ->map(function (Collection $stays, string $key): array {
                /** @var EventHotelStay $first */
                $first = $stays->sortBy('day')->first();
                $meta = ContractorContactDetails::operationalMeta($first->contractor, $first->contractorLocation);

                return [
                    'key' => $key,
                    'contractor_id' => (int) $first->contractor_id,
                    'contractor_location_id' => $first->contractor_location_id !== null
                        ? (int) $first->contractor_location_id
                        : null,
                    'name' => (string) ($meta['company_name'] ?? $first->contractor?->name ?? 'Hotel'),
                    'branch' => $meta['branch_name'] ?? null,
                    'address' => $meta['address'] ?? null,
                    'phone' => $meta['phone'] ?? null,
                    'email' => $meta['email'] ?? null,
                    'stays' => $stays->sortBy('day')->values(),
                ];
            })
            ->sortBy('name')
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Event $event, int $contractorId, ?int $locationId = null): array
    {
        $event->loadMissing([
            'assignedUser',
            'startPlace',
            'qtyVariants',
            'hotelStays.contractor',
            'hotelStays.contractorLocation',
            'hotelStays.programPoint',
            'programPoints.templatePoint',
            'programPoints.contractor',
            'documents',
            'activeSettlement.documents',
        ]);

        $hotels = $this->hotelsForEvent($event);
        $hotel = $hotels->first(function (array $row) use ($contractorId, $locationId): bool {
            if ((int) $row['contractor_id'] !== $contractorId) {
                return false;
            }

            $rowLocation = $row['contractor_location_id'];

            return $locationId === null
                ? $rowLocation === null
                : (int) ($rowLocation ?? 0) === $locationId;
        });

        if ($hotel === null) {
            throw new \InvalidArgumentException('Hotel nie jest przypisany do planu noclegów tej imprezy.');
        }

        $participantCount = max(0, (int) ($event->participant_count ?? 0));
        if (Schema::hasTable('event_participants')) {
            $rosterCount = $event->activeParticipants()->count();
            if ($rosterCount > 0) {
                $participantCount = $rosterCount;
            }
        }
        $gratis = max(0, $event->resolveGratisCountForParticipantCount($participantCount ?: 1));
        $variant = $event->qtyVariants
            ->sortBy(fn ($v) => abs(((int) ($v->qty ?? 0)) - max(1, $participantCount)))
            ->first();
        $staff = max(0, (int) ($variant->staff ?? 0));
        $driverCount = max(1, (int) ($variant->driver ?? 1));

        $company = config('company', []);
        $dates = optional($event->start_date)->format('d.m.Y') ?: '—';
        if ($event->end_date) {
            $dates .= ' - '.$event->end_date->format('d.m.Y');
        }

        $participantSummary = sprintf(
            '%d (w tym %d %s)',
            $participantCount,
            $gratis,
            mb_strtolower(EventParticipantGroupLabels::GRATIS_GENITIVE)
        );
        $extras = array_filter([
            $event->assigned_to ? 'pilot' : null,
            $driverCount > 0 ? 'kierowca' : null,
        ]);
        if ($extras !== []) {
            $participantSummary .= ' + '.implode(' i ', $extras);
        }

        return [
            'audience' => 'hotel_agenda',
            'audienceLabel' => 'Agenda dla hotelu',
            'event' => $event,
            'company' => $company,
            'generatedAt' => now(),
            'hotel' => $hotel,
            'pilot' => [
                'name' => $event->assignedUser?->name,
                'phone' => $event->assignedUser?->phone,
            ],
            'driver' => [
                'name' => Schema::hasColumn('events', 'driver_name') ? $event->driver_name : null,
                'phone' => Schema::hasColumn('events', 'driver_phone') ? $event->driver_phone : null,
            ],
            'scheduleRows' => $this->scheduleRows($event, $hotel),
            'participantSummary' => $participantSummary,
            'participantCount' => $participantCount,
            'gratisCount' => $gratis,
            'staffCount' => $staff,
            'driverCount' => $driverCount,
            'dietSummary' => filled($event->diet_info)
                ? trim(strip_tags((string) $event->diet_info))
                : null,
            'hotelNotes' => trim(strip_tags((string) ($event->hotel_notes ?? ''))),
            'attachedFiles' => $this->attachmentLabels($event),
            'eventTitleLine' => $this->eventTitleLine($event),
            'termLine' => $dates,
        ];
    }

    public function hotelKey(int $contractorId, ?int $locationId): string
    {
        return $contractorId.'_'.($locationId ?? 0);
    }

    /**
     * @param  array{
     *   contractor_id: int,
     *   contractor_location_id: int|null,
     *   stays: Collection<int, EventHotelStay>
     * }  $hotel
     * @return list<array{datetime: string, date: string, time: string, program: string, notes: string}>
     */
    private function scheduleRows(Event $event, array $hotel): array
    {
        $rows = [];
        $days = $hotel['stays']->pluck('day')->map(fn ($d) => (int) $d)->unique()->sort()->values();
        $contractorId = (int) $hotel['contractor_id'];

        // Punkty hotelowe / usługi hotelu (śniadanie, obiadokolacja, zakwaterowanie…) — źródło główne jak we wzorcu.
        $points = $event->programPoints
            ->filter(function (EventProgramPoint $point) use ($days, $contractorId): bool {
                if (! $days->contains((int) ($point->day ?? 1))) {
                    return false;
                }

                $isHotelRelated = (bool) ($point->is_hotel ?? false) || (bool) ($point->is_hotel_service ?? false);
                $sameContractor = (int) ($point->contractor_id ?? 0) === $contractorId;

                return $isHotelRelated && $sameContractor;
            })
            ->sortBy(fn (EventProgramPoint $p) => [(int) ($p->day ?? 1), (string) ($p->start_time ?? '99:99'), (int) ($p->order ?? 0)])
            ->values();

        foreach ($points as $point) {
            $date = $event->dateForProgramDay((int) ($point->day ?? 1));
            $datePart = $date?->format('d.m.Y') ?? ('Dzień '.$point->day);
            $timePart = $this->formatPointTime($point);
            $name = $point->name ?: ($point->templatePoint?->name ?? 'Punkt');
            $notes = trim(strip_tags((string) (
                $point->resolvedOfficeNotes()
                ?? $point->resolvedDescription()
                ?? ''
            )));

            $rows[] = [
                'datetime' => trim($datePart.($timePart !== '' ? ' '.$timePart : '')),
                'date' => $datePart,
                'time' => $timePart,
                'program' => $name,
                'notes' => $notes !== '' ? $notes : '—',
                'sort' => ((int) ($point->day ?? 1)) * 1000
                    + $this->timeSortKey($point->start_time)
                    + (int) ($point->order ?? 0),
            ];
        }

        // Jeśli brak punktów hotelowych — fallback z planu noclegów (stay).
        if ($rows === []) {
            foreach ($hotel['stays'] as $stay) {
                /** @var EventHotelStay $stay */
                $date = $event->dateForProgramDay((int) $stay->day);
                $dateLabel = $date?->format('d.m.Y') ?? ('Dzień '.$stay->day);
                $pointName = $stay->programPoint?->name
                    ?: ($stay->programPoint?->templatePoint?->name ?? 'Zakwaterowanie / nocleg');

                $notes = trim(implode("\n", array_filter([
                    filled($stay->offer_notes) ? trim(strip_tags((string) $stay->offer_notes)) : null,
                    filled($stay->notes) ? trim(strip_tags((string) $stay->notes)) : null,
                ])));

                $rows[] = [
                    'datetime' => $dateLabel,
                    'date' => $dateLabel,
                    'time' => '',
                    'program' => $pointName,
                    'notes' => $notes !== '' ? $notes : '—',
                    'sort' => ((int) $stay->day) * 1000,
                ];
            }
        }

        return collect($rows)
            ->sortBy('sort')
            ->map(fn (array $row): array => [
                'datetime' => $row['datetime'],
                'date' => $row['date'],
                'time' => $row['time'],
                'program' => $row['program'],
                'notes' => $row['notes'],
            ])
            ->values()
            ->all();
    }

    private function formatPointTime(EventProgramPoint $point): string
    {
        $start = $point->start_time ? substr((string) $point->start_time, 0, 5) : null;
        $end = $point->end_time ? substr((string) $point->end_time, 0, 5) : null;

        if ($start && $end) {
            return $start.'–'.$end;
        }

        return $start ?: ($end ?: '');
    }

    private function timeSortKey(mixed $time): int
    {
        if (! filled($time)) {
            return 500;
        }

        $parts = explode(':', substr((string) $time, 0, 5));

        return ((int) ($parts[0] ?? 0)) * 60 + (int) ($parts[1] ?? 0);
    }

    private function eventTitleLine(Event $event): string
    {
        $dates = optional($event->start_date)->format('d.m.Y') ?: '—';
        if ($event->end_date) {
            $dates .= ' – '.$event->end_date->format('d.m.Y');
        }

        $code = filled($event->code) ? $event->code.' — ' : '';

        return $code.$event->name.': '.$dates;
    }

    /**
     * @return list<array{label: string, type: string}>
     */
    private function attachmentLabels(Event $event): array
    {
        $items = [];

        foreach ($event->documents ?? [] as $doc) {
            if (! (bool) ($doc->attach_to_hotel_pdf ?? false)) {
                continue;
            }
            if (($doc->approval_status ?? 'pending') !== 'approved') {
                continue;
            }

            $items[] = [
                'label' => (string) ($doc->name ?: 'Dokument #'.$doc->id),
                'type' => 'Dokument imprezy',
            ];
        }

        foreach ($event->activeSettlement?->documents ?? [] as $doc) {
            if (! (bool) ($doc->attach_to_hotel_pdf ?? false)) {
                continue;
            }
            if (($doc->approval_status ?? 'pending') !== 'approved') {
                continue;
            }

            $items[] = [
                'label' => (string) ($doc->document_number ?: 'Dokument #'.$doc->id),
                'type' => (string) ($doc->document_type ?? 'Załącznik'),
            ];
        }

        return $items;
    }
}
