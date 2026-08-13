<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Facades\Schema;

/**
 * Pola transportowe do szablonów umów — źródło: impreza (miejsce startu, godziny).
 */
final class AgreementTransportPayloadResolver
{
    /**
     * @return array{
     *     departure_place: string,
     *     departure_date: string,
     *     departure_time: string,
     *     return_place: string,
     *     return_date: string,
     *     return_time: string
     * }
     */
    public function forEvent(?Event $event, mixed $fallbackStartDate = null, mixed $fallbackEndDate = null): array
    {
        $startDate = $event?->start_date ?: $fallbackStartDate;
        $endDate = $event?->end_date ?: $fallbackEndDate;

        if (! $event) {
            return [
                'departure_place' => '—',
                'departure_date' => $this->formatDate($startDate),
                'departure_time' => '—',
                'return_place' => '—',
                'return_date' => $this->formatDate($endDate),
                'return_time' => '—',
            ];
        }

        $event->loadMissing('startPlace');

        $pickupPlace = $this->resolvePickupPlace($event);
        $returnPlace = $this->resolveReturnPlace($event, $pickupPlace);

        return [
            'departure_place' => $pickupPlace !== '' ? $pickupPlace : '—',
            'departure_date' => $this->formatDate($startDate),
            'departure_time' => $this->formatTime($event->departure_time ?? null),
            'return_place' => $returnPlace !== '' ? $returnPlace : '—',
            'return_date' => $this->formatDate($endDate),
            'return_time' => Schema::hasColumn('events', 'return_time')
                ? $this->formatTime($event->return_time ?? null)
                : '—',
        ];
    }

    private function resolvePickupPlace(Event $event): string
    {
        $pickup = trim(strip_tags((string) ($event->pickup_place_details ?? '')));

        if ($pickup !== '') {
            return $pickup;
        }

        return (string) ($event->startPlace?->name ?? '');
    }

    private function resolveReturnPlace(Event $event, string $pickupPlace): string
    {
        // Na razie powrót = miejsce zbiórki / startu (spójne z EventFolderPdfService).
        if ($pickupPlace !== '') {
            return $pickupPlace;
        }

        return (string) ($event->startPlace?->name ?? '');
    }

    private function formatDate(mixed $date): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('d.m.Y');
        }

        if (is_string($date) && trim($date) !== '') {
            try {
                return \Carbon\Carbon::parse($date)->format('d.m.Y');
            } catch (\Throwable) {
                return '—';
            }
        }

        return '—';
    }

    private function formatTime(mixed $time): string
    {
        if (! filled($time)) {
            return '—';
        }

        $raw = (string) $time;

        return strlen($raw) >= 5 ? substr($raw, 0, 5) : $raw;
    }
}
