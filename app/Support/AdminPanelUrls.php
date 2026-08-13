<?php

declare(strict_types=1);

namespace App\Support;

use App\Filament\Pages\ClientInvoiceRequestsInboxPage;
use App\Filament\Resources\ContractResource;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventSettlementResource;
use App\Filament\Resources\ReservationResource;
use App\Filament\Resources\TaskResource;
use App\Filament\Resources\VendorInvoiceResource;
use App\Models\ClientInvoiceRequest;
use App\Models\Contract;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\Reservation;
use App\Models\VendorInvoice;

/**
 * Cienka warstwa URL-i panelu admin. Support może znać Filament —
 * Services powinny wołać tu, zamiast importować Resources/Pages.
 */
final class AdminPanelUrls
{
    public static function eventFinance(Event|int $event): string
    {
        return EventResource::getUrl('finance', ['record' => self::eventKey($event)]);
    }

    public static function eventEdit(Event|int $event): string
    {
        return EventResource::getUrl('edit', ['record' => self::eventKey($event)]);
    }

    public static function eventReservations(Event|int $event): string
    {
        return EventResource::getUrl('reservations', ['record' => self::eventKey($event)]);
    }

    public static function eventPilot(Event|int $event): string
    {
        return EventResource::getUrl('pilot', ['record' => self::eventKey($event)]);
    }

    public static function eventTransport(Event|int $event): string
    {
        return EventResource::getUrl('transport', ['record' => self::eventKey($event)]);
    }

    public static function eventHotelPlanning(Event|int $event): string
    {
        return EventResource::getUrl('hotel-planning', ['record' => self::eventKey($event)]);
    }

    public static function eventFinanceForSettlement(EventSettlement|int|null $settlement): ?string
    {
        return EventSettlementResource::getEventFinanceUrlForSettlement($settlement);
    }

    public static function reservationEdit(Reservation|int $reservation): string
    {
        return ReservationResource::getUrl('edit', ['record' => self::modelKey($reservation)]);
    }

    public static function contractEdit(Contract|int $contract): string
    {
        return ContractResource::getUrl('edit', ['record' => self::modelKey($contract)]);
    }

    public static function vendorInvoiceEdit(VendorInvoice|int $invoice): string
    {
        return VendorInvoiceResource::getUrl('edit', ['record' => self::modelKey($invoice)]);
    }

    public static function taskBoard(): string
    {
        return TaskResource::getUrl('index');
    }

    /**
     * @param  array<string, mixed>  $tableFilters
     */
    public static function clientInvoiceRequestsInbox(array $tableFilters = []): string
    {
        if ($tableFilters === []) {
            $tableFilters = [
                'status' => ['value' => ClientInvoiceRequest::STATUS_PENDING],
            ];
        }

        return ClientInvoiceRequestsInboxPage::getUrl([
            'tableFilters' => $tableFilters,
        ]);
    }

    private static function eventKey(Event|int $event): int
    {
        return is_int($event) ? $event : (int) $event->getKey();
    }

    private static function modelKey(object|int $model): int
    {
        return is_int($model) ? $model : (int) $model->getKey();
    }
}
