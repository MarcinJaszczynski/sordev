<?php

namespace App\Filament\Resources\EventSettlementResource\Concerns;

use App\Filament\Resources\EventSettlementResource;
use App\Models\EventSettlement;

/**
 * Legacy settlement UI pages redirect to the unified EventFinance screen.
 *
 * Używać tylko na zwykłym Filament Page (nie EditRecord / SingleRelationManagerPage):
 * te klasy wołają getRecord() w lifecycle i wymagają Modelu, a parametr routy to string.
 */
trait RedirectsToEventFinance
{
    public function mount(int|string $record): void
    {
        $settlement = $record instanceof EventSettlement
            ? $record
            : EventSettlement::query()->findOrFail($record);

        $url = EventSettlementResource::getEventFinanceUrlForSettlement($settlement);

        abort_unless($url, 404);

        $this->redirect($url);
    }
}
