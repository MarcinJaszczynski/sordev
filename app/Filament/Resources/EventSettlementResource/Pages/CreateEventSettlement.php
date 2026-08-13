<?php

namespace App\Filament\Resources\EventSettlementResource\Pages;

use App\Filament\Pages\FinanceOverviewPage;
use App\Filament\Resources\EventSettlementResource;
use Filament\Resources\Pages\Page;

/**
 * Legacy create — zamrożone; rozliczenie powstaje z imprezy (EventFinance).
 *
 * Nie dziedziczy po CreateRecord: mount tylko redirectuje, a CreateRecord
 * odpala lifecycle formularza / getRecord bez sensu dla strony-zombie.
 */
class CreateEventSettlement extends Page
{
    protected static string $resource = EventSettlementResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.event-finance-redirect';

    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        $this->redirect(FinanceOverviewPage::getUrl());
    }
}
