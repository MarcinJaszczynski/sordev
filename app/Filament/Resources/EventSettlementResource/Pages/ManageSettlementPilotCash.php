<?php

namespace App\Filament\Resources\EventSettlementResource\Pages;

use App\Filament\Resources\EventSettlementResource;
use App\Filament\Resources\EventSettlementResource\Concerns\RedirectsToEventFinance;
use Filament\Resources\Pages\Page;

/** @deprecated Redirects to EventFinance — bookmark/legacy URL only. */
class ManageSettlementPilotCash extends Page
{
    use RedirectsToEventFinance;

    protected static string $resource = EventSettlementResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.event-finance-redirect';

    protected static ?string $navigationLabel = 'Gotówka pilota';

    protected static bool $shouldRegisterNavigation = false;
}
