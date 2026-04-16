<?php

namespace App\Filament\Resources\EventSettlementResource\Traits;

trait DispatchesSettlementDataChanged
{
    protected function dispatchSettlementDataChanged(): void
    {
        $this->dispatch('settlement-data-changed');
    }
}
