<?php

namespace App\Filament\Resources\EventSettlementResource\Pages;

use App\Filament\Resources\EventSettlementResource;
use App\Filament\Resources\EventSettlementResource\Concerns\RedirectsToEventFinance;
use Filament\Resources\Pages\Page;

/**
 * Legacy edit — bookmark/deep-link only; redirects to EventFinance.
 *
 * Nie dziedziczy po EditRecord: mount redirectu zostawia $record jako string z routy,
 * a EditRecord::getRecord() wymaga Modelu → TypeError.
 */
class EditEventSettlement extends Page
{
    use RedirectsToEventFinance;

    protected static string $resource = EventSettlementResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.event-finance-redirect';

    protected static ?string $navigationLabel = 'Podsumowanie';

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static bool $shouldRegisterNavigation = false;
}
