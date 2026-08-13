<?php

namespace App\Services;

use App\Models\Event;
use App\Support\ContractorContactDetails;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Logika pakietów PDF imprezy (teczka, kierowca, hotel, program).
 */
final class EventFolderPdfService
{
    /**
     * @return array{
     *     departure: string,
     *     destination: string,
     *     return: string,
     *     return_place: string,
     *     colors: array{departure: string, destination: string, return: string, return_place: string, backgrounds: array<string, string>}
     * }
     */
    public function buildTravelLegends(Event $event): array
    {
        Carbon::setLocale('pl');

        $departure = '—';
        if ($event->start_date) {
            $d = $event->start_date->copy()->locale('pl');
            if (filled($event->departure_time)) {
                $departure = 'godz. '.$this->formatClock($event->departure_time).', '.$d->translatedFormat('l').', '.$d->format('d.m.Y');
            } else {
                $departure = $d->translatedFormat('l').', '.$d->format('d.m.Y');
            }
        }

        $destination = '—';
        $destinationIsHotel = false;
        $firstHotel = $event->hotelProgramPoints
            ->sortBy(fn ($p) => [(int) ($p->day ?? 1), (int) ($p->order ?? 0)])
            ->first();
        if ($firstHotel) {
            $destinationIsHotel = true;
            if ($firstHotel->contractor) {
                $meta = ContractorContactDetails::operationalMeta(
                    $firstHotel->contractor,
                    $firstHotel->contractorLocation,
                );
                $lines = array_filter([
                    (string) ($meta['company_name'] ?? $firstHotel->contractor->name),
                    $meta['branch_name'] ?? null,
                    $meta['address'] ?? null,
                    $meta['phone'] ?? null,
                ]);
                $destination = implode("\n", $lines);
            } else {
                $destination = $firstHotel->name ?: optional($firstHotel->templatePoint)->name ?: '—';
            }
        }

        $return = '—';
        if ($event->end_date) {
            $e = $event->end_date->copy()->locale('pl');
            if (Schema::hasColumn('events', 'return_time') && filled($event->return_time)) {
                $return = 'godz. '.$this->formatClock($event->return_time).', '.$e->translatedFormat('l').', '.$e->format('d.m.Y');
            } else {
                $return = $e->translatedFormat('l').', '.$e->format('d.m.Y');
            }
        }

        $returnPlace = '—';
        if (filled($event->pickup_place_details)) {
            $returnPlace = trim(strip_tags((string) $event->pickup_place_details));
        } elseif ($event->startPlace) {
            $returnPlace = (string) $event->startPlace->name;
        }

        $pickupPlace = trim(strip_tags((string) ($event->pickup_place_details ?? '')));
        if ($pickupPlace === '' && $event->startPlace) {
            $pickupPlace = (string) $event->startPlace->name;
        }

        $returnDiffersFromPickup = $this->normalizePlaceLabel($returnPlace) !== $this->normalizePlaceLabel($pickupPlace)
            && $returnPlace !== '—'
            && $pickupPlace !== '';

        $isMultiDay = $event->start_date && $event->end_date
            && ! $event->start_date->isSameDay($event->end_date);

        $colors = [
            'departure' => '#1d4ed8',
            'destination' => $destinationIsHotel ? '#15803d' : '#111827',
            'return' => $isMultiDay ? '#c2410c' : '#111827',
            'return_place' => $returnDiffersFromPickup ? '#7c3aed' : '#374151',
            'backgrounds' => [
                'departure' => '#eff6ff',
                'destination' => $destinationIsHotel ? '#f0fdf4' : '#ffffff',
                'return' => $isMultiDay ? '#fff7ed' : '#ffffff',
                'return_place' => $returnDiffersFromPickup ? '#f5f3ff' : '#ffffff',
            ],
        ];

        return [
            'departure' => $departure,
            'destination' => $destination,
            'return' => $return,
            'return_place' => $returnPlace,
            'colors' => $colors,
        ];
    }

    private function normalizePlaceLabel(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? ''));
    }

    private function formatClock(mixed $time): string
    {
        if (! filled($time)) {
            return '';
        }

        return substr((string) $time, 0, 5);
    }
}
