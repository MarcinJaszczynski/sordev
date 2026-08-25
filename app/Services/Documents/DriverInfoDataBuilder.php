<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\Event;
use App\Models\EventHotelStay;
use App\Models\EventProgramPoint;
use App\Services\EventFolderPdfService;
use App\Support\ContractorContactDetails;
use App\Support\EventParticipantGroupLabels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Dane pod PDF „Informacje dla kierowcy” / teczka kierowcy.
 */
final class DriverInfoDataBuilder
{
    public function __construct(
        private readonly EventFolderPdfService $folderPdfService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Event $event): array
    {
        $event->loadMissing([
            'assignedUser',
            'startPlace',
            'bus',
            'contractor',
            'qtyVariants',
            'hotelStays.contractor',
            'hotelStays.contractorLocation',
            'hotelProgramPoints.contractor',
            'hotelProgramPoints.contractorLocation',
            'hotelProgramPoints.templatePoint',
            'documents',
            'activeSettlement.documents',
        ]);

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
        $travelLegends = $this->folderPdfService->buildTravelLegends($event);

        return [
            'audience' => 'driver',
            'audienceLabel' => 'Informacje dla kierowcy',
            'event' => $event,
            'company' => $company,
            'generatedAt' => now(),
            'eventTitleLine' => $this->eventTitleLine($event),
            'pilot' => [
                'name' => $event->assignedUser?->name,
                'phone' => $event->assignedUser?->phone,
            ],
            'driver' => [
                'name' => Schema::hasColumn('events', 'driver_name') ? $event->driver_name : null,
                'phone' => Schema::hasColumn('events', 'driver_phone') ? $event->driver_phone : null,
                'vehicle' => Schema::hasColumn('events', 'vehicle_registration') ? $event->vehicle_registration : null,
                'company' => Schema::hasColumn('events', 'transport_company_name') ? $event->transport_company_name : null,
            ],
            'pickup' => $this->pickupBlock($event),
            'departure' => $this->departureBlock($event),
            'return' => $this->returnBlock($event),
            // Format operacyjny dla kierowcy: „16+1” = uczestnicy + opiekunowie/gratis.
            'passengerSummary' => sprintf('%d+%d', $participantCount, $gratis),
            'participantCompactLine' => sprintf('%d+%d', $participantCount, $gratis),
            'participantSummaryLine' => sprintf(
                '%d+%d os. (%d uczestn. + %d %s; obsługa: %d; kierowca: %d)',
                $participantCount,
                $gratis,
                $participantCount,
                $gratis,
                mb_strtolower(EventParticipantGroupLabels::GRATIS_GENITIVE),
                $staff,
                $driverCount
            ),
            'participantCount' => $participantCount,
            'gratisCount' => $gratis,
            'staffCount' => $staff,
            'driverCount' => $driverCount,
            'hotels' => $this->hotelsList($event),
            'travelLegends' => $travelLegends,
            'driverNotes' => trim(strip_tags((string) ($event->driver_notes ?? ''))),
            'busInfo' => trim(strip_tags((string) ($event->bus_info ?? ''))),
            'attachedFiles' => $this->attachmentLabels($event),
        ];
    }

    /**
     * @return array{date: ?string, time: ?string, place: string, label: string}
     */
    private function pickupBlock(Event $event): array
    {
        $time = null;
        if (Schema::hasColumn('events', 'substitution_time') && filled($event->substitution_time)) {
            $time = substr((string) $event->substitution_time, 0, 5);
        } elseif (filled($event->departure_time)) {
            $time = substr((string) $event->departure_time, 0, 5);
        }

        $place = trim(strip_tags((string) ($event->pickup_place_details ?? '')));
        if ($place === '') {
            $place = (string) ($event->startPlace?->name ?? '—');
        }

        $date = optional($event->start_date)->format('d.m.Y');

        return [
            'date' => $date,
            'time' => $time,
            'place' => $place,
            'label' => trim(($date ?? '—').($time ? ' godz. '.$time : '')),
        ];
    }

    /**
     * @return array{date: ?string, time: ?string, label: string}
     */
    private function departureBlock(Event $event): array
    {
        $time = filled($event->departure_time) ? substr((string) $event->departure_time, 0, 5) : null;
        $date = optional($event->start_date)->format('d.m.Y');

        return [
            'date' => $date,
            'time' => $time,
            'label' => trim(($date ?? '—').($time ? ' godz. '.$time : '')),
        ];
    }

    /**
     * @return array{date: ?string, time: ?string, label: string}
     */
    private function returnBlock(Event $event): array
    {
        $time = null;
        if (Schema::hasColumn('events', 'return_time') && filled($event->return_time)) {
            $time = substr((string) $event->return_time, 0, 5);
        }
        $date = optional($event->end_date)->format('d.m.Y')
            ?? optional($event->start_date)->format('d.m.Y');

        return [
            'date' => $date,
            'time' => $time,
            'label' => trim(($date ?? '—').($time ? ' godz. '.$time : '')),
        ];
    }

    /**
     * @return list<array{name: string, branch: ?string, address: ?string, phone: ?string, days: string}>
     */
    private function hotelsList(Event $event): array
    {
        if ($event->hotelStays->isNotEmpty()) {
            return $event->hotelStays
                ->filter(fn (EventHotelStay $stay): bool => (int) ($stay->contractor_id ?? 0) > 0)
                ->groupBy(fn (EventHotelStay $stay): string => (int) $stay->contractor_id.'_'.(int) ($stay->contractor_location_id ?? 0))
                ->map(function (Collection $stays): array {
                    /** @var EventHotelStay $first */
                    $first = $stays->sortBy('day')->first();
                    $meta = ContractorContactDetails::operationalMeta($first->contractor, $first->contractorLocation);
                    $days = $stays->pluck('day')->map(fn ($d) => (int) $d)->unique()->sort()->values();

                    return [
                        'name' => (string) ($meta['company_name'] ?? $first->contractor?->name ?? 'Hotel'),
                        'branch' => $meta['branch_name'] ?? null,
                        'address' => $meta['address'] ?? null,
                        'phone' => $meta['phone'] ?? null,
                        'days' => 'Dni: '.$days->implode(', '),
                    ];
                })
                ->values()
                ->all();
        }

        return $event->hotelProgramPoints
            ->sortBy(fn ($p) => [(int) ($p->day ?? 1), (int) ($p->order ?? 0)])
            ->map(function (EventProgramPoint $point): array {
                $meta = ContractorContactDetails::operationalMeta($point->contractor, $point->contractorLocation);

                return [
                    'name' => (string) ($meta['company_name'] ?? $point->contractor?->name ?? $point->name ?? 'Hotel'),
                    'branch' => $meta['branch_name'] ?? null,
                    'address' => $meta['address'] ?? null,
                    'phone' => $meta['phone'] ?? null,
                    'days' => 'Dzień '.(int) ($point->day ?? 1),
                ];
            })
            ->values()
            ->all();
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
            if (! (bool) ($doc->attach_to_driver_pdf ?? false)) {
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
            if (! (bool) ($doc->attach_to_driver_pdf ?? false)) {
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
