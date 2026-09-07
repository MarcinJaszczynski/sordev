<?php

namespace App\Filament\Pilot\Concerns;

use App\Models\Event;
use Illuminate\Contracts\Support\Htmlable;

trait HasPilotTripNav
{
    public function getPilotTripEvent(): ?Event
    {
        if (property_exists($this, 'record') && $this->record instanceof Event) {
            return $this->record;
        }

        if (property_exists($this, 'event') && $this->event instanceof Event) {
            return $this->event;
        }

        return null;
    }

    public function getPilotTripNavActiveTab(): ?string
    {
        return null;
    }

    /** Hero + pigułki zastępują nagłówek Filament. */
    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public function getSubheading(): ?string
    {
        return null;
    }
}
