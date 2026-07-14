<x-filament-panels::page>
    @include('filament.resources.event-resource.components.participants-sub-navigation', ['record' => $record])

    {{ $this->form }}
</x-filament-panels::page>
