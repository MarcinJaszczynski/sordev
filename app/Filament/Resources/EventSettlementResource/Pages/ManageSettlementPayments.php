<?php

namespace App\Filament\Resources\EventSettlementResource\Pages;

use App\Filament\Resources\EventSettlementResource;
use App\Models\EventSettlement;
use Filament\Resources\Pages\Page;

/**
 * Legacy payments — bookmark/deep-link only; redirects to EventFinance → Wpłaty.
 *
 * Nie dziedziczy po SingleRelationManagerPage: mount redirectu zostawia $record
 * jako string z routy, a getRecord() wymaga Modelu → TypeError.
 */
class ManageSettlementPayments extends Page
{
    protected static string $resource = EventSettlementResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.event-finance-redirect';

    protected static ?string $navigationLabel = 'Wpłaty uczestników';

    protected static bool $shouldRegisterNavigation = false;

    public function mount(int|string $record): void
    {
        $settlement = $record instanceof EventSettlement
            ? $record
            : EventSettlement::query()->findOrFail($record);

        $url = EventSettlementResource::getEventFinanceParticipantPaymentsUrlForSettlement($settlement);

        abort_unless($url, 404);

        $this->redirect($url);
    }
}
