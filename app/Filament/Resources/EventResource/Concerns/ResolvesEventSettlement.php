<?php

namespace App\Filament\Resources\EventResource\Concerns;

use App\Models\Event;
use App\Models\EventSettlement;

trait ResolvesEventSettlement
{
    public EventSettlement $settlement;

    protected function resolveEventSettlement(Event $event): EventSettlement
    {
        return $this->settlement = EventSettlement::findOrCreateActiveForEvent($event);
    }
}
