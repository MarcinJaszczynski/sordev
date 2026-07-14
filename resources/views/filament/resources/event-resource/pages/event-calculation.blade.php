<x-filament-panels::page>
    @include('filament.resources.event-resource.components.finance-sub-navigation', ['record' => $record])

    <div class="space-y-6">
        @foreach($this->getWidgets() as $widget)
            @livewire($widget, ['record' => $record])
        @endforeach
    </div>
</x-filament-panels::page>
