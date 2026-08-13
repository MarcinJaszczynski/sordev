<?php

namespace App\Support\Calendar;

use App\Filament\Resources\ContractorResource;
use App\Filament\Resources\ContractResource;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventSettlementResource;
use App\Filament\Resources\ReservationResource;
use App\Filament\Resources\VendorInvoiceResource;
use App\Models\Task;

final class CalendarEventLinks
{
    /**
     * @param  array<int, array{label: string, url: string, icon?: string}|null>  $links
     * @return array<int, array{label: string, url: string, icon: string}>
     */
    public static function compact(array $links): array
    {
        $seen = [];
        $result = [];

        foreach ($links as $link) {
            if (! is_array($link) || blank($link['url'] ?? null)) {
                continue;
            }

            if (isset($seen[$link['url']])) {
                continue;
            }

            $seen[$link['url']] = true;
            $result[] = [
                'label' => $link['label'],
                'url' => $link['url'],
                'icon' => $link['icon'] ?? 'heroicon-o-arrow-top-right-on-square',
            ];
        }

        return $result;
    }

    /**
     * @return array{label: string, url: string, icon: string}|null
     */
    public static function link(?string $url, string $label, string $icon = 'heroicon-o-arrow-top-right-on-square'): ?array
    {
        if (! filled($url)) {
            return null;
        }

        return [
            'label' => $label,
            'url' => $url,
            'icon' => $icon,
        ];
    }

    /**
     * @return array{label: string, url: string, icon: string}|null
     */
    public static function event(?int $eventId, string $label = 'Impreza'): ?array
    {
        if (! $eventId) {
            return null;
        }

        return self::link(
            EventResource::getUrl('edit', ['record' => $eventId]),
            $label,
            'heroicon-o-map',
        );
    }

    /**
     * @return array{label: string, url: string, icon: string}|null
     */
    public static function eventProgram(?int $eventId, string $label = 'Program imprezy'): ?array
    {
        if (! $eventId) {
            return null;
        }

        return self::link(
            EventResource::getUrl('edit-program', ['record' => $eventId]),
            $label,
            'heroicon-o-calendar-days',
        );
    }

    /**
     * @return array{label: string, url: string, icon: string}|null
     */
    public static function eventReservations(?int $eventId, string $label = 'Rezerwacje imprezy'): ?array
    {
        if (! $eventId) {
            return null;
        }

        return self::link(
            EventResource::getUrl('reservations', ['record' => $eventId]),
            $label,
            'heroicon-o-ticket',
        );
    }

    /**
     * @return array{label: string, url: string, icon: string}|null
     */
    public static function eventDocuments(?int $eventId): ?array
    {
        if (! $eventId) {
            return null;
        }

        return self::link(
            EventResource::getUrl('documents', ['record' => $eventId]),
            'Dokumenty imprezy',
            'heroicon-o-folder',
        );
    }

    /**
     * @return array{label: string, url: string, icon: string}|null
     */
    public static function contractor(?int $contractorId): ?array
    {
        if (! $contractorId) {
            return null;
        }

        return self::link(
            ContractorResource::getUrl('edit', ['record' => $contractorId]),
            'Kontrahent',
            'heroicon-o-building-office',
        );
    }

    /**
     * @return array{label: string, url: string, icon: string}|null
     */
    public static function task(int $taskId): ?array
    {
        $task = Task::query()->find($taskId);

        if (! $task) {
            return null;
        }

        return self::link(
            \App\Support\Tasks\TaskNavigation::editUrl($task),
            'Zadanie',
            'heroicon-o-clipboard-document-list',
        );
    }

    /**
     * @return array{label: string, url: string, icon: string}|null
     */
    public static function reservation(int $reservationId): ?array
    {
        return self::link(
            ReservationResource::getUrl('edit', ['record' => $reservationId]),
            'Rezerwacja',
            'heroicon-o-ticket',
        );
    }

    /**
     * @return array{label: string, url: string, icon: string}|null
     */
    public static function vendorInvoice(int $invoiceId): ?array
    {
        return self::link(
            VendorInvoiceResource::getUrl('edit', ['record' => $invoiceId]),
            'Faktura KSeF',
            'heroicon-o-document-text',
        );
    }

    /**
     * @return array{label: string, url: string, icon: string}|null
     */
    public static function settlement(int $settlementId): ?array
    {
        $url = EventSettlementResource::getEventFinanceUrlForSettlement($settlementId);

        return self::link(
            $url,
            'Finanse imprezy',
            'heroicon-o-banknotes',
        );
    }

    /**
     * @return array{label: string, url: string, icon: string}|null
     */
    public static function contract(int $contractId): ?array
    {
        return self::link(
            ContractResource::getUrl('edit', ['record' => $contractId]),
            'Kontrakt TFG',
            'heroicon-o-document-check',
        );
    }

    /**
     * @param  array<int, array{label: string, url: string, icon?: string}|null>  $links
     * @return array{label: string, url: string, icon: string}|null
     */
    public static function firstUrl(array $links): ?array
    {
        $compact = self::compact($links);

        return $compact[0] ?? null;
    }
}
