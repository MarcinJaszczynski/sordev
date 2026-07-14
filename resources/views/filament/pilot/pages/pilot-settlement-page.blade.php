<x-filament-panels::page>
    @livewire('pilot-trip-settlement-form', [
        'event' => $this->event,
        'showTripHeader' => false,
    ], key('pilot-settlement-'.$this->event->id))
</x-filament-panels::page>
