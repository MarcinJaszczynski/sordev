@php
    /** @var \App\Filament\Resources\EventResource\Pages\ManageEventClientPortal $this */
@endphp

<div>
    @livewire('client-portal-settings-toolbar', ['eventId' => $this->record->getKey()], key('client-portal-toolbar-'.$this->record->getKey()))
</div>
